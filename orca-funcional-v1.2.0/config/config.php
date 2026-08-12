<?php
declare(strict_types=1);

use App\Support\Config;

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/src/Support/Config.php';
Config::load(__DIR__ . '/runtime.php', $projectRoot);

defined('APP_NAME') || define('APP_NAME', (string) Config::get('app.name'));
defined('APP_URL') || define('APP_URL', rtrim((string) Config::get('app.url'), '/'));
defined('APP_ENV') || define('APP_ENV', (string) Config::get('app.environment'));
defined('APP_VERSION') || define('APP_VERSION', (string) Config::get('app.version'));

defined('MAIL_HOST') || define('MAIL_HOST', (string) Config::get('mail.host'));
defined('MAIL_PORT') || define('MAIL_PORT', (int) Config::get('mail.port'));
defined('MAIL_USER') || define('MAIL_USER', (string) Config::get('mail.user'));
defined('MAIL_PASS') || define('MAIL_PASS', (string) Config::get('mail.pass'));
defined('MAIL_FROM') || define('MAIL_FROM', (string) Config::get('mail.from'));
defined('MAIL_FROM_NAME') || define('MAIL_FROM_NAME', (string) Config::get('mail.from_name'));

defined('WA_API_URL') || define('WA_API_URL', (string) Config::get('whatsapp.api_url'));
defined('WA_API_KEY') || define('WA_API_KEY', (string) Config::get('whatsapp.api_key'));

defined('UPLOAD_DIR') || define('UPLOAD_DIR', (string) Config::get('uploads.directory'));
defined('UPLOAD_MAX_MB') || define('UPLOAD_MAX_MB', (int) Config::get('uploads.max_mb'));

defined('SESSION_NAME') || define('SESSION_NAME', (string) Config::get('security.session_name'));
defined('SESSION_LIFETIME') || define('SESSION_LIFETIME', (int) Config::get('security.session_lifetime'));
defined('BCRYPT_COST') || define('BCRYPT_COST', (int) Config::get('security.bcrypt_cost'));
defined('SECURE_COOKIES') || define('SECURE_COOKIES', (bool) Config::get('security.secure_cookies'));
defined('LOGIN_MAX_ATTEMPTS') || define('LOGIN_MAX_ATTEMPTS', (int) Config::get('security.login_max_attempts'));
defined('LOGIN_LOCK_MINUTES') || define('LOGIN_LOCK_MINUTES', (int) Config::get('security.login_lock_minutes'));
