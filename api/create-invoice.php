// В create-invoice.php после создания инвойса добавить:

// Создаем платеж в ЮKassa
$yookassa_payment = createYookassaPayment($plan['price'], $invoice_id, $user_id, $plan_id);

// Обновляем payment_data с ID платежа
$stmt = $conn->prepare("UPDATE payments SET payment_data = payment_data || :yookassa_data WHERE payment_data->>'invoice_id' = :invoice_id");
$stmt->execute([
    'yookassa_data' => json_encode(['yookassa_payment_id' => $yookassa_payment['id'], 'confirmation_url' => $yookassa_payment['confirmation']['confirmation_url']]),
    'invoice_id' => $invoice_id
]);

// Возвращаем URL для оплаты
echo json_encode([
    'success' => true,
    'invoice_id' => $invoice_id,
    'amount' => $plan['price'],
    'description' => "Тариф {$plan['name']} - Global Shield",
    'confirmation_url' => $yookassa_payment['confirmation']['confirmation_url'],
    'payment_methods' => ['card', 'crypto', 'qiwi']
]);

function createYookassaPayment($amount, $invoice_id, $user_id, $plan_id) {
    $shop_id = 'your_shop_id'; // Получите в кабинете ЮKassa
    $secret_key = 'your_secret_key'; // Получите в кабинете ЮKassa

    $url = 'https://api.yookassa.ru/v3/payments';
    $data = [
        'amount' => [
            'value' => number_format($amount / 100, 2, '.', ''),
            'currency' => 'RUB'
        ],
        'payment_method_data' => [
            'type' => 'bank_card'
        ],
        'confirmation' => [
            'type' => 'redirect',
            'return_url' => 'https://t.me/Global_Shield_bot'
        ],
        'description' => "Тариф {$plan_id} - Global Shield",
        'metadata' => [
            'invoice_id' => $invoice_id,
            'user_id' => $user_id,
            'plan_id' => $plan_id
        ],
        'capture' => true
    ];

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode("{$shop_id}:{$secret_key}"),
                'Idempotence-Key: ' . uniqid()
            ],
            'content' => json_encode($data)
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    return json_decode($response, true);
}
