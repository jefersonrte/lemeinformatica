<?php
declare(strict_types=1);

namespace App\Domain\Cotacao;

final class MensagemCotacao
{
    public static function montar(array $orcamento, array $fornecedor, array $itens, string $prazo, string $complemento = ''): string
    {
        $prazoTimestamp = strtotime($prazo);
        $prazoFormatado = $prazoTimestamp !== false ? date('d/m/Y', $prazoTimestamp) : date('d/m/Y', strtotime('+3 days'));
        $linhas = "Cód | Descrição | Unid | Qtd\n" . str_repeat('-', 50) . "\n";
        foreach ($itens as $item) {
            $linhas .= trim((string) ($item['obs'] ?? '')) . ' | '
                . trim((string) ($item['descricao'] ?? '')) . ' | '
                . trim((string) ($item['unidade'] ?? 'UN')) . ' | '
                . number_format((float) ($item['quantidade'] ?? 0), 3, ',', '.') . "\n";
        }

        $mensagem = "Olá, " . trim((string) ($fornecedor['nome'] ?? '')) . "!\n\n";
        $mensagem .= "Solicitamos cotação para os materiais abaixo, referentes à obra: *"
            . trim((string) ($orcamento['obra'] ?? $orcamento['obra_nome'] ?? '')) . '* - '
            . trim((string) ($orcamento['razao_social'] ?? '')) . ".\n\n";
        $mensagem .= "ITENS PARA COTAÇÃO:\n{$linhas}\n";
        $mensagem .= "Por favor, retorne com os valores unitários até: *{$prazoFormatado}*.\n\n";
        if (trim($complemento) !== '') {
            $mensagem .= trim($complemento) . "\n\n";
        }
        return $mensagem . "Atenciosamente,\n" . APP_NAME;
    }

    /** Link "clique para conversar" do WhatsApp com a mensagem pronta; null sem número válido. */
    public static function linkWhatsapp(?string $numero, string $mensagem): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $numero) ?? '';
        if (strlen($digitos) === 10 || strlen($digitos) === 11) {
            $digitos = '55' . $digitos;
        }
        if (strlen($digitos) < 12 || strlen($digitos) > 13) {
            return null;
        }
        return 'https://wa.me/' . $digitos . '?text=' . rawurlencode($mensagem);
    }
}
