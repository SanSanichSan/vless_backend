<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    logAudit('invalid_method', null, ['method' => $_SERVER['REQUEST_METHOD']]);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['plan_id']) || !isset($input['user_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    logAudit('missing_fields', $input['user_id'] ?? null, ['input' => $input]);
    exit();
}

$plans = [
    'start_99' => ['price' => 9900, 'name' => 'Старт'],
    'flex_199' => ['price' => 19900, 'name' => 'Гибкий'],
    'optimal_499' => ['price' => 49900, 'name' => 'Оптимальный'],
    'max_799' => ['price' => 79900, 'name' => 'Максимум'],
    'corporate' => ['price' => 399900, 'name' => 'Корпоративный'],
    'dedicated_node' => ['price' => 69900, 'name' => 'Выделенный узел']
];

$plan_id = $input['plan_id'];
$user_id = $input['user_id'];

if (!isset($plans[$plan_id])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid plan ID']);
    logAudit('invalid_plan', $user_id, ['plan_id' => $plan_id]);
    exit();
}

$plan = $plans[$plan_id];
$invoice_id = uniqid('inv_');

// Сохраняем в базу
try {
    logPayment([
        'user_id' => $user_id,
        'plan_id' => $plan_id,
        'amount' => $plan['price'],
        'status' => 'pending',
        'payment_data' => json_encode([
            'invoice_id' => $invoice_id,
            'user_data' => $input,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ])
    ]);

    // Логируем создание инвойса
    logAudit('invoice_created', $user_id, [
        'plan_id' => $plan_id,
        'amount' => $plan['price'],
        'invoice_id' => $invoice_id
    ]);

    // Отправляем уведомление в Telegram
    sendTelegramNotification(
        "🆕 Новая заявка на подключение\n" .
        "План: <b>{$plan['name']}</b>\n" .
        "Сумма: <b>" . ($plan['price'] / 100) . " ₽</b>\n" .
        "ID пользователя: <code>$user_id</code>\n" .
        "ID инвойса: <code>$invoice_id</code>\n" .
        "IP: <code>" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "</code>"
    );

    echo json_encode([
        'success' => true,
        'invoice_id' => $invoice_id,
        'amount' => $plan['price'],
        'description' => "Тариф {$plan['name']} - Global Shield",
        'plan_name' => $plan['name']
    ]);

} catch (Exception $e) {
    error_log("Invoice creation error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    logAudit('invoice_creation_error', $user_id, [
        'plan_id' => $plan_id,
        'error' => $e->getMessage()
    ]);
}
?>
