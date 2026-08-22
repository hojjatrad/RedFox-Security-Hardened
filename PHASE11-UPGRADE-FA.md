# Red Fox 2.0 — تکمیل قابلیت‌های نهایی

## Migration الزامی

```bash
php bin/migrate.php --dry-run
php bin/migrate.php
```

روی هاست فاقد Terminal، فایل‌های 001 تا 010 را به‌ترتیب در phpMyAdmin اجرا کنید. Migration 010 تمام جدول‌ها و ستون‌هایی را که قبلاً در Runtime ساخته می‌شدند ایجاد می‌کند.

## پیوست امن تیکت

نماینده می‌تواند در پاسخ تیکت فایل‌های زیر را تا سقف ۸MB ارسال کند:

```text
JPG, PNG, WebP, MP4, PDF, TXT
```

MIME با `finfo` از محتوای واقعی تشخیص داده می‌شود، نام ذخیره‌سازی تصادفی است، فایل در `storage/tickets` با Permission 0640 قرار می‌گیرد و SHA-256 ثبت می‌شود. فایل مستقیماً از وب قابل دسترسی نیست و دانلود فقط از `portal/ticket_file.php` بعد از بررسی Ticket و Customer Scope انجام می‌شود.

## تسویه نمایندگان

نماینده:

- شبا را ثبت می‌کند؛
- شبا با Master Key رمز می‌شود؛
- پس از تأیید ادمین درخواست می‌دهد؛
- مبلغ فوراً در Escrow رزرو می‌شود؛
- درخواست pending را می‌تواند لغو کند؛
- تاریخچه، کارمزد، خالص و مرجع پرداخت را می‌بیند.

ادمین:

- حداقل مبلغ، درصد کارمزد و Hold Time را تنظیم می‌کند؛
- شبا را تأیید یا رد می‌کند؛
- درخواست را Approve/Reject می‌کند؛
- شماره مرجع بانکی را ثبت و Paid می‌کند.

هر انتقال و Refund در `reseller_wallet_ledger` ثبت می‌شود. رد درخواست مبلغ کامل را آزاد می‌کند و هنگام Paid، خالص و کارمزد جداگانه از Escrow خارج می‌شوند.

## دسته‌بندی مستقل

`reseller_categories` اکنون دارای نام، slug، ترتیب و وضعیت مستقل است. اتصال محصول در `reseller_product_categories` ذخیره می‌شود. نماینده می‌تواند محصولات پایه یا اختصاصی مجاز خود را دسته‌بندی کند. اگر دسته مستقل فعال وجود داشته باشد، ربات نماینده فقط همان دسته‌ها را نمایش می‌دهد و دسته‌های عمومی به‌عنوان fallback استفاده نمی‌شوند.

## Write API

POSTهای نمایندگی با HMAC-SHA256، Timestamp پنج‌دقیقه‌ای، Nonce یک‌بارمصرف، Idempotency Key و IP Allowlist محافظت می‌شوند. Scopeها:

```text
message_write
service_write
product_write
settlement_write
```

مستندات کامل:

```text
docs/RESELLER-WRITE-API-FA.md
examples/reseller-api-client.php
```

## حذف DDL از Runtime

هیچ صفحه وب، Worker، Cron، Connector یا پنل عملیاتی دیگر CREATE/ALTER/DROP اجرا نمی‌کند. Schema فقط در این مسیرها مجاز است:

```text
migrations/
bin/migrate.php
table.php هنگام Installer
backup/restore tooling
```

`table.php` پس از حذف Installer از وب 404 می‌دهد. Runtime با `rx_require_schema` فقط وجود Schema را بررسی و در صورت کمبود، اجرای Migration را درخواست می‌کند.

پس از تأیید Migration 010 می‌توان مجوز کاربر Runtime دیتابیس را به DML محدود کرد:

```bash
php bin/generate-runtime-grants.php DB_USER DB_HOST DB_NAME
```

SQL خروجی را فقط MySQL Administrator و پس از بررسی اجرا کند.

## تست‌های الزامی

1. آپلود هر MIME مجاز و یک فایل جعلی با پسوند تصویر؛
2. تلاش دانلود Attachment تیکت نماینده دیگر؛
3. درخواست تسویه هم‌زمان، لغو، رد، Paid و Ledger balancing؛
4. شبا با checksum نامعتبر؛
5. دسته مستقل و خرید از Callback دستکاری‌شده؛
6. POST API با Signature غلط، Timestamp قدیمی، Nonce تکراری و Idempotency body conflict؛
7. اجرای برنامه با کاربر دیتابیس فاقد CREATE/ALTER/DROP؛
8. نصب تازه و ارتقا از دیتابیس قبلی با Migration 010.
