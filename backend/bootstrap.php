<?php
// QuizOnline Core Bootstrap

// Enable CORS so requests from VS Code Live Server or custom ports work seamlessly
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

header("Content-Type: application/json; charset=utf-8");

// Global exception and shutdown handler to guarantee JSON output and eliminate blank responses
set_exception_handler(function (Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
        header("Content-Type: application/json; charset=utf-8");
    }
    echo json_encode(["error" => $e->getMessage()]);
    exit();
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            http_response_code(500);
            header("Content-Type: application/json; charset=utf-8");
        }
        echo json_encode([
            "error" => "PHP Error: " . $error['message'] . " in " . basename($error['file']) . " on line " . $error['line']
        ]);
        exit();
    }
});

// Start session safely (compatible with PHP 7.4, 8.0, 8.1, 8.2, 8.3)
if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.cookie_httponly', '1');
    @session_start();
}

$config = require __DIR__ . "/config.php";

$dbHost = $config["db"]["host"] ?? "127.0.0.1";
$dbName = $config["db"]["name"] ?? "quizonline";
$dbUser = $config["db"]["user"] ?? "root";
$dbPass = $config["db"]["pass"] ?? "";
$dbPort = !empty($config["db"]["port"]) ? (int) $config["db"]["port"] : 3306;

$db = null;
$hostsToTry = array_unique([$dbHost, "127.0.0.1", "localhost"]);

foreach ($hostsToTry as $host) {
    try {
        $portDsn = ($host !== "localhost" && $dbPort !== 3306) ? ";port={$dbPort}" : "";
        $pdo = new PDO(
            "mysql:host={$host}{$portDsn};charset=utf8mb4",
            $dbUser,
            $dbPass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        // Ensure database exists
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$dbName}`");

        // Auto-initialize if users table is missing or empty
        $checkTable = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
        $needsSeed = true;
        if ($checkTable) {
            $userCount = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            if ($userCount > 0) {
                $needsSeed = false;
            }
        }

        if ($needsSeed) {
            $sqlFile = dirname(__DIR__) . "/database.sql";
            if (file_exists($sqlFile)) {
                $sqlContent = file_get_contents($sqlFile);
                if ($sqlContent) {
                    $pdo->exec($sqlContent);
                }
            }
        }

        $db = $pdo;
        break;
    } catch (PDOException $ex) {
        // Try next host in fallback list
        continue;
    }
}

if (!$db) {
    reply([
        "error" => "Database connection failed. Please ensure MySQL is running in XAMPP (Apache & MySQL green) and database 'quizonline' is accessible with user '{$dbUser}'."
    ], 500);
}

// Universal response function without strict 'never' return type (compatible with PHP 7.4 through 8.3+)
function reply(array $data, int $status = 200)
{
    http_response_code($status);
    echo json_encode($data);
    exit();
}

function input(): array
{
    return json_decode(file_get_contents("php://input"), true) ?? [];
}

function current_user(): ?array
{
    return $_SESSION["user"] ?? null;
}

function login_required(): array
{
    $u = current_user();
    if (!$u) {
        reply(["error" => "Please login"], 401);
    }
    return $u;
}

function admin_required(): array
{
    $u = login_required();
    if ($u["role"] !== "admin") {
        reply(["error" => "Admin access required"], 403);
    }
    return $u;
}

function teacher_required(): array
{
    $u = login_required();
    if (!in_array($u["role"], ["teacher", "admin"], true)) {
        reply(["error" => "Teacher access required"], 403);
    }
    return $u;
}

function clean(string $value): string
{
    return trim($value);
}

function ensure_settings_table(PDO $db): void
{
    $db->exec(
        "CREATE TABLE IF NOT EXISTS app_settings (
            setting_key VARCHAR(80) PRIMARY KEY,
            setting_value TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",
    );
}

function encrypt_secret(string $plain, string $appSecret): string
{
    $key = hash("sha256", $appSecret, true);
    $iv = random_bytes(12);
    $tag = "";
    $cipher = openssl_encrypt(
        $plain,
        "aes-256-gcm",
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
    );
    if ($cipher === false) {
        throw new RuntimeException("Could not protect the API key");
    }
    return base64_encode($iv . $tag . $cipher);
}

function decrypt_secret(string $encoded, string $appSecret): string
{
    $payload = base64_decode($encoded, true);
    if ($payload === false || strlen($payload) < 29) {
        return "";
    }
    $plain = openssl_decrypt(
        substr($payload, 28),
        "aes-256-gcm",
        hash("sha256", $appSecret, true),
        OPENSSL_RAW_DATA,
        substr($payload, 0, 12),
        substr($payload, 12, 16),
    );
    return $plain === false ? "" : $plain;
}

function setting(PDO $db, string $key): ?string
{
    ensure_settings_table($db);
    $statement = $db->prepare(
        "SELECT setting_value FROM app_settings WHERE setting_key = ?",
    );
    $statement->execute([$key]);
    $value = $statement->fetchColumn();
    return $value === false ? null : (string) $value;
}
