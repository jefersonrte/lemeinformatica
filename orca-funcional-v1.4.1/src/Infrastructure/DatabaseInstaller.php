<?php
declare(strict_types=1);

namespace App\Infrastructure;

use App\Support\Config;
use PDO;
use RuntimeException;

final class DatabaseInstaller
{
    public function __construct(private readonly PDO $db, private readonly string $projectRoot)
    {
    }

    public function installBaseSchema(): void
    {
        $file = $this->projectRoot . '/database/schema.sql';
        $sql = is_file($file) ? (string) file_get_contents($file) : '';
        if ($sql === '') {
            throw new RuntimeException('Schema base não encontrado.');
        }

        $sql = preg_replace('/CREATE\s+DATABASE.*?;/is', '', $sql) ?? $sql;
        $sql = preg_replace('/\bUSE\s+[a-zA-Z0-9_]+\s*;/i', '', $sql) ?? $sql;
        $parts = preg_split('/--\s*Dados iniciais/iu', $sql, 2);
        $sql = is_array($parts) ? (string) $parts[0] : $sql;
        $this->executeStatements($sql);
    }

    public function seedDemo(): array
    {
        if (!filter_var(Config::get('demo.enabled', false), FILTER_VALIDATE_BOOL)) {
            return [];
        }

        $seeded = [];
        foreach ([
            'Alvenaria e Argamassa', 'Revestimentos Cerâmicos', 'Tintas e Impermeabilizantes',
            'Instalações Elétricas', 'Instalações Hidráulicas', 'Estrutura e Madeira',
            'Coberturas e Telhados', 'Esquadrias e Vidros', 'Pisos e Acabamentos', 'Ferramentas e EPI',
        ] as $category) {
            $statement = $this->db->prepare('INSERT IGNORE INTO categorias (nome, ativo) VALUES (?, 1)');
            $statement->execute([$category]);
        }

        $demoUser = $this->findId('SELECT id FROM usuarios WHERE email = ?', ['cliente.demo@orca.invalid']);
        if ($demoUser === 0) {
            $this->db->prepare('INSERT INTO usuarios (nome,email,senha,role,ativo,email_verificado) VALUES (?,?,?,?,1,1)')
                ->execute(['Cliente Demonstração', 'cliente.demo@orca.invalid', password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT), 'cliente']);
            $demoUser = (int) $this->db->lastInsertId();
            $seeded[] = 'usuario_demo';
        }

        $clientId = $this->findId('SELECT id FROM clientes WHERE usuario_id = ?', [$demoUser]);
        if ($clientId === 0) {
            $this->db->prepare('INSERT INTO clientes (usuario_id,razao_social,nome_fantasia,email,cidade,estado,obs) VALUES (?,?,?,?,?,?,?)')
                ->execute([$demoUser, 'Horizonte Empreendimentos', 'Cliente Modelo', 'projetos@exemplo.invalid', 'Florianópolis', 'SC', 'Dados demonstrativos da versão 1.2.0.']);
            $clientId = (int) $this->db->lastInsertId();
            $seeded[] = 'cliente_demo';
        }

        $workId = $this->findId('SELECT id FROM obras WHERE cliente_id = ? AND nome = ?', [$clientId, 'Residencial Horizonte']);
        if ($workId === 0) {
            $this->db->prepare('INSERT INTO obras (cliente_id,nome,descricao,endereco,cidade,estado,status,data_inicio,data_prev_fim,valor_total,progresso) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$clientId, 'Residencial Horizonte', 'Projeto residencial completo para demonstração do acompanhamento executivo.', 'Rua das Palmeiras, 120', 'Florianópolis', 'SC', 'em_andamento', '2026-02-10', '2026-12-18', 980000, 68]);
            $workId = (int) $this->db->lastInsertId();
            $seeded[] = 'obra_demo';
        }

        if ($this->count('SELECT COUNT(*) FROM obra_etapas WHERE obra_id = ?', [$workId]) === 0) {
            $step = $this->db->prepare('INSERT INTO obra_etapas (obra_id,nome,ordem,status,progresso) VALUES (?,?,?,?,?)');
            foreach ([['Fundação', 1, 'concluida', 100], ['Estrutura', 2, 'concluida', 100], ['Alvenaria', 3, 'em_andamento', 82], ['Cobertura', 4, 'em_andamento', 55], ['Elétrica / Hidráulica', 5, 'em_andamento', 42], ['Revestimentos', 6, 'pendente', 20], ['Acabamento', 7, 'pendente', 0]] as $row) {
                $step->execute([$workId, ...$row]);
            }
            $seeded[] = 'etapas_demo';
        }

        $electricalCategory = $this->findId('SELECT id FROM categorias WHERE nome = ?', ['Instalações Elétricas']);
        $roofCategory = $this->findId('SELECT id FROM categorias WHERE nome = ?', ['Coberturas e Telhados']);
        $supplierId = $this->findId('SELECT id FROM fornecedores WHERE email = ?', ['contato@fornecedordemo.invalid']);
        if ($supplierId === 0) {
            $this->db->prepare('INSERT INTO fornecedores (nome,email,telefone,whatsapp,contato,cidade,estado,obs) VALUES (?,?,?,?,?,?,?,?)')
                ->execute(['Materiais Sul Demonstração', 'contato@fornecedordemo.invalid', '(48) 3000-0000', '(48) 99999-0000', 'Equipe Comercial', 'São José', 'SC', 'Fornecedor fictício para testes.']);
            $supplierId = (int) $this->db->lastInsertId();
            $seeded[] = 'fornecedor_demo';
        }
        foreach (array_filter([$electricalCategory, $roofCategory]) as $categoryId) {
            $this->db->prepare('INSERT IGNORE INTO fornecedor_categorias (fornecedor_id,categoria_id) VALUES (?,?)')->execute([$supplierId, $categoryId]);
        }

        if ($this->findId('SELECT id FROM produtos WHERE codigo = ?', ['ORCA-CABO-001']) === 0 && $electricalCategory > 0) {
            $this->db->prepare('INSERT INTO produtos (categoria_id,codigo,nome,unidade,descricao) VALUES (?,?,?,?,?)')
                ->execute([$electricalCategory, 'ORCA-CABO-001', 'Cabo flexível 2,5 mm²', 'M', 'Produto demonstrativo.']);
            $seeded[] = 'produto_demo';
        }

        $budgetId = $this->findId('SELECT id FROM orcamentos WHERE obra_id = ? AND titulo = ?', [$workId, 'Orçamento executivo 2026']);
        if ($budgetId === 0) {
            $this->db->prepare('INSERT INTO orcamentos (obra_id,cliente_id,titulo,status,total_estimado,total_cotado,tipo_origem,obs) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$workId, $clientId, 'Orçamento executivo 2026', 'cotado', 980000, 914500, 'manual', 'Valores demonstrativos.']);
            $budgetId = (int) $this->db->lastInsertId();
            $item = $this->db->prepare('INSERT INTO orcamento_itens (orcamento_id,categoria_id,descricao,unidade,quantidade,preco_unitario,preco_cotado,fornecedor_id) VALUES (?,?,?,?,?,?,?,?)');
            $item->execute([$budgetId, $electricalCategory ?: null, 'Instalações elétricas completas', 'VB', 1, 215000, 198500, $supplierId]);
            $item->execute([$budgetId, $roofCategory ?: null, 'Cobertura e impermeabilização', 'VB', 1, 184000, 176000, $supplierId]);
            $seeded[] = 'orcamento_demo';
        }

        $quotationId = $this->findId('SELECT id FROM cotacoes WHERE orcamento_id = ? AND fornecedor_id = ?', [$budgetId, $supplierId]);
        if ($quotationId === 0) {
            $this->db->prepare('INSERT INTO cotacoes (orcamento_id,fornecedor_id,status,canal_envio,mensagem,resposta,data_envio,data_resposta) VALUES (?,?,?,?,?,?,NOW(),NOW())')
                ->execute([$budgetId, $supplierId, 'respondida', 'email', 'Solicitação demonstrativa.', 'Cotação recebida e conferida.']);
            $quotationId = (int) $this->db->lastInsertId();
            $seeded[] = 'cotacao_demo';
        }

        if ($this->findId('SELECT id FROM compras WHERE obra_id = ? AND fornecedor_id = ?', [$workId, $supplierId]) === 0) {
            $this->db->prepare('INSERT INTO compras (obra_id,cotacao_id,fornecedor_id,status,valor_total,data_pedido,data_prev_entrega,obs) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$workId, $quotationId, $supplierId, 'confirmado', 645200, '2026-06-20', '2026-09-15', 'Compra demonstrativa para o painel financeiro.']);
            $seeded[] = 'compra_demo';
        }

        $this->seedPlants($workId);
        return $seeded;
    }

    /** @return array{sources:int,copied:int,existing:int} */
    public function migrateLegacyUploads(): array
    {
        $parentDirectory = dirname($this->projectRoot);
        $legacyDirectories = glob($parentDirectory . '/orca-funcional-v*/uploads', GLOB_ONLYDIR) ?: [];
        usort($legacyDirectories, static fn (string $a, string $b): int => strnatcasecmp($b, $a));
        $legacyDirectories[] = $parentDirectory . '/orca/uploads';

        return (new LegacyUploadMigrator())->migrate(
            (string) Config::get('uploads.directory'),
            $legacyDirectories
        );
    }

    public function executeStatements(string $sql): void
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            $this->db->exec($statement);
        }
    }

    private function seedPlants(int $workId): void
    {
        $sourceDir = $this->projectRoot . '/assets/demo-plants';
        $targetDir = rtrim((string) Config::get('uploads.directory'), '/\\') . DIRECTORY_SEPARATOR . 'plantas' . DIRECTORY_SEPARATOR . $workId;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0750, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Não foi possível criar o acervo demonstrativo.');
        }

        $plants = [
            ['Cobertura arquitetônica', 'cobertura.svg', 'Cobertura e volumetria em perspectiva.'],
            ['Planta baixa — pavimento térreo', 'planta-baixa.svg', 'Distribuição dos ambientes e cotas principais.'],
            ['Projeto elétrico executivo', 'eletrico.svg', 'Circuitos, pontos e quadro de distribuição.'],
            ['Projeto hidráulico', 'hidraulico.svg', 'Redes de água, esgoto e pontos técnicos.'],
        ];
        foreach ($plants as $plant) {
            [$title, $fileName, $description] = $plant;
            $source = $sourceDir . '/' . $fileName;
            $target = $targetDir . DIRECTORY_SEPARATOR . $fileName;
            if (!is_file($target) && (!is_file($source) || !copy($source, $target))) {
                throw new RuntimeException('Falha ao preparar planta demonstrativa.');
            }
            if ($this->findId('SELECT id FROM obra_plantas WHERE obra_id = ? AND titulo = ? AND versao = 1', [$workId, $title]) > 0) {
                continue;
            }
            $relative = 'plantas/' . $workId . '/' . $fileName;
            $this->db->prepare('INSERT INTO obra_plantas (obra_id,titulo,descricao,arquivo,nome_original,mime_type,tamanho,versao,usuario_id) VALUES (?,?,?,?,?,?,?,?,NULL)')
                ->execute([$workId, $title, $description, $relative, $fileName, 'image/svg+xml', filesize($target), 1]);
        }
    }

    private function findId(string $sql, array $params): int
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return (int) ($statement->fetchColumn() ?: 0);
    }

    private function count(string $sql, array $params): int
    {
        return $this->findId($sql, $params);
    }
}
