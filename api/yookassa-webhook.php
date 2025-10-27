<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

// Логируем вебхук от ЮKassa
logAudit('yookassa_webhook_received', null, $input);

// Проверяем подпись (реализуйте позже)
// if (!verifyYookassaSignature($input)) {
//     http_response_code(401);
//     exit();
// }

if (isset($input['object']['id'])) {
    $payment_id = $input['object']['id'];
    $status = $input['object']['status'];
    $metadata = $input['object']['metadata'] ?? [];
    
    $invoice_id = $metadata['invoice_id'] ?? '';
    
    if ($invoice_id && $status === 'succeeded') {
        // Обновляем статус платежа
        $conn = getDBConnection();
        $stmt = $conn->prepare("UPDATE payments SET status = 'paid' WHERE payment_data->>'invoice_id' = :invoice_id");
        $stmt->execute(['invoice_id' => $invoice_id]);
        
        // Активируем подписку
        $stmt = $conn->prepare("SELECT * FROM payments WHERE payment_data->>'invoice_id' = :invoice_id");
        $stmt->execute(['invoice_id' => $invoice_id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($payment) {
            activateRemnawaveSubscription($payment['user_id'], $payment['plan_id']);
            
            sendTelegramNotification(
                "✅ Платеж подтвержден (ЮKassa)\n" .
                "Инвойс: <code>$invoice_id</code>\n" .
                "Платеж: <code>$payment_id</code>\n" .
                "Пользователь: <code>{$payment['user_id']}</code>\n" .
                "Тариф: <b>{$payment['plan_id']}</b>\n" .
                "Сумма: <b>" . ($payment['amount'] / 100) . " ₽</b>"
            );
        }
    }
}

http_response_code(200);
echo 'OK';

function activateRemnawaveSubscription($user_id, $plan_id) {
    // TODO: Интеграция с Remnawave API
    // Пока обновляем нашу БД
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        INSERT INTO users (telegram_id, subscription_end, max_devices, created_at) 
        VALUES (:telegram_id, NOW() + INTERVAL '1 month', :max_devices, NOW())
        ON CONFLICT (telegram_id) 
        DO UPDATE SET subscription_end = NOW() + INTERVAL '1 month', max_devices = :max_devices
    ");
    
    $max_devices = getMaxDevicesForPlan($plan_id);
    $stmt->execute([
        'telegram_id' => $user_id,
        'max_devices' => $max_devices
    ]);
    
    logAudit('subscription_activated', $user_id, [
        'plan_id' => $plan_id,
        'max_devices' => $max_devices
    ]);
}

function getMaxDevicesForPlan($plan_id) {
    $devices = [
        'start_99' => 5,
        'flex_199' => 3,
        'optimal_499' => 5,
        'max_799' => 7,
        'corporate' => 50,
        'dedicated_node' => 25
    ];
    
    return $devices[$plan_id] ?? 5;
}
?>
