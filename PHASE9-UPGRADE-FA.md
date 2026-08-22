# Red Fox 1.8 — Staging Harness، CI و یکپارچگی Release

## تنظیمات از Environment

بدون حذف سازگاری config.php، در صورت خالی‌بودن مقادیر فایل، این Environmentها خوانده می‌شوند:

```text
REDFOX_DB_HOST
REDFOX_DB_NAME
REDFOX_DB_USER
REDFOX_DB_PASSWORD
REDFOX_BOT_TOKEN
REDFOX_BOT_USERNAME
REDFOX_ADMIN_ID
REDFOX_DOMAIN
REDFOX_TELEGRAM_API_BASE
```

در Production، Telegram API Base را تنظیم نکنید تا مقدار رسمی `https://api.telegram.org` استفاده شود. مقدار سفارشی فقط برای Mock/Staging یا Bot API Server مورداعتماد است.

## هشدار ورود دستگاه جدید

هنگام ساخت نشست نماینده، ترکیب IP و Hash مرورگر با نشست‌های قبلی مقایسه می‌شود. برای دستگاه/IP جدید، هشدار تلگرامی شامل IP، زمان و راهنمای لغو نشست ارسال می‌شود.

## یکپارچگی Release پس از نصب

Updater امضاشده هنگام Stage دو فایل فقط‌خواندنی می‌سازد:

```text
.release-manifest.json
.release-signature.txt
```

ابزار زیر امضا و SHA-256 همه فایل‌های Release را دوباره بررسی می‌کند:

```bash
php bin/verify-release.php
```

نتایج:

- `OK`: Release امضاشده و بدون تغییر؛
- Exit 2: فایل حذف یا تغییر کرده یا امضا/کانال نامعتبر است؛
- Exit 3: نصب اولیه/دستی و فاقد Release Manifest است.

`bin/preflight.php` نیز این بررسی را انجام می‌دهد. Release دستی به‌صورت Warning و Release امضاشده دستکاری‌شده به‌صورت Critical گزارش می‌شود.

فایل‌های Runtime مثل config، storage و logs در Manifest نیستند و تغییر آن‌ها Tamper محسوب نمی‌شود.

## محیط Staging با Docker

مسیر:

```text
deploy/staging/
```

اجزا:

- PHP 8.4 + Apache؛
- MySQL 8؛
- Mock Telegram/Panel/Gateway؛
- Environment کامل آزمایشی؛
- اجرای table.php و Migration هنگام Startup؛
- Smoke Test برای Health، Ready، Preflight و Integrity.

اجرا:

```bash
cd deploy/staging
docker compose up -d --build
./smoke.sh
```

پاک‌سازی کامل دیتابیس آزمایشی:

```bash
docker compose down -v
```

Secretهای داخل Compose فقط تستی‌اند و برای Production ممنوع هستند.

## Mock Server

`mock-server.py` پاسخ‌های کنترل‌شده برای این مسیرها می‌دهد:

- Telegram getMe/sendMessage/setWebhook؛
- Panel token؛
- دریافت کاربر و Subscription؛
- Modify/Delete؛
- Verify عمومی Gateway.

هدف آن Smoke/Integration اولیه است، نه شبیه‌سازی کامل رفتار همه ارائه‌دهندگان.

## CI

Workflow جدید:

```text
.github/workflows/ci.yml
```

مراحل:

- PHP 8.4 و Extensions؛
- Composer Validate/Install/Audit؛
- PHP lint موازی؛
- تست‌های ایستای فاز ۱ تا ۹؛
- تست Runtime رمزنگاری، Canonical JSON و SQL splitter؛
- Bash syntax؛
- Docker Compose config validation.

## تست Runtime Core

```bash
php tests/runtime_core.php
```

این تست بدون دیتابیس، رمزنگاری/رمزگشایی Secret، JSON Canonical و SQL splitter را اجرا می‌کند.

## محدودیت این تحویل

در محیط فعلی Docker و PHP CLI نصب نبود؛ بنابراین فایل‌های Staging/CI از نظر Syntax و ساختار بررسی شدند اما Container واقعی اینجا اجرا نشد. اجرای `docker compose up` و Smoke Test باید روی ماشین Staging یا CI انجام شود.

## تست‌های بعدی Staging

1. Build کامل Container؛
2. اجرای table.php و Migrationهای 001–008؛
3. Ready با جزئیات؛
4. Mock Telegram login/2FA؛
5. Mock پنل برای extra/renew؛
6. Signed update و verify-release؛
7. تغییر عمدی یک فایل Release و انتظار Critical؛
8. Composer audit و PHP lint واقعی؛
9. سپس اتصال تدریجی به یک Bot و پنل آزمایشی واقعی.
