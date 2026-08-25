<?php
// helpers/mailer.php — Envio de email (PHPMailer) e WhatsApp (CallMeBot/Z-API)
require_once __DIR__ . '/../config/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function enviarEmail(string $para, string $nome, string $assunto, string $corpo): bool {
    try {
        require_once __DIR__ . '/../vendor/autoload.php';
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USER;
        $mail->Password   = MAIL_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($para, $nome);
        $mail->Subject = 'Solicitação de Cotação — ' . $assunto;
        $mail->isHTML(false);
        $mail->Body = $corpo;
        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('[mailer] ' . $e->getMessage());
        return false;
    }
}

function enviarEmailHtml(string $para, string $nome, string $assunto, string $htmlCorpo): bool {
    try {
        require_once __DIR__ . '/../vendor/autoload.php';
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USER;
        $mail->Password   = MAIL_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($para, $nome);
        $mail->Subject = $assunto;
        $mail->isHTML(true);
        $mail->Body    = $htmlCorpo;
        $mail->AltBody = strip_tags($htmlCorpo);
        $mail->send();
        return true;
    } catch (Throwable) {
        return false;
    }
}

/**
 * Envia via CallMeBot (gratuito, requer opt-in do número).
 * Para produção com volume, use Z-API, UltraMsg ou similar.
 */
function enviarWhatsapp(string $telefone, string $mensagem): bool {
    $phone = '55' . preg_replace('/\D/', '', $telefone);
    $apiKey = WA_API_KEY;
    if (!$apiKey || $apiKey === 'SUA_CHAVE_CALLMEBOT') {
        // Sem chave configurada — apenas loga
        error_log('[whatsapp] Chave não configurada. Mensagem para ' . $phone . ': ' . mb_substr($mensagem, 0, 100));
        return false;
    }
    $url = WA_API_URL . '?' . http_build_query([
        'phone'   => $phone,
        'text'    => $mensagem,
        'apikey'  => $apiKey,
    ]);
    $ctx = stream_context_create(['http'=>['timeout'=>15]]);
    $resp = @file_get_contents($url, false, $ctx);
    return $resp !== false;
}

/**
 * Gera link wa.me para abertura manual (fallback sem API)
 */
function waLink(string $telefone, string $mensagem): string {
    $phone = '55' . preg_replace('/\D/', '', $telefone);
    return 'https://wa.me/' . $phone . '?text=' . rawurlencode($mensagem);
}
