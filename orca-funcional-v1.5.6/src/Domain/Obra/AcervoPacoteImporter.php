<?php
declare(strict_types=1);

namespace App\Domain\Obra;

use App\Domain\Orcamento\OrcamentoService;
use App\Domain\Orcamento\OrcamentoStatus;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Importa o pacote do acervo gerado por `php scripts/acervo.php pacote` (ZIP com manifest.json e plantas/).
 *
 * Cada obra do pacote passa pelos mesmos serviços das telas: cliente (fictício), obra com as etapas
 * padrão, orçamentos com itens e plantas no catálogo técnico. É idempotente: obras e orçamentos são
 * reconhecidos pelo hash do arquivo de origem (ou pelo cliente/nome e obra/título quando já foram
 * digitados pelas telas) e plantas pelo nome e tamanho, então reenviar o pacote só completa o que faltou.
 */
final class AcervoPacoteImporter
{
    public const VERSAO = 1;

    public function __construct(
        private readonly PDO $db,
        private readonly string $uploadRoot,
        private readonly int $plantaMaxMb = 80,
    ) {
    }

    /** @return array{versao:int,gerado_em:string,obras:list<array<string,mixed>>} */
    public function manifesto(string $pacote): array
    {
        return self::lerManifesto($pacote);
    }

    /** @return array{versao:int,gerado_em:string,obras:list<array<string,mixed>>} */
    public static function lerManifesto(string $pacote): array
    {
        $zip = self::abrir($pacote);
        try {
            $manifesto = json_decode((string) $zip->getFromName('manifest.json'), true);
        } finally {
            $zip->close();
        }
        if (!is_array($manifesto) || (int) ($manifesto['versao'] ?? 0) !== self::VERSAO || !is_array($manifesto['obras'] ?? null)) {
            throw new InvalidArgumentException('Pacote inválido: gere-o com "php scripts/acervo.php pacote".');
        }
        return $manifesto;
    }

    /**
     * Importa a obra de índice $indice do pacote.
     * @return array{obra:string,obra_id:int,orcamentos:int,plantas:int,avisos:list<string>}
     */
    public function importarObra(string $pacote, int $indice, ?int $usuarioId): array
    {
        $manifesto = $this->manifesto($pacote);
        $dados = $manifesto['obras'][$indice] ?? null;
        if (!is_array($dados)) {
            throw new InvalidArgumentException('Obra ' . ($indice + 1) . ' não existe no pacote.');
        }

        $clienteId = $this->cliente($dados['cliente']);
        $obraId = $this->obra($clienteId, $dados['obra'], array_column($dados['orcamentos'], 'arquivo_origem'));
        $resultado = ['obra' => (string) $dados['obra']['nome'], 'obra_id' => $obraId, 'orcamentos' => 0, 'plantas' => 0, 'avisos' => []];

        $orcamentos = new OrcamentoService($this->db);
        $existe = $this->db->prepare('SELECT COUNT(*) FROM orcamentos WHERE obra_id = ? AND arquivo_origem = ?');
        // Orçamento já digitado pelas telas (mesma obra e título, sem arquivo de origem): só ganha o vínculo
        $digitado = $this->db->prepare('SELECT id FROM orcamentos WHERE obra_id = ? AND titulo = ? AND (arquivo_origem IS NULL OR arquivo_origem = \'\') ORDER BY id LIMIT 1');
        foreach ($dados['orcamentos'] as $orcamento) {
            $existe->execute([$obraId, (string) $orcamento['arquivo_origem']]);
            if ((int) $existe->fetchColumn() > 0) {
                continue;
            }
            $digitado->execute([$obraId, mb_substr((string) $orcamento['titulo'], 0, 200)]);
            $idDigitado = (int) $digitado->fetchColumn();
            if ($idDigitado > 0) {
                $this->db->prepare('UPDATE orcamentos SET arquivo_origem = ? WHERE id = ?')->execute([mb_substr((string) $orcamento['arquivo_origem'], 0, 255), $idDigitado]);
                continue;
            }
            $id = $orcamentos->criar(
                $obraId,
                mb_substr((string) $orcamento['titulo'], 0, 200),
                (string) ($orcamento['tipo_origem'] ?? 'excel'),
                (string) ($orcamento['obs'] ?? ''),
                $orcamento['itens'],
                (float) ($orcamento['bdi'] ?? 0)
            );
            $status = in_array($orcamento['status'] ?? '', OrcamentoStatus::TODOS, true) ? $orcamento['status'] : 'rascunho';
            $this->db->prepare('UPDATE orcamentos SET arquivo_origem = ?, status = ? WHERE id = ?')
                ->execute([mb_substr((string) $orcamento['arquivo_origem'], 0, 255), $status, $id]);
            $resultado['orcamentos']++;
        }

        $plantas = new PlantaService($this->db, $this->uploadRoot, $this->plantaMaxMb);
        $jaTem = $this->db->prepare('SELECT COUNT(*) FROM obra_plantas WHERE obra_id = ? AND nome_original = ? AND tamanho = ?');
        $zip = self::abrir($pacote);
        try {
            foreach ($dados['plantas'] as $planta) {
                $jaTem->execute([$obraId, (string) $planta['nome_original'], (int) $planta['tamanho']]);
                if ((int) $jaTem->fetchColumn() > 0) {
                    continue;
                }
                $temporario = tempnam(sys_get_temp_dir(), 'acervo');
                try {
                    $origem = $zip->getStream((string) $planta['arquivo']);
                    if ($origem === false) {
                        throw new RuntimeException('arquivo ausente no pacote');
                    }
                    $destino = fopen((string) $temporario, 'wb');
                    stream_copy_to_stream($origem, $destino);
                    fclose($origem);
                    fclose($destino);
                    $plantas->armazenar($obraId, mb_substr((string) $planta['titulo'], 0, 180), (string) ($planta['descricao'] ?? 'Planta do acervo.'), [
                        'name' => (string) $planta['nome_original'], 'tmp_name' => $temporario,
                        'error' => UPLOAD_ERR_OK, 'size' => (int) filesize((string) $temporario),
                    ], $usuarioId, true);
                    $resultado['plantas']++;
                } catch (Throwable $exception) {
                    $resultado['avisos'][] = $planta['nome_original'] . ': ' . $exception->getMessage();
                } finally {
                    @unlink((string) $temporario);
                }
            }
        } finally {
            $zip->close();
        }
        return $resultado;
    }

    private function cliente(array $cliente): int
    {
        $email = (string) $cliente['chave'];
        if (!preg_match('/^acervo\+[a-f0-9]{6,40}@orca\.local$/', $email)) {
            throw new InvalidArgumentException('Cliente do pacote sem chave válida.');
        }
        $busca = $this->db->prepare('SELECT c.id FROM clientes c JOIN usuarios u ON u.id = c.usuario_id WHERE u.email = ?');
        $busca->execute([$email]);
        $id = (int) $busca->fetchColumn();
        $nome = mb_substr((string) $cliente['nome'], 0, 150);
        if ($id > 0) {
            $this->db->prepare('UPDATE clientes c JOIN usuarios u ON u.id = c.usuario_id SET c.razao_social = ?, u.nome = ?, c.cidade = ?, c.estado = ? WHERE c.id = ?')
                ->execute([$nome, $nome, (string) ($cliente['cidade'] ?? ''), (string) ($cliente['estado'] ?? 'SC'), $id]);
            return $id;
        }
        // Usuário inativo e sem senha conhecida: o cliente fictício existe só para organizar as obras
        $this->db->prepare('INSERT INTO usuarios (nome,email,senha,role,ativo,email_verificado) VALUES (?,?,?,?,0,1)')
            ->execute([$nome, $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), 'cliente']);
        $this->db->prepare('INSERT INTO clientes (usuario_id,razao_social,cidade,estado,obs) VALUES (?,?,?,?,?)')
            ->execute([(int) $this->db->lastInsertId(), $nome, (string) ($cliente['cidade'] ?? ''), (string) ($cliente['estado'] ?? 'SC'), (string) ($cliente['obs'] ?? 'Cliente fictício — orçamento real do acervo.')]);
        return (int) $this->db->lastInsertId();
    }

    /** @param list<string> $hashes */
    private function obra(int $clienteId, array $obra, array $hashes): int
    {
        $hashes = array_values(array_filter(array_map('strval', $hashes)));
        if ($hashes !== []) {
            $marcadores = implode(',', array_fill(0, count($hashes), '?'));
            $busca = $this->db->prepare('SELECT obra_id FROM orcamentos WHERE arquivo_origem IN (' . $marcadores . ') LIMIT 1');
            $busca->execute($hashes);
            $id = (int) $busca->fetchColumn();
            if ($id > 0) {
                return $id;
            }
        }
        // Obra já cadastrada pelas telas para o mesmo cliente
        $mesmoNome = $this->db->prepare('SELECT id FROM obras WHERE cliente_id = ? AND nome = ? ORDER BY id LIMIT 1');
        $mesmoNome->execute([$clienteId, mb_substr((string) $obra['nome'], 0, 200)]);
        $id = (int) $mesmoNome->fetchColumn();
        if ($id > 0) {
            return $id;
        }
        $service = new ObraService($this->db);
        $id = $service->criar([
            'cliente_id' => $clienteId, 'nome' => mb_substr((string) $obra['nome'], 0, 200),
            'descricao' => (string) ($obra['descricao'] ?? ''), 'endereco' => (string) ($obra['endereco'] ?? ''),
            'cidade' => (string) ($obra['cidade'] ?? ''), 'estado' => (string) ($obra['estado'] ?? 'SC'),
            'status' => (string) ($obra['status'] ?? 'concluida'), 'data_inicio' => null, 'data_prev_fim' => null,
            'valor_total' => (float) ($obra['valor_total'] ?? 0), 'progresso' => (int) ($obra['progresso'] ?? 100),
        ]);
        $area = isset($obra['area_construida']) && (float) $obra['area_construida'] > 0 ? (float) $obra['area_construida'] : null;
        $service->atualizarCaracteristicas($id, $area, $obra['tipologia'] ?? null, $obra['padrao'] ?? null);
        return $id;
    }

    private static function abrir(string $pacote): ZipArchive
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('A extensão ZIP do PHP não está disponível no servidor.');
        }
        $zip = new ZipArchive();
        if (!is_file($pacote) || $zip->open($pacote) !== true) {
            throw new InvalidArgumentException('Não foi possível abrir o pacote ZIP.');
        }
        return $zip;
    }
}
