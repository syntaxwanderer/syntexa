# Database Migration Guide

## 1. Налаштування PostgreSQL

### Перевірка встановлення
```bash
# Перевірити чи встановлений PostgreSQL
psql --version

# Якщо не встановлений, встановити:
sudo apt-get update
sudo apt-get install postgresql postgresql-contrib
```

### Створення бази даних
```bash
# Підключитися до PostgreSQL
sudo -u postgres psql

# Створити базу даних
CREATE DATABASE syntexa;

# Створити користувача (опціонально)
CREATE USER syntexa_user WITH PASSWORD 'your_password';
GRANT ALL PRIVILEGES ON DATABASE syntexa TO syntexa_user;

# Вийти
\q
```

## 2. Налаштування .env

Додайте в `.env` файл:

```env
DB_HOST=localhost
DB_PORT=5432
DB_NAME=syntexa
DB_USER=postgres
DB_PASSWORD=your_password
DB_CHARSET=utf8
DB_POOL_SIZE=10
```

## 3. Запуск міграцій

```bash
# Запустити всі міграції
bin/syntexa migrate --init

# Або запустити конкретну міграцію
bin/syntexa migrate --file packages/syntexa/user-frontend/migrations/001_create_users_table.sql --init
```

## 4. Створення користувача

```bash
bin/syntexa user:create
```

## 5. Перевірка підключення

Якщо виникають проблеми з підключенням:

1. Перевірте чи запущений PostgreSQL:
```bash
sudo systemctl status postgresql
```

2. Перевірте чи доступний порт:
```bash
sudo netstat -tlnp | grep 5432
```

3. Перевірте налаштування в `.env`

4. Перевірте права доступу користувача до бази даних

