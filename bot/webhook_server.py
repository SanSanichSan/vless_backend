from flask import Flask, request
import requests
import datetime
import sqlite3

import config
import database

app = Flask(__name__)

def send_telegram_message(user_id, text):
    """Отправляет сообщение пользователю от имени бота."""
    url = f"https://api.telegram.org/bot{config.BOT_TOKEN}/sendMessage"
    payload = {
        'chat_id': user_id,
        'text': text,
        'parse_mode': 'Markdown'
    }
    try:
        requests.post(url, json=payload)
    except Exception as e:
        print(f"Ошибка отправки сообщения пользователю {user_id}: {e}")

@app.route('/yookassa_webhook', methods=['POST'])
def yookassa_webhook():
    event_json = request.json
    try:
        # Проверяем, что это уведомление об успешном платеже
        if event_json['event'] == 'payment.succeeded':
            payment_info = event_json['object']
            metadata = payment_info['metadata']

            user_id = int(metadata['user_id'])
            plan_id = metadata['plan_id']

            print(f"Успешная оплата от {user_id} по тарифу {plan_id}")

            # Продлеваем подписку в нашей БД
            plan_days = config.PLANS[plan_id][2]
            conn = sqlite3.connect(database.DB_NAME)
            cursor = conn.cursor()

            cursor.execute("SELECT subscription_until FROM users WHERE user_id = ?", (user_id,))
            result = cursor.fetchone()
            start_date = datetime.date.today()
            if result and result[0]:
                sub_date = datetime.datetime.strptime(result[0], '%Y-%m-%d').date()
                if sub_date > start_date:
                    start_date = sub_date

            new_expiry_date = start_date + datetime.timedelta(days=plan_days)
            cursor.execute("UPDATE users SET subscription_until = ? WHERE user_id = ?", (new_expiry_date.isoformat(), user_id))
            conn.commit()
            conn.close()

            # Создаем или обновляем ключ через VPN API
            api_headers = {'Authorization': f'Bearer {config.VPN_API_KEY}'}
            api_payload = {'telegram_user_id': user_id, 'plan_days': plan_days}

            try:
                # Пытаемся продлить. Если не вышло - создаем.
                response = requests.put(f"{config.VPN_API_URL}/users/{user_id}/subscription", headers=api_headers, json={'plan_days': plan_days})
                if not response.ok:
                     response = requests.post(f"{config.VPN_API_URL}/users", headers=api_headers, json=api_payload)

                response.raise_for_status()
                vpn_key_response = requests.get(f"{config.VPN_API_URL}/users/{user_id}", headers=api_headers)
                vpn_key = vpn_key_response.json()['data']['vpn_key']

                success_text = (f"✅ Оплата прошла успешно!\n\n"
                                f"Ваша подписка продлена до **{new_expiry_date.strftime('%d.%m.%Y')}**.\n\n"
                                f"Используйте этот ключ для подключения:\n`{vpn_key}`")
                send_telegram_message(user_id, success_text)

            except Exception as e:
                print(f"Ошибка при работе с VPN API для пользователя {user_id}: {e}")
                error_text = "Произошла ошибка при генерации ключа. Пожалуйста, срочно свяжитесь с поддержкой @your_support_account, мы все решим!"
                send_telegram_message(user_id, error_text)

        return {"status": "success"}, 200

    except Exception as e:
        print(f"Ошибка обработки веб-уведомления: {e}")
        return {"status": "error"}, 400
```

### **Шаг 4: Настройка и запуск**

1.  **Настройте ЮKassa:**
    * В личном кабинете перейдите в «Интеграция» -> «HTTP-уведомления».
    * В поле **URL для уведомлений** вставьте адрес вашего сервера: `http://ВАШ_ДОМЕН_ИЛИ_IP:5000/yookassa_webhook`.
    * Отметьте галочкой событие `payment.succeeded`.

2.  **Запустите сервисы:**
    * **Веб-сервер:**
        ```bash
        gunicorn --bind 0.0.0.0:5000 webhook_server:app
        ```
    * **Telegram-бот (в другом окне терминала):**
        ```bash
        python bot.py


