-- Hanya dijalankan saat volume MySQL pertama kali dibuat.
-- Database terpisah untuk php artisan test, supaya data utama tidak tersentuh.
CREATE DATABASE IF NOT EXISTS ttmpos_test;
GRANT ALL PRIVILEGES ON ttmpos_test.* TO 'ttm'@'%';
FLUSH PRIVILEGES;
