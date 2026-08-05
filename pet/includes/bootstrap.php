<?php
declare(strict_types=1);

$petVersionPath = dirname(__DIR__) . '/VERSION';
$petVersionContents = is_file($petVersionPath) ? file_get_contents($petVersionPath) : false;
$petVersion = is_string($petVersionContents) ? trim($petVersionContents) : '';
define('PET_VERSION', $petVersion !== '' ? $petVersion : '1.1.1');
define('PET_ROOT', dirname(__DIR__));
define('PET_UPLOAD_ROOT', PET_ROOT . '/uploads');
define('PET_MAX_UPLOAD_BYTES', 5 * 1024 * 1024);

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/http.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';
require_once dirname(__DIR__, 2) . '/includes/session.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/migrations.php';
require_once __DIR__ . '/sso.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/uploads.php';

final class PetDomainException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'REGRA_DE_NEGOCIO',
        public readonly int $httpStatus = 422
    ) {
        parent::__construct($message);
    }
}

function pet_boot_page(): array
{
    apply_page_security_headers();
    start_api_session();

    $user = current_api_user();
    if ($user === null) {
        header('Location: ../login.php?next=pet');
        exit;
    }

    return pet_user_context($user);
}

function pet_boot_api(): array
{
    apply_page_security_headers();
    start_api_session();

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    $user = current_api_user();
    if ($user === null) {
        json_response([
            'ok' => false,
            'codigo' => 'SESSAO_NECESSARIA',
            'erro' => 'Sua sessao expirou. Entre novamente.'
        ], 401);
    }

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!validate_api_csrf(is_string($token) ? $token : null)) {
            json_response([
                'ok' => false,
                'codigo' => 'CSRF_INVALIDO',
                'erro' => 'A sessao de seguranca expirou. Atualize a pagina.'
            ], 419);
        }
    }

    return pet_user_context($user);
}

function pet_api_exception(Throwable $exception, string $fallback = 'Nao foi possivel concluir a operacao.'): void
{
    error_log('[PET] ' . get_class($exception) . ': ' . $exception->getMessage());

    if ($exception instanceof PetDomainException) {
        json_response([
            'ok' => false,
            'codigo' => $exception->errorCode,
            'erro' => $exception->getMessage(),
        ], $exception->httpStatus);
    }

    if ($exception instanceof mysqli_sql_exception) {
        $code = (int) $exception->getCode();

        if ($code === 1146) {
            json_response([
                'ok' => false,
                'codigo' => 'PET_BANCO_NAO_INSTALADO',
                'erro' => 'O modulo Pet ainda nao foi instalado no banco de dados.'
            ], 503);
        }

        if ($code === 1062) {
            json_response([
                'ok' => false,
                'codigo' => 'REGISTRO_DUPLICADO',
                'erro' => 'Ja existe um cadastro com essa informacao unica.'
            ], 409);
        }
    }

    json_response([
        'ok' => false,
        'codigo' => 'ERRO_INTERNO',
        'erro' => $fallback
    ], 500);
}

function pet_audit(array $context, string $action, string $entity, ?int $entityId = null, array $details = []): void
{
    try {
        $userId = (int) $context['id'];
        $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $payload = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($payload)) {
            $payload = '{}';
        }

        $stmt = db()->prepare(
            'INSERT INTO pet_audit_log
                (usuario_id, acao, entidade, entidade_id, detalhes_json, ip)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('ississ', $userId, $action, $entity, $entityId, $payload, $ip);
        $stmt->execute();
    } catch (Throwable $exception) {
        error_log('[PET AUDIT] ' . $exception->getMessage());
    }
}
