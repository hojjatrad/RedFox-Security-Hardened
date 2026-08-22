# Red Fox 1.4 — آمادگی Production

## ۱) Migration

```bash
php bin/migrate.php --dry-run
php bin/migrate.php
php bin/preflight.php
```

Migration جدید `005_production_operations.sql` جداول `payment_effects` و `cron_job_runs` را ایجاد می‌کند.

## ۲) متغیرهای محیطی

حداقل این Secretها را در PHP-FPM/Apache و محیط CLI قرار دهید:

```text
REDFOX_CRON_SECRET=<random 32+ chars>
REDFOX_DIAG_SECRET=<different random 32+ chars>
REDFOX_HEALTH_TOKEN=<different random 32+ chars>
REDFOX_MASTER_KEY=<64 hex chars from openssl rand -hex 32>
```

توکن ربات تلگرام دیگر نباید به‌عنوان رمز Cron یا Diagnostics استفاده شود.

## ۳) تغییر مهم Cron HTTP

تمام Cronهای عملیاتی اکنون یکی از این شرایط را لازم دارند:

- اجرا با PHP CLI؛ یا
- Header به‌شکل `X-Cron-Secret: SECRET`؛ یا
- درخواست loopback از Orchestrator داخلی با `X-Cron-Source: cron-orchestrator`.

روش پیشنهادی:

```cron
* * * * * /usr/bin/php /var/www/html/redfox/cron/cron.php >/dev/null 2>&1
* * * * * /usr/bin/php /var/www/html/redfox/cron/reseller_messages.php >/dev/null 2>&1
0 3 * * * /usr/bin/php /var/www/html/redfox/bin/maintenance.php >/dev/null 2>&1
```

Cronهای HTTP قدیمی بدون Secret پس از ارتقا 404 می‌شوند؛ تنظیمات Cron هاست را هم‌زمان اصلاح کنید.

## ۴) Idempotency اثرات پرداخت

`payment_confirm_paid` اکنون پیش از تغییر وضعیت فاکتور یک رکورد یکتا در `payment_effects` می‌گیرد. حالت‌ها:

- `processing`: در حال اجرا؛
- `completed`: همه اثرات مسیر پرداخت تکمیل شده؛
- `needs_reconcile`: اجرای دوباره خودکار خطرناک است؛
- `failed`: فاکتور قابل پردازش نبوده است.

اگر پردازش بعد از DirectPayment/Cashback و قبل از ثبت نهایی قطع شود، callback بعدی اثرات را دوباره اجرا نمی‌کند و وضعیت `needs_reconcile` می‌شود. ادمین در `panel/payment_effects.php` پس از بررسی کیف پول، سرویس، فاکتور و درگاه، آن را دستی Resolve می‌کند.

این محافظ برای مسیرهایی اعمال می‌شود که از `payment_confirm_paid` استفاده می‌کنند: NowPayments، Plisio، IranPay مربوطه و workerهای متصل. مسیرهای بسیار قدیمی که مستقیماً `Payment_report` را تغییر می‌دهند باید در Staging جداگانه تست و در ادامه به سرویس مشترک منتقل شوند.

## ۵) Health و Readiness

- `/health.php`: Liveness سبک، بدون دیتابیس؛
- `/ready.php`: بررسی DB، Migration 005، Storage و Master Key.

جزئیات Readiness فقط با Header زیر نمایش داده می‌شود:

```text
X-Health-Token: REDFOX_HEALTH_TOKEN
```

## ۶) مرکز عملیات

`panel/operations.php` وضعیت زیر را نمایش می‌دهد:

- اتصال دیتابیس و Migration؛
- دسترسی نوشتن Storage/Logs؛
- فضای دیسک؛
- پرداخت، سرویس و پیام نیازمند تطبیق؛
- آخرین اجرای Cronها و Fatal Errorها.

## ۷) Preflight و نگهداری

```bash
php bin/preflight.php
php bin/maintenance.php
```

Preflight در صورت ایراد Critical کد خروجی 1 می‌دهد و برای CI/Deployment مناسب است. Maintenance لاگ قدیمی را فشرده/حذف و فایل‌های Rate Limit منقضی را پاک می‌کند.

## ۸) سخت‌سازی وب

دسترسی مستقیم HTTP به مسیرهای زیر در Apache مسدود شده است:

```text
bin/
lib/
vendor/
migrations/
re/rx/
config.php
```

اگر Nginx استفاده می‌کنید باید معادل این قوانین را در کانفیگ Nginx اعمال کنید؛ `.htaccess` روی Nginx اثری ندارد.

## تست Staging الزامی

1. تغییر همه Cronهای HTTP قدیمی و بررسی 404 بدون Secret؛
2. اجرای یک پرداخت واقعی هر درگاه و بررسی `payment_effects=completed`؛
3. شبیه‌سازی قطع PHP و بررسی `needs_reconcile`؛
4. بررسی `/ready.php` قبل و بعد Migration؛
5. اجرای Preflight با همان کاربر Unix که PHP-FPM و Cron استفاده می‌کنند؛
6. بررسی Cron telemetry حداقل ۲۴ ساعت؛
7. بررسی Log Rotation و فضای دیسک.
