import sqlite3
import datetime

DB_NAME = 'freedomnet.db'

def init_db():
    """Создает таблицу пользователей, если она не существует."""
    conn = sqlite3.connect(DB_NAME)
    cursor = conn.cursor()
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS users (
            user_id INTEGER PRIMARY KEY,
            username TEXT,
            subscription_until TEXT
        )
    ''')
    conn.commit()
    conn.close()
    print("База данных инициализирована.")

def add_user_if_not_exists(user_id: int, username: str):
    """Добавляет нового пользователя, если его еще нет в базе."""
    conn = sqlite3.connect(DB_NAME)
    cursor = conn.cursor()
    cursor.execute("SELECT user_id FROM users WHERE user_id = ?", (user_id,))
    if cursor.fetchone() is None:
        cursor.execute("INSERT INTO users (user_id, username) VALUES (?, ?)", (user_id, username))
        conn.commit()
    conn.close()

def get_user_profile(user_id: int) -> dict:
    """Возвращает данные о подписке пользователя."""
    conn = sqlite3.connect(DB_NAME)
    cursor = conn.cursor()
    cursor.execute("SELECT subscription_until FROM users WHERE user_id = ?", (user_id,))
    result = cursor.fetchone()
    conn.close()
    if result and result[0]:
        try:
            sub_date = datetime.datetime.strptime(result[0], '%Y-%m-%d').date()
            days_left = (sub_date - datetime.date.today()).days
            return {
                'subscription_until': sub_date.strftime('%d.%m.%Y'),
                'days_left': days_left if days_left > 0 else 0
            }
        except (ValueError, TypeError):
            return None
    return None

if __name__ == '__main__':
    # Чтобы создать файл БД, запустите этот файл один раз: python database.py
    init_db()
