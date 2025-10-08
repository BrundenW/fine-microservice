# Fine Microservice

A minimal RESTful microservice for managing fines, built with PHP 8.4+ and Slim 4, backed by PostgreSQL. It includes simple domain rules such as overdue penalties, early payment discount, and a frequent-offender surcharge.

- Tech: PHP 8, Slim 4, PHP-DI, PostgreSQL 16, Docker/Docker Compose
- Exposed port: 8080 (container -> host)

## Features
- CRUD for fines
- Pagination on list endpoint (limit/offset)
- Business rules:
  - Overdue penalty: any fine older than 30 days and not already overdue/paid becomes `overdue` and gets +20% applied when endpoints are accessed.
  - Early payment discount: if paid within 14 days of `date_issued`, a 10% discount is applied upon marking as paid.
  - Frequent offender surcharge: when creating a fine, if the offender has 3 or more existing unpaid fines, +50 is added to the new fine amount.

## Quick start (Docker)

Prerequisites:
- Docker and Docker Compose

1) Create a `.env` file in the project root with database credentials (used by docker-compose):

```
POSTGRES_DB=fines
POSTGRES_USER=postgres
POSTGRES_PASSWORD=postgres
```

2) Start the stack:
- With Makefile (recommended):
  - `make up` to build and start
  - `make logs` to follow logs
- Or with Docker Compose directly:
  - `docker compose up -d --build`

3) Wait for the database to become healthy. The app will be available at:

- Base URL: http://localhost:8080

The database schema is initialized automatically from db/init/*.sql.

4) Stop the stack:
- `make down` or `docker compose down`

## Configuration
The app reads database settings from environment variables (wired via docker-compose):
- DB_HOST (default: db)
- DB_PORT (default: 5432)
- DB_NAME
- DB_USER
- DB_PASSWORD

You can also use compatible Postgres variables (POSTGRES_HOST/PORT/DB/USER/PASSWORD).

## API
All responses are JSON. Content-Type: application/json.

Entity shape (table: `fines`):
- fine_id: number (auto)
- offender_name: string
- offence_type: string
- fine_amount: number (2 decimals)
- date_issued: date (YYYY-MM-DD)
- status: enum [unpaid, paid, overdue]

### List fines
GET /fines?limit={1..100}&offset={0..}

Example:
```
curl "http://localhost:8080/fines?limit=10&offset=0"
```

Notes:
- Triggers overdue penalty rule before returning data.

### Get fine by id
GET /fines/{id}
```
curl http://localhost:8080/fines/1
```

### Create fine
POST /fines
Body fields (JSON): offender_name, offence_type, fine_amount, date_issued, [status]
```
curl -X POST http://localhost:8080/fines \
  -H "Content-Type: application/json" \
  -d '{
        "offender_name": "John Doe",
        "offence_type": "Speeding",
        "fine_amount": 100.00,
        "date_issued": "2025-10-01"
      }'
```
- Applies frequent offender surcharge if offender has >=3 unpaid fines.

### Update fine (full)
PUT /fines/{id}
Body must include all fields: offender_name, offence_type, fine_amount, date_issued, status
```
curl -X PUT http://localhost:8080/fines/1 \
  -H "Content-Type: application/json" \
  -d '{
        "offender_name": "John Doe",
        "offence_type": "Parking",
        "fine_amount": 80.00,
        "date_issued": "2025-09-15",
        "status": "unpaid"
      }'
```

### Partial update
PATCH /fines/{id}
Body: any subset of fields.
```
curl -X PATCH http://localhost:8080/fines/1 \
  -H "Content-Type: application/json" \
  -d '{"status": "overdue"}'
```

### Delete fine
DELETE /fines/{id}
```
curl -X DELETE http://localhost:8080/fines/1
```

### Mark as paid
PATCH /fines/{id}/paid
```
curl -X PATCH http://localhost:8080/fines/1/paid
```
Behavior:
- If within 14 days from `date_issued`, 10% discount is applied.
- 204 on success, 404 if not found, 409 if already paid or unable to change state.

## Local (non-Docker) development
If you prefer running PHP locally:
- PHP 8.4+ with pdo_pgsql extension
- Composer
- PostgreSQL accessible via env vars mentioned above

Install dependencies:
```
composer install
```
Run dev server:
```
php -S 0.0.0.0:8080 -t public public/index.php
```

## Project layout
- public/index.php: Slim app bootstrap, routes
- src/Controller/FineController.php: Handlers and business rules
- db/init/*.sql: Database schema init (executed on first Postgres start)
- Dockerfile, docker-compose.yml: Containerization
- Makefile: Convenience commands (up/down/build/logs/ps)

## Database migrations and seed
- Automatic on first start: The database schema and seed run automatically from db/init/*.sql when the Postgres container initializes for the first time.
- Idempotent: The seed inserts data only if the fines table is empty, so re-running it is safe.

## Troubleshooting
- Port 8080 already in use: change the mapped port in docker-compose.yml under app. Example: "9080:8080".
- Database connection errors: ensure the db service is healthy (`docker compose ps`), env vars in .env are correct, and the network is up.
- Curl 500 errors: check application logs with `make logs` or `docker compose logs -f`.