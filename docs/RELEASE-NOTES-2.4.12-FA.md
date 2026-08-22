# یادداشت انتشار v2.4.12-security-hardened

تاریخ انتشار: 2026-08-22

## هدف انتشار

این release روی رفع خطای «ثبت امن تنظیمات نصب»، سازگاری با محدودیت‌های هاست اشتراکی، جلوگیری از شکست مخرب در تنظیم Webhook، dependencyهای واقعی و gate قابل‌تکرار انتشار تمرکز دارد.

## تغییرات شاخص

### نصب و Secret

- بازنویسی HostingSecrets با allow-list؛
- ذخیره در `storage/secure.env.php` با mode `0600`؛
- write اتمیک و کنترل symlink؛
- mutex چندپردازه با `flock` و fallback اتمیک `mkdir`؛
- پشتیبانی runtime وقتی `getenv`/`putenv` غیرفعال است؛
- token یک‌بارمصرف installer خارج web root؛
- حذف installer/token پس از نهایی‌شدن.

### Webhook

- Webhook دارای secret header؛
- preflight URL، DNS/IP، TLS و پورت؛
- coordinator مرکزی برای ثبت/تعمیر؛
- retry محدود و verification با `getWebhookInfo`؛
- حفظ نصب موفق در حالت `pending`؛
- کدهای خطای امن بدون بازنمایی پاسخ خام یا token.

### دیتابیس و پرداخت

- migration 055 برای وضعیت recovery؛
- migration 056 برای full SHA-256 order uniqueness؛
- سه unique index و idempotency بهتر؛
- state/reconciliation hardening در مسیرهای پرداخت؛
- تطبیق verify hash Plisio با قرارداد payload.

### Dependency

- `endroid/qr-code 6.1.3` و API صحیح Builder؛
- `phpoffice/phpspreadsheet 5.9.0`؛
- `paragonie/sodium_compat v1.24.2`؛
- composer.lock و vendor واقعی برای ۱۱ package production؛
- runtime test QR PNG، XLSX و Sodium.

### عملیات و امنیت

- updater امضاشده و fail-closed؛
- backup/restore hardening؛
- security headers، session/CSRF و trusted proxy policy؛
- outbound URL policy و SSRF protection؛
- health/ready، maintenance، audit و log redaction؛
- release builder fail-closed و قرارداد فایل‌های اجباری.

## نکته ارتقا

پیش از ارتقا رسیدهای کارت‌به‌کارت `waiting` را بازبینی کنید. با فعال بودن تأیید خودکار، رسیدهای قدیمی واجد زمان می‌توانند در اجرای Cron پردازش شوند.

Updater بدون public key مورداعتماد عمداً کار نمی‌کند. signature/TLS check را برای انجام update دور نزنید.

## نصب

فایل ZIP و checksum را از assets همین Release بگیرید و راهنمای [نصب](INSTALLATION-FA.md) را دنبال کنید. Repository شامل source کامل و vendor است.

## اعتبارسنجی

گزارش کامل در [TEST-REPORT-2.4.12-FA.md](TEST-REPORT-2.4.12-FA.md) قرار دارد. SHA-256 و تعداد entry artifact نهایی در متن GitHub Release و فایل `.sha256` اعلام می‌شود.
