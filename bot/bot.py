import requests
import uuid
from telegram import Update, InlineKeyboardButton, InlineKeyboardMarkup
from telegram.ext import Application, CommandHandler, CallbackQueryHandler, ContextTypes
from yookassa import Configuration, Payment

import config
import database

# --- Главное меню ---
def get_main_menu():
    keyboard = [
        [InlineKeyboardButton("Тарифы и оплата 💳", callback_data='pricing')],
        [InlineKeyboardButton("Мой профиль 👤", callback_data='profile')],
        [InlineKeyboardButton("Помощь ❓", callback_data='help')]
    ]
    return InlineKeyboardMarkup(keyboard)

# --- Обработчики команд ---
async def start(update: Update, context: ContextTypes.DEFAULT_TYPE):
    user = update.effective_user
    database.add_user_if_not_exists(user.id, user.username)
    await update.message.reply_text(
        f"👋 Привет, {user.first_name}! Я бот сервиса Freedom Net.\n\n"
        "Здесь вы можете оформить и управлять своей подпиской.",
        reply_markup=get_main_menu()
    )

async def button_handler(update: Update, context: ContextTypes.DEFAULT_TYPE):
    query = update.callback_query
    await query.answer()
    user = query.from_user

    # --- Обработка кнопок главного меню ---
    if query.data == 'main_menu':
        await query.edit_message_text(
            "Главное меню:",
            reply_markup=get_main_menu()
        )

    # --- Показ тарифов ---
    elif query.data == 'pricing':
        keyboard = []
        for plan_id, plan_details in config.PLANS.items():
            text = f"{plan_details[0]} - {plan_details[1]} ₽"
            keyboard.append([InlineKeyboardButton(text, callback_data=f"buy_{plan_id}")])
        keyboard.append([InlineKeyboardButton("⬅️ Назад", callback_data='main_menu')])
        await query.edit_message_text(
            text="Выберите подходящий план:",
            reply_markup=InlineKeyboardMarkup(keyboard)
        )

    # --- Показ профиля ---
    elif query.data == 'profile':
        profile_data = database.get_user_profile(user.id)
        api_headers = {'Authorization': f'Bearer {config.VPN_API_KEY}'}
        try:
            response = requests.get(f"{config.VPN_API_URL}/users/{user.id}", headers=api_headers)
            vpn_key = response.json()['data']['vpn_key'] if response.ok else "ключ не найден"
        except Exception:
            vpn_key = "не удалось получить ключ"

        if profile_data and profile_data['days_left'] > 0:
            text = (f"👤 **Ваш профиль**\n\n"
                    f"**Подписка активна до:** {profile_data['subscription_until']}\n"
                    f"**Осталось дней:** {profile_data['days_left']}\n\n"
                    f"🔑 **Ваш ключ доступа:**\n`{vpn_key}`")
        else:
            text = "У вас нет активной подписки."

        await query.edit_message_text(text, reply_markup=InlineKeyboardMarkup([[InlineKeyboardButton("⬅️ Назад", callback_data='main_menu')]]), parse_mode='Markdown')

    # --- Помощь ---
    elif query.data == 'help':
        text = "Если у вас возникли вопросы, напишите нашей поддержке: @your_support_account"
        await query.edit_message_text(text, reply_markup=InlineKeyboardMarkup([[InlineKeyboardButton("⬅️ Назад", callback_data='main_menu')]]))

    # --- Создание платежа в ЮKassa ---
    elif query.data.startswith('buy_'):
        plan_id = query.data.split('_')[1]
        plan_name, plan_price, plan_days = config.PLANS[plan_id]

        # Настраиваем API ЮKassa
        Configuration.account_id = config.YOOKASSA_SHOP_ID
        Configuration.secret_key = config.YOOKASSA_SECRET_KEY

        # Создаем платеж
        idempotence_key = str(uuid.uuid4())
        payment = Payment.create({
            "amount": {
                "value": str(plan_price),
                "currency": "RUB"
            },
            "confirmation": {
                "type": "redirect",
                "return_url": f"https://t.me/{context.bot.username}" # Куда вернется пользователь после оплаты
            },
            "capture": True,
            "description": f"Оплата подписки Freedom Net: {plan_name}",
            "metadata": {
                'user_id': user.id,
                'username': user.username,
                'plan_id': plan_id
            }
        }, idempotence_key)

        payment_url = payment.confirmation.confirmation_url

        await query.edit_message_text(
            f"Вы выбрали: **{plan_name}**\nСумма к оплате: **{plan_price} ₽**\n\n"
            "Нажмите на кнопку ниже, чтобы перейти к безопасной оплате.",
            reply_markup=InlineKeyboardMarkup([
                [InlineKeyboardButton("✅ Оплатить", url=payment_url)],
                [InlineKeyboardButton("⬅️ Назад к тарифам", callback_data='pricing')]
            ]),
            parse_mode='Markdown'
        )

def main():
    application = Application.builder().token(config.BOT_TOKEN).build()
    application.add_handler(CommandHandler("start", start))
    application.add_handler(CallbackQueryHandler(button_handler))
    print("Бот запущен...")
    application.run_polling()

if __name__ == '__main__':
    main()

