<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://www.globalshield.ru');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Конфигурация базы данных PostgreSQL для Payment API
define('DB_HOST', getenv('PAYMENT_DB_HOST') ?: 'payment-db');
define('DB_PORT', getenv('PAYMENT_DB_PORT') ?: '5432');
define('DB_NAME', getenv('PAYMENT_DB_NAME') ?: 'payment_api');
define('DB_USER', getenv('PAYMENT_DB_USER') ?: 'payment_user');
define('DB_PASS', getenv('PAYMENT_DB_PASSWORD') ?: 'payment_password_456');

// Telegram конфигурация
define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: '');
define('TELEGRAM_CHAT_ID', getenv('TELEGRAM_NOTIFY_CRM_CHAT_ID') ?: '');

function getDBConnection() {
    static $conn = null;
    if ($conn === null) {
        $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
        try {
            $conn = new PDO($dsn, DB_USER, DB_PASS);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw $e;
        }
    }
    return $conn;
}

function logPayment($data) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        INSERT INTO payments (user_id, plan_id, amount, status, payment_data, created_at)
        VALUES (:user_id, :plan_id, :amount, :status, :payment_data, NOW())
    ");
    return $stmt->execute($data);
}

function logAudit($action, $user_id = null, $details = null) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        INSERT INTO audit_log (action, user_id, details, ip_address, user_agent, created_at)
        VALUES (:action, :user_id, :details, :ip_address, :user_agent, NOW())
    ");

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    return $stmt->execute([
        'action' => $action,
        'user_id' => $user_id,
        'details' => $details ? json_encode($details) : null,
        'ip_address' => $ip_address,
        'user_agent' => $user_agent
    ]);
}

function sendTelegramNotification($message) {
    if (!TELEGRAM_BOT_TOKEN || !TELEGRAM_CHAT_ID) return;

    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $data = [
        'chat_id' => TELEGRAM_CHAT_ID,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($data)
        ]
    ];

    $context = stream_context_create($options);
    @file_get_contents($url, false, $context);
}

// Health check endpoint
if (basename($_SERVER['PHP_SELF']) == 'health-check.php') {
    try {
        $conn = getDBConnection();
        // Проверяем доступность основных таблиц
        $tables = ['payments', 'users', 'audit_log'];
        foreach ($tables as $table) {
            $stmt = $conn->query("SELECT 1 FROM $table LIMIT 1");
        }
        echo "OK";
        logAudit('health_check', null, ['status' => 'success']);
    } catch (Exception $e) {
        http_response_code(500);
        echo "Database connection failed";
        logAudit('health_check', null, ['status' => 'failed', 'error' => $e->getMessage()]);
    }
    exit();
}
?>
