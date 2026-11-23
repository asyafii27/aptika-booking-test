# Aptika Booking

## Menjalankan Aplikasi

1. Salin `.env.example` menjadi `.env`, lalu ubah bagian database:

    ```env
    DB_CONNECTION=pgsql
    DB_HOST=127.0.0.1
    DB_PORT=5432
    DB_DATABASE=booking_db
    DB_USERNAME=postgres
    DB_PASSWORD=password_bebas
    ```

2. Jalankan container:

    ```bash
    docker compose up -d --build
    ```

3. Buat `APP_KEY` dan `JWT_SECRET` (cukup sekali):

    ```bash
    docker compose exec app php artisan key:generate
    docker compose exec app php artisan jwt:secret
    ```

API bisa diakses di `http://localhost:8080/api/v1`.

## Menjalankan Test

1. Buat database test (cukup sekali):

    ```bash
    docker compose exec db createdb -U postgres booking_db_test
    ```

2. Jalankan test:

    ```bash
    docker compose exec app php artisan test
    ```
