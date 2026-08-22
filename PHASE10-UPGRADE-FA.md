# Red Fox 1.9 — هاست اشتراکی و Pre-Certification

## Migration

```bash
php bin/migrate.php --dry-run
php bin/migrate.php
```

اگر Terminal ندارید، فایل `009_hosting_certification.sql` را بعد از 001 تا 008 در phpMyAdmin Import کنید. قبل از Import بکاپ کامل بگیرید.

## Wizard هاست

از پنل ادمین وارد «Wizard هاست» شوید:

```text
panel/hosting_setup.php
```

Wizard موارد زیر را بررسی می‌کند:

- PHP 8.2؛
- PDO MySQL، cURL، ZIP و Sodium؛
- محافظت Storage؛
- Permission فایل Secret؛
- تنظیم Key Ring و Stable/Beta؛
- دستورهای Cron مناسب هاست.

## Secret فایل‌محور

برای هاستی که امکان Environment ندارد، Secretها در این فایل PHP ذخیره می‌شوند:

```text
storage/secure.env.php
```

مشخصات:

- Permission برابر 0600؛
- نوشتن اتمیک با فایل موقت و Rename؛
- Allowlist نام متغیرها؛
- عدم نمایش مجدد مقادیر موجود؛
- نمایش Secret تازه فقط یک بار؛
- مسدودسازی کامل مسیر storage در Apache و Nginx.

Environment واقعی همیشه از فایل Hosting اولویت بیشتری دارد. این روش جای Secret Manager را نمی‌گیرد، ولی برای Shared Hosting محدود، از قرار دادن Secret در config عمومی بهتر است.

پس از ساخت Secret، با URL مستقیم زیر تست کنید و باید 403 یا 404 بگیرید:

```text
https://DOMAIN/storage/secure.env.php
```

Pre-Certification این Deny را نیز از داخل سرور بررسی می‌کند.

## Cron روی هاست

روش ارجح در بخش Cron Jobs:

```cron
* * * * * /usr/local/bin/php /home/USER/public_html/redfox/cron/cron.php
* * * * * /usr/local/bin/php /home/USER/public_html/redfox/cron/reseller_messages.php
0 3 * * * /usr/local/bin/php /home/USER/public_html/redfox/bin/maintenance.php
```

مسیر PHP ممکن است `/usr/bin/php` یا نسخه‌دار باشد. اگر فقط URL Cron دارید، Wizard پس از ساخت/چرخش Secret، URL دارای `key` را فقط یک بار نمایش می‌دهد.

## Pre-Certification

صفحه:

```text
panel/certification.php
```

بررسی‌ها:

- PHP و Extensions؛
- Migration 009؛
- Duplicate Order ID؛
- فاکتور بدون کاربر؛
- Secretهای ضروری؛
- Key Ring بروزرسانی؛
- یکپارچگی Release؛
- محافظت مستقیم config/storage/logs؛
- پنل‌های VPN، HTTPS، TLS و رمزنگاری credential؛
- فعال/غیرفعال بودن و credential درگاه‌ها؛
- صف‌های نیازمند تطبیق؛
- آخرین اجرای Cron Orchestrator.

گزارش با امتیاز ۰ تا ۱۰۰ در `certification_runs` ذخیره و به‌شکل JSON قابل دانلود است. این گزارش Read-only است و تراکنش یا سرویس واقعی ایجاد نمی‌کند.

## تست کانکتورهای ترکیبی

برای هر پنل یک Username آزمایشی موجود در همان پنل ذخیره کنید، سپس «DataUser Read-only» را اجرا کنید. خروجی فقط شامل این اطلاعات غیرحساس است:

- موفق/ناموفق؛
- نوع Status؛
- Latency؛
- وجود Data Limit؛
- وجود Expire.

Subscription و Config در گزارش چاپ نمی‌شوند. این تست هیچ حجم، زمان یا وضعیت کاربر را تغییر نمی‌دهد.

## بررسی همه درگاه‌ها

Pre-Certification وضعیت پیکربندی و اتصال TLS/DNS این خانواده‌ها را بررسی می‌کند:

- زرین‌پال؛
- آقای‌پرداخت؛
- NowPayments؛
- Plisio؛
- Tronado؛
- SwapWallet/پرداخت ریالی؛
- Tetra/FloyPay؛
- کارت‌به‌کارت.

درگاه فعال با Credential خالی یا Endpoint غیرقابل دسترس Fail می‌شود. درگاه خاموش Warning است. برای Certification نهایی همچنان یک تراکنش واقعی کم‌مبلغ یا Sandbox لازم است.

## جدول‌های جدید

```text
panel_test_accounts
certification_runs
```

## تست عملی پیشنهادی روی هاست شما

1. Wizard را اجرا و Secretهای مفقود را بسازید؛
2. دسترسی مستقیم config/storage/logs را تست کنید؛
3. Migration 009 را Import کنید؛
4. Cron CLI را تنظیم و پنج دقیقه صبر کنید؛
5. Pre-Certification بگیرید؛
6. برای هر نوع پنل یک اکانت تست Read-only معرفی کنید؛
7. تمام Failها را رفع و گزارش جدید بگیرید؛
8. سپس برای هر درگاه یک تراکنش آزمایشی انجام دهید.
