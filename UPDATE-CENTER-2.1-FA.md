# Red Fox 2.1 — مرکز حرفه‌ای بروزرسانی

## علت Signed manifest is missing

ZIPهای کامل پروژه مانند نسخه‌های تحویلی قبلی Snapshot هستند، نه بسته امضاشده Release؛ بنابراین فایل‌های `update-manifest.json` و `update-signature.txt` ندارند. مرکز جدید دو حالت دارد:

1. **Signed Update** — حالت اصلی و امن؛
2. **Trusted Full Snapshot** — حالت سازگاری برای ZIP کامل مورداعتماد مدیر.

برای Snapshot کامل، در پنل بروزرسانی گزینه «پذیرش Snapshot بدون امضا» را فعال کنید. سپس ZIP را دوباره Upload کنید. برای بسته ناشناس اینترنتی این گزینه را فعال نکنید.

## منابع بروزرسانی

- Upload مستقیم ZIP؛
- اسکن خودکار تمام ZIPهای پوشه `updates/`؛
- GitHub Releases با تنظیم `owner/repo` و Asset Pattern؛
- GitHub Source Zip به‌عنوان fallback در Compatibility Mode.

## بررسی خودکار نسخه

Cron جدید:

```bash
php cron/update_check.php
```

یا از طریق Orchestrator با Job ساعتی `update_check`. Metadata آخرین Release در دیتابیس ذخیره می‌شود. اگر نسخه جدیدتر باشد، در Header پنل ادمین اعلان زرد «نسخه جدید موجود است» نمایش داده می‌شود.

## نصب Diff-based

بسته می‌تواند کل پروژه را داشته باشد؛ سیستم برای هر فایل SHA-256 را با نسخه فعلی مقایسه می‌کند و فایل‌ها را به چهار گروه تقسیم می‌کند:

- changed؛
- added؛
- unchanged؛
- preserved runtime data.

فقط changed/added نوشته می‌شوند. فایل غایب در بسته خودکار حذف نمی‌شود.

## اطلاعاتی که حفظ می‌شوند

```text
config.php
text.json
license.json
images.jpg / images.jpeg / custom.jpg
storage/
logs/
updates/
ربات‌های ساخته‌شده vpnbot/*
```

قالب‌های `vpnbot/Default` و `vpnbot/update` بروزرسانی می‌شوند، ولی Instanceهای واقعی نمایندگان حفظ می‌شوند.

## ایمنی نصب

با زدن دکمه بروزرسانی:

1. قفل Update گرفته می‌شود؛
2. Maintenance Mode فعال می‌شود؛
3. بکاپ رمزنگاری‌شده دیتابیس ساخته می‌شود؛
4. فایل‌های تغییرکرده جداگانه Backup می‌شوند؛
5. Hash بسته دوباره هنگام نوشتن کنترل می‌شود؛
6. نوشتن هر فایل با temp + atomic rename انجام می‌شود؛
7. Migrationها با Lock و Checksum اجرا می‌شوند؛
8. در خطای فایل، فایل‌های قبلی Rollback می‌شوند؛
9. نتیجه در `update_jobs` و `update_history` ثبت می‌شود؛
10. Package نصب‌شده به `updates/archive` منتقل می‌شود.

Rollback فایل نمی‌تواند DDL اعمال‌شده دیتابیس را برگرداند؛ بکاپ دیتابیس برای Restore دستی ثبت می‌شود.

## GitHub

در تنظیمات منبع:

```text
Repository: owner/repo
Asset Pattern: RedFox*.zip
```

Signed Release Asset توصیه می‌شود. اگر Release فقط Source ZIP داشته باشد، استفاده از آن نیازمند Compatibility Mode است.

## تأیید بروزرسانی

پیش از نصب باید شماره نسخه جدید را دقیق وارد کنید. برای Snapshot بدون امضا علاوه بر نسخه، عبارت زیر لازم است:

```text
UNSIGNED
```

## Migration

```text
migrations/012_professional_updater.sql
```

این Migration جدول‌های `update_sources` و `update_jobs` را ایجاد می‌کند.
