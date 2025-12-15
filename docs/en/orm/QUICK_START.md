# Швидкий старт PostgreSQL для Syntexa

## Автоматичне налаштування (рекомендовано)

```bash
bin/setup-postgres.sh
```

Скрипт автоматично:
- Перевірить чи запущений PostgreSQL
- Створить базу даних
- Оновить `.env` файл
- Запустить міграції

## Ручне налаштування

### 1. Змінити пароль користувача postgres

```bash
sudo -u postgres psql
```

В консолі PostgreSQL:
```sql
ALTER USER postgres PASSWORD 'postgres';
\q
```

### 2. Створити базу даних

```bash
sudo -u postgres psql -c "CREATE DATABASE syntexa;"
```

### 3. Оновити .env

Відредагуйте `.env`:
```env
DB_HOST=localhost
DB_PORT=5432
DB_NAME=syntexa
DB_USER=postgres
DB_PASSWORD=postgres
DB_CHARSET=utf8
DB_POOL_SIZE=10
```

### 4. Запустити міграції

```bash
bin/syntexa migrate --init
```

### 5. Створити користувача

```bash
bin/syntexa user:create
```

## Перевірка підключення

```bash
# Тест підключення
psql -U postgres -d syntexa -h localhost
```

Якщо запитує пароль, введіть той, який встановили в `.env`.

## Проблеми?

Дивіться детальні інструкції в `POSTGRESQL_SETUP.md`

