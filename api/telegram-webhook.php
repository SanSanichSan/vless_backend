<?php
require_once 'config.php';

$telegram_token = '8424446876:AAGFCuNKrIKTEjd1yb5fkxb8MO6uuOgaey8';
$api_base = 'https://api.globalshield.ru';

$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? [];
$text = $message['text'] ?? '';
$chat_id = $message['chat']['id'] ?? '';
$user_id = $message['from']['id'] ?? '';

// Логируем входящее сообщение
logAudit('telegram_webhook_received', $user_id, [
    'text' => $text,
    'chat_id' => $chat_id
]);

// Обработка команды /start с параметром
if (strpos($text, '/start pay_') === 0) {
    $invoice_id = str_replace('/start pay_', '', $text);
    processPaymentCommand($chat_id, $user_id, $invoice_id);
} 
// Обработка команды /pay
elseif ($text === '/pay') {
    askForInvoiceId($chat_id);
}
// Обработка команды /help
elseif ($text === '/help') {
    sendHelpMessage($chat_id);
}
// Обработка команды /start без параметров
elseif ($text === '/start') {
    sendWelcomeMessage($chat_id);
}
// Если сообщение похоже на invoice_id
elseif (preg_match('/^inv_[a-zA-Z0-9]+$/', $text)) {
    processPaymentCommand($chat_id, $user_id, $text);
}

function sendMessage($chat_id, $text, $reply_markup = null) {
    global $telegram_token;
    $url = "https://api.telegram.org/bot{$telegram_token}/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    if ($reply_markup) {
        $data['reply_markup'] = json_encode($reply_markup);
    }
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    return $result;
}

function processPaymentCommand($chat_id, $user_id, $invoice_id) {
    global $api_base;
    
    // Проверяем статус платежа
    $payment_status = checkPaymentStatus($invoice_id);
    
    if ($payment_status['success']) {
        $status = $payment_status['payment']['status'];
        $plan_id = $payment_status['payment']['plan_id'];
        $amount = $payment_status['payment']['amount'];
        
        if ($status === 'paid') {
            $message = "
✅ <b>Платеж подтвержден!</b>

Ваш аккаунт активирован!
Тариф: <b>{$plan_id}</b>
Сумма: <b>" . ($amount / 100) . " ₽</b>

Для настройки подключения:
1. Перейдите в панель: https://panel.globalshield.ru
2. Создайте конфигурацию
3. Настройте на устройствах

Нужна помощь? @Global_Shield_support
            ";
        } else {
            $message = "
💰 <b>Ожидание оплаты</b>

ID счета: <code>{$invoice_id}</code>
Тариф: <b>{$plan_id}</b>
Сумма: <b>" . ($amount / 100) . " ₽</b>
Статус: <b>{$status}</b>

Для оплаты используйте один из способов:
• Банковская карта (РФ)
• Криптовалюта (USDT, BTC)
• Другие методы

👇 Выберите способ оплаты:
            ";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '💳 Банковская карта', 'callback_data' => 'pay_card_' . $invoice_id],
                        ['text' => '₿ Криптовалюта', 'callback_data' => 'pay_crypto_' . $invoice_id]
                    ],
                    [
                        ['text' => '🔄 Проверить статус', 'callback_data' => 'check_status_' . $invoice_id]
                    ]
                ]
            ];
            
            sendMessage($chat_id, $message, $keyboard);
            return;
        }
    } else {
        $message = "
❌ <b>Счет не найден</b>

Проверьте правильность ID счета: <code>{$invoice_id}</code>

Если вы не создавали счет:
1. Откройте Mini App
2. Выберите тариф  
3. Скопируйте ID счета
4. Вернитесь сюда

Или нажмите кнопку оплаты в Mini App.
        ";
    }
    
    sendMessage($chat_id, $message);
}

function checkPaymentStatus($invoice_id) {
    global $api_base;
    
    $url = $api_base . '/check-payment.php?invoice_id=' . urlencode($invoice_id);
    $response = file_get_contents($url);
    return json_decode($response, true);
}

function askForInvoiceId($chat_id) {
    $message = "
📋 <b>Оплата подписки Global Shield</b>

Для оплаты введите ID счета, который вы получили в Mini App.

Пример ID счета: <code>inv_abc123def456</code>

Как получить ID счета:
1. Откройте Mini App
2. Выберите тариф
3. Скопируйте ID счета
4. Вернитесь сюда и введите его

Или просто нажмите кнопку оплаты в Mini App для автоматического создания счета.
    ";
    
    sendMessage($chat_id, $message);
}

function sendHelpMessage($chat_id) {
    $message = "
🤖 <b>Global Shield Bot - Помощь</b>

<b>Команды:</b>
/start - Начало работы
/pay - Оплатить подписку  
/help - Эта справка

<b>Как оплатить:</b>
1. Получите ID счета в Mini App
2. Отправьте его боту или используйте /pay
3. Выберите способ оплаты
4. Следуйте инструкциям

<b>Ссылки:</b>
• Mini App: https://t.me/Global_Shield_bot/globalshield
• Панель управления: https://panel.globalshield.ru
• Поддержка: @Global_Shield_support
    ";
    
    sendMessage($chat_id, $message);
}

function sendWelcomeMessage($chat_id) {
    $message = "
🛡️ <b>Добро пожаловать в Global Shield!</b>

Я помогу вам оплатить подписку на VPN сервис.

<b>Для начала работы:</b>
1. Откройте Mini App для выбора тарифа
2. Получите ID счета для оплаты
3. Вернитесь сюда для завершения оплаты

<b>Быстрые команды:</b>
/pay - Оплатить подписку
/help - Помощь и инструкции

🌐 <b>Ссылки:</b>
Mini App: https://t.me/Global_Shield_bot/globalshield
Поддержка: @Global_Shield_support
    ";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🚀 Открыть Mini App', 'url' => 'https://t.me/Global_Shield_bot/globalshield']
            ],
            [
                ['text' => '💳 Оплатить подписку', 'callback_data' => 'start_payment']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard);
}

// Отвечаем Telegram, что вебхук обработан
http_response_code(200);
echo 'OK';
?>
