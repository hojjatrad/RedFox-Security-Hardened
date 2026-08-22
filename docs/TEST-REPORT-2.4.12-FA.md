# گزارش اعتبارسنجی Release 2.4.12 Security Hardened

تاریخ gate نهایی: ۱۴۰۵/۰۵/۳۱ برابر 2026-08-22

## دامنه

هدف این gate تأیید بسته قابل‌نصب، hardening هاست اشتراکی، recovery وب‌هوک، dependencyهای واقعی، migration دیتابیس و پاک‌بودن artifact انتشار بود.

## نتایج source gate

| آزمون | نتیجه |
|---|---|
| Python regression suites | ۳۹/۳۹ موفق |
| PHP lint/parser | ۳۴۳/۳۴۳ موفق |
| Runtime هسته | موفق |
| Runtime dependency | موفق |
| Runtime security | موفق |
| Runtime HostingSecrets | موفق |
| Concurrency با flock | موفق |
| Concurrency با mkdir fallback | موفق |
| Composer validate/lock/vendor parity | موفق |
| Composer/OSV advisory بررسی‌شده | صفر advisory شناخته‌شده در زمان gate |
| YAML parser برای CI/Compose | موفق |

## dependency runtime

- Endroid QR Code `6.1.3`: تولید PNG واقعی، نمونه `qr_bytes=511`؛
- PhpSpreadsheet `5.9.0`: ساخت/خواندن XLSX موفق؛
- Sodium و `sodium_compat v1.24.2`: عملیات runtime موفق؛
- lock/vendor برای ۱۱ package production منطبق.

## نصب و دیتابیس

نصب واقعی قبلی روی PHP 8.4 و MariaDB با Telegram test double کنترل‌شده انجام شد:

- پاسخ نصب: HTTP 200؛
- ۸۱ جدول؛
- ۵۵ migration اجراشده؛
- migration 056 موجود؛
- install lock، حذف installer/token و endpointهای پنل تأیید شدند؛
- `ready.php`، `health.php` و `panel/login.php`: HTTP 200؛
- GET روی endpoint اصلی: HTTP 405؛
- secret file: mode `0600` و ۱۴ کلید allow-list؛
- اتصال PDO/config با `disable_functions=getenv,putenv`: موفق.

## قرارداد دیتابیس

سه unique index فعال و uniqueness شناسه سفارش بلند با ستون باینری ۳۲بایتی مبتنی بر:

```sql
UNHEX(SHA2(id_order, 256))
```

تأیید runtime: `FULL_HASH_UNIQUENESS_OK`.

## Webhook recovery

سناریوی recovery با خطای مقصد کنترل‌شده اجرا و کد تشخیصی امن `TG-WH-DNS` تأیید شد. شکست Webhook، نصب هسته و migration موفق را rollback نکرد. coordinator مسیر retry/repair و verification مستقل را حفظ کرد.

## Release artifact audit

Builder رسمی موارد زیر را کنترل کرد:

- ۱۵۹۴ entry در build پس از تکمیل مستندات انتشار؛
- قرارداد ۱۱۶ فایل اجباری؛
- نام‌های یکتا؛
- نبود traversal؛
- نبود symlink و encryption ناخواسته؛
- نبود secret/runtime artifact؛
- CRC کامل ZIP؛
- checksum مستقل؛
- runtime dependency/security/HostingSecrets روی extraction مستقل.

Builder پس از افزودن مستندات دوباره موفق شد. SHA-256 نهایی پس از آخرین rebuild در GitHub Release و فایل checksum همان asset ثبت می‌شود؛ hash داخل خود ZIP درج نمی‌شود تا قرارداد checksum خودارجاع ایجاد نشود.

## محدودیت‌ها

- Docker در محیط gate نصب نبود؛ YAML parse شد ولی `docker compose config` اجرا نشد؛
- تماس با Telegram production و درگاه‌های واقعی بدون credential بهره‌بردار انجام نشد؛
- یک fresh-install نهایی از ZIP در preflight Telegram با policy محیط آزمایش (`TG-POLICY`) متوقف شد؛ این توقف پیش از آغاز DB install بود. نصب واقعی کامل قبلی با test double مورداعتماد موفق بوده است؛
- نبود advisory شناخته‌شده تضمین نبود vulnerability ناشناخته نیست؛
- API پنل‌ها و gatewayهای ثالث ممکن است بعداً تغییر کنند؛ staging ضروری است.

## معیار پذیرش بهره‌بردار

پیش از production باید دست‌کم این موارد در محیط خود بهره‌بردار موفق شوند:

1. checksum release؛
2. installer روی HTTPS؛
3. Webhook active و دریافت update آزمایشی؛
4. Cron و queue؛
5. backup رمز‌شده و restore روی staging؛
6. یک محصول/connector آزمایشی؛
7. پرداخت sandbox یا کم‌مبلغ با callback واقعی؛
8. QR و XLSX؛
9. health/ready؛
10. review permission، admin و log redaction.
