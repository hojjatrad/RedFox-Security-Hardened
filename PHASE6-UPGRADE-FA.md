# Red Fox 1.5 — یکپارچه‌سازی پرداخت و استقرار اتمیک

## Migration و بررسی داده

```bash
php bin/migrate.php --dry-run
php bin/migrate.php
php bin/integrity-check.php
php bin/enforce-constraints.php
```

اگر Integrity خطای duplicate برای `Payment_report.id_order`، `product.code_product` یا `botsaz.bot_token` نشان نداد، بعد از بکاپ:

```bash
php bin/enforce-constraints.php --apply
php bin/preflight.php
```

Migration 006 ایندکس‌های غیرشکننده را اضافه می‌کند. Unique Constraintها عمداً توسط ابزار جدا اعمال می‌شوند تا دیتابیس قدیمی دارای داده تکراری وسط Migration از کار نیفتد.

## پرداخت‌های منتقل‌شده به سرویس مشترک

تمام فراخوانی‌های `DirectPayment` اکنون فقط در `lib/PaymentConfirm.php` وجود دارند. مسیرهای زیر به `payment_confirm_paid` منتقل شدند:

- زرین‌پال؛
- آقای‌پرداخت؛
- IranPay/Tetra؛
- Tronado؛
- ZarinPay؛
- کارت بانکی خودکار؛
- تأیید خودکار رسید Cron؛
- Crypto Check؛
- تأیید دستی رسید توسط ادمین؛
- بررسی پرداخت داخل ربات؛
- Telegram Stars.

هیچ فایل Gateway یا Worker اجازه ندارد مستقیماً `Payment_report` را paid و سپس DirectPayment را اجرا کند.

## اصلاح مهم Telegram Stars

نسخه قدیمی در مرحله `pre_checkout_query` سفارش را تحویل می‌داد؛ در حالی‌که Pre-checkout هنوز پرداخت موفق نیست. اکنون:

1. Pre-checkout فقط مالکیت و معتبر بودن فاکتور را بررسی و پاسخ Telegram را تأیید می‌کند؛
2. تحویل فقط پس از دریافت `message.successful_payment` انجام می‌شود؛
3. Payload و payer با فاکتور تطبیق داده می‌شوند؛
4. Telegram charge ID در گزارش اثرات ثبت می‌شود؛
5. Idempotency پرداخت از تحویل دوباره جلوگیری می‌کند.

## Crypto Retry

مسیر قدیمی `cryptocheck` برای سفارش paid ولی ناقص، `DirectPayment` را دوباره اجرا می‌کرد. این Retry خودکار حذف شد. سفارش به `payment_effects.needs_reconcile` منتقل می‌شود و ادمین باید آن را بررسی کند.

## استقرار اتمیک

نمونه‌ها در `deploy/`:

- `nginx-redfox.conf`
- `php-production.ini`
- `crontab.example`
- `deploy.sh`
- `rollback.sh`

Deploy ساختار release-based دارد:

```text
/var/www/redfox/current -> /var/www/redfox/releases/TIMESTAMP
/var/www/redfox/shared/storage
/var/www/redfox/shared/logs
```

نمونه اجرا:

```bash
CONFIG_SOURCE=/secure/path/config.php \
  deploy/deploy.sh RedFox.zip /var/www/redfox
```

اسکریپت فایل را Extract، config را با Permission 640 کپی، Storage/Logs را Shared، Migration و Preflight را اجرا و سپس Symlink را اتمیک جابه‌جا می‌کند. پنج Release آخر نگه داشته می‌شوند.

Rollback فقط کد را برمی‌گرداند و Migration دیتابیس را Rollback نمی‌کند:

```bash
deploy/rollback.sh /var/www/redfox
```

## Nginx

فایل نمونه دسترسی مستقیم به `config.php`، `bin/`، `lib/`، `vendor/`، `migrations/`، `re/rx/`، Dotfile و Log/SQL را مسدود می‌کند. نام دامنه، مسیر PHP-FPM و SSL را متناسب با سرور تغییر دهید.

## Integrity Check

```bash
php bin/integrity-check.php
php bin/integrity-check.php --json
```

موارد مهم:

- Order ID تکراری؛
- Username سرویس تکراری؛
- فاکتور بدون کاربر/پنل؛
- موجودی منفی نماینده؛
- پرداخت/سرویس/پیام نیازمند تطبیق؛
- پرداخت جدید paid بدون payment_effect.

## تست Staging اجباری

1. یک پرداخت واقعی از هر Gateway؛
2. دو Callback هم‌زمان برای یک Order ID؛
3. قطع PHP بعد از Verify و بررسی needs_reconcile؛
4. Telegram Stars: اطمینان از عدم تحویل در pre_checkout و تحویل پس از successful_payment؛
5. تأیید دستی رسید هم‌زمان توسط دو ادمین؛
6. اجرای Crypto Check روی سفارش ناقص و اطمینان از عدم Retry تحویل؛
7. Deploy و Rollback کد؛
8. Nginx deny tests برای مسیرهای داخلی.
