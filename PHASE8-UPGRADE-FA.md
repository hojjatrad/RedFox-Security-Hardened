# Red Fox 1.7 — Recovery، بکاپ اجباری و چرخش کلید Update

## Migration

```bash
php bin/migrate.php --dry-run
php bin/migrate.php
```

Migration 008 جدول کدهای بازیابی و metadata کانال/کلید/بکاپ Update را اضافه می‌کند.

## کدهای بازیابی نماینده

در صفحه حساب و امنیت، نماینده پس از واردکردن رمز فعلی می‌تواند ۱۰ کد یک‌بارمصرف بسازد. هر بار تولید:

- تمام کدهای قبلی را باطل می‌کند؛
- فقط Hash کد را در دیتابیس ذخیره می‌کند؛
- کدهای خام را فقط همان یک بار نمایش می‌دهد؛
- هر کد پس از یک Login اتمیک used می‌شود؛
- ورود با Recovery Code برای نماینده پیام هشدار و Audit Log ایجاد می‌کند.

کد نمونه:

```text
ABCDE-FG234
```

این کدها باید آفلاین نگهداری شوند و جایگزین دائمی 2FA نیستند.

## چرخش کلید بروزرسانی

برای Key Ring، JSON زیر را در Environment قرار دهید:

```text
REDFOX_UPDATE_PUBLIC_KEYS={"2026-primary":"BASE64_PUBLIC_KEY","2027-next":"BASE64_PUBLIC_KEY"}
REDFOX_UPDATE_CHANNEL=stable
```

روی Build Server:

```text
REDFOX_UPDATE_KEY_ID=2027-next
REDFOX_UPDATE_PRIVATE_KEY=...
REDFOX_UPDATE_CHANNEL=stable
```

Manifest شامل `key_id` و `channel` است. انتخاب Key ID داخل بسته باعث اعتماد خودکار نمی‌شود؛ فقط کلیدهای موجود در Key Ring سرور پذیرفته می‌شوند.

روال Rotation:

1. کلید عمومی جدید را کنار کلید قبلی در Key Ring قرار دهید؛
2. یک Update با کلید جدید منتشر و روی Staging/Production تست کنید؛
3. بعد از ارتقای همه سرورها، کلید قدیمی را حذف کنید؛
4. Private Key قدیمی را Archive یا نابود کنید.

کانال‌های مجاز `stable` و `beta` هستند و سرور فقط کانال تنظیم‌شده خودش را قبول می‌کند.

## بکاپ اجباری قبل Update

متغیر جدید:

```text
REDFOX_BACKUP_KEY=<32-byte hex/base64 key>
```

تولید:

```bash
openssl rand -hex 32
```

`bin/update.php install` قبل از Stage/Migration الزاماً `bin/backup-database.php` را اجرا می‌کند. اگر بکاپ ساخته نشود، Update متوقف و Symlink دست‌نخورده می‌ماند.

بکاپ:

- SQL را Streaming و gzip می‌کند؛
- با Sodium SecretStream رمز و Authenticate می‌کند؛
- Permission برابر 0600 دارد؛
- SHA-256 جانبی دارد؛
- در `shared/backups` ذخیره می‌شود؛
- مسیر آن در `update_history.backup_path` ثبت می‌شود.

بکاپ‌گیری دستی:

```bash
php bin/backup-database.php /secure/backups
```

بازیابی فقط با تأیید صریح:

```bash
php bin/restore-database.php /secure/backups/file.rxb --confirm=RESTORE
```

Restore صحت SHA، Header، تمام Frameهای رمزنگاری و Final Authentication Tag را بررسی می‌کند و سپس SQL را به mysql client می‌فرستد. ابتدا فقط روی Staging Restore Drill انجام دهید.

## Maintenance Mode

در زمان Update فایل `storage/maintenance.flag` ساخته می‌شود. تمام درخواست‌های وب به‌جز `health.php` و `ready.php` پاسخ 503 و `Retry-After` می‌گیرند. CLI محدود نمی‌شود.

کنترل دستی:

```bash
php bin/maintenance-mode.php on "پیام نگهداری"
php bin/maintenance-mode.php status
php bin/maintenance-mode.php off
```

Updater در موفقیت، خطا و Shutdown عادی Marker را حذف می‌کند. در Crash سیستم، ادمین می‌تواند آن را با فرمان `off` حذف کند؛ فقط پس از بررسی Migration و نسخه فعال.

## Update History توسعه‌یافته

تاریخچه اکنون ثبت می‌کند:

- Key ID امضاکننده؛
- Stable/Beta channel؛
- مسیر بکاپ قبل Update؛
- نسخه قبل و بعد؛
- SHA-256 و وضعیت.

## متغیرهای الزامی Production

```text
REDFOX_MASTER_KEY
REDFOX_BACKUP_KEY
REDFOX_CRON_SECRET
REDFOX_DIAG_SECRET
REDFOX_HEALTH_TOKEN
REDFOX_UPDATE_PUBLIC_KEYS
REDFOX_UPDATE_CHANNEL=stable
```

Private update key فقط روی Build Server است.

## تست Staging

1. تولید Recovery Codes، استفاده یک‌باره و رد استفاده دوم؛
2. باطل‌شدن کدهای قبلی پس از Regenerate؛
3. Login با Recovery و دریافت هشدار؛
4. Update با کلید قدیم و جدید در دوره Rotation؛
5. رد Key ID ناشناس و کانال Beta روی سرور Stable؛
6. شکست Backup و اطمینان از نصب‌نشدن Update؛
7. Update موفق و ثبت backup_path؛
8. Restore بکاپ روی دیتابیس Staging؛
9. مشاهده 503 Maintenance و باقی‌ماندن health/ready؛
10. Crash Update و پاک‌سازی کنترل‌شده Marker.
