# Налаштування PostgreSQL для Syntexa

## Зміна пароля користувача postgres

### Варіант 1: Через psql (рекомендовано)

```bash
# Підключитися до PostgreSQL як користувач postgres
sudo -u postgres psql

# В консолі PostgreSQL виконайте:
ALTER USER postgres PASSWORD 'your_new_password';

# Або створити нового користувача:
CREATE USER syntexa_user WITH PASSWORD 'your_password';
CREATE DATABASE syntexa OWNER syntexa_user;
GRANT ALL PRIVILEGES ON DATABASE syntexa TO syntexa_user;

# Вийти з psql
\q
```

### Варіант 2: Через команду в одному рядку

```bash
# Змінити пароль користувача postgres
sudo -u postgres psql -c "ALTER USER postgres PASSWORD 'your_new_password';"

# Або створити нового користувача та базу даних
sudo -u postgres psql -c "CREATE USER syntexa_user WITH PASSWORD 'your_password';"
sudo -u postgres psql -c "CREATE DATABASE syntexa OWNER syntexa_user;"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE syntexa TO syntexa_user;"
```

### Варіант 3: Якщо не можете використати sudo

Якщо у вас немає прав sudo, можна:

1. **Підключитися без пароля (peer authentication)**:
```bash
# Якщо ваш системний користувач має доступ
psql -U postgres -d postgres
```

2. **Використати пароль за замовчуванням**:
   - Деякі дистрибутиви встановлюють PostgreSQL з пустим паролем
   - Спробуйте підключитися без пароля або з паролем `postgres`

3. **Створити локального користувача PostgreSQL**:
```bash
# Створити користувача з вашим системним ім'ям
createuser -s $USER
createdb syntexa
```

## Перевірка підключення

Після налаштування пароля, перевірте підключення:

```bash
# Тест підключення
psql -U postgres -d postgres -h localhost

# Або з паролем
PGPASSWORD=your_password psql -U postgres -d syntexa -h localhost
```

## Налаштування .env

Після налаштування PostgreSQL, оновіть `.env`:

```env
DB_HOST=localhost
DB_PORT=5432
DB_NAME=syntexa
DB_USER=postgres
DB_PASSWORD=your_new_password
DB_CHARSET=utf8
DB_POOL_SIZE=10
```

## Якщо PostgreSQL не запущений

```bash
# Запустити PostgreSQL
sudo systemctl start postgresql

# Або
sudo service postgresql start

# Перевірити статус
sudo systemctl status postgresql
```

## Дозволити підключення з localhost

Якщо виникають проблеми з підключенням, перевірте `pg_hba.conf`:

```bash
# Знайти файл конфігурації
sudo find /etc -name pg_hba.conf

# Відредагувати (зазвичай /etc/postgresql/*/main/pg_hba.conf)
# Додати рядок:
host    all             all             127.0.0.1/32            md5

# Перезапустити PostgreSQL
sudo systemctl restart postgresql
```

## Альтернатива: Використати Docker

Якщо налаштування локального PostgreSQL складно, можна використати Docker:

```bash
docker run --name syntexa-postgres \
  -e POSTGRES_PASSWORD=postgres \
  -e POSTGRES_DB=syntexa \
  -p 5432:5432 \
  -d postgres:16
```

Тоді в `.env`:
```env
DB_HOST=localhost
DB_PORT=5432
DB_NAME=syntexa
DB_USER=postgres
DB_PASSWORD=postgres
```

