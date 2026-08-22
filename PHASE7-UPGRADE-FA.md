# Red Fox 1.6 — امنیت نماینده و بروزرسانی امضاشده

## Migration

```bash
php bin/migrate.php --dry-run
php bin/migrate.php
```

Migration 007 ستون 2FA، جدول نشست نمایندگان و تاریخچه بروزرسانی را ایجاد می‌کند. نشست‌های قدیمی پورتال پس از ارتقا فاقد رکورد سروری هستند و عمداً باید دوباره Login شوند.

## 2FA نماینده

- نماینده عادی می‌تواند از حساب کاربری 2FA تلگرامی را فعال کند.
- 2FA برای سوپرنماینده اجباری و غیرقابل خاموش‌کردن است.
- کد ۶ رقمی، پنج دقیقه اعتبار و حداکثر پنج تلاش دارد.
- کد به آیدی تلگرام همان نماینده از Bot اصلی ارسال می‌شود.
- اگر Bot اصلی Start نشده یا ارسال کد شکست بخورد، Login انجام نمی‌شود.

## نشست‌های نماینده

هر نشست با Hash شناسه Session، IP، Hash مرورگر، زمان ایجاد/فعالیت و انقضای ۲۴ ساعته ثبت می‌شود. نماینده می‌تواند:

- نشست فعلی را ببیند؛
- یک دستگاه را لغو کند؛
- از همه دستگاه‌های دیگر خارج شود.

تغییر دستی Cookie بدون رکورد فعال سروری پذیرفته نمی‌شود.

## تیکت Scope‌شده

دسترسی جدید `support` به نماینده اجازه می‌دهد تیکت‌های جدول قدیمی `support_message` را فقط برای مشتریان Scope خودش ببیند و پاسخ دهد. پاسخ از Bot متعلق به همان مشتری ارسال می‌شود. تغییر Tracking در URL مجدداً با Customer Scope بررسی می‌شود.

## کلیدهای بروزرسانی

کلید را فقط روی سیستم Build امن تولید کنید:

```bash
php bin/generate-update-key.php
```

- `REDFOX_UPDATE_PRIVATE_KEY` فقط در CI/Build آفلاین نگهداری شود.
- `REDFOX_UPDATE_PUBLIC_KEY` روی سرورهای Production قرار گیرد.
- Private Key را هرگز داخل ZIP، Git، config.php یا سرور وب قرار ندهید.

Fingerprint کلید عمومی در صفحه بروزرسانی نمایش داده می‌شود.

## ساخت بسته امضاشده

ابتدا نسخه فایل `version` را افزایش دهید، سپس روی سیستم Build:

```bash
export REDFOX_UPDATE_PRIVATE_KEY='...'
php bin/build-update.php /secure/output/RedFox-1.7.0-signed.zip 1.6.0
```

Manifest شامل نسخه، حداقل نسخه فعلی، زمان Build و SHA-256 تمام فایل‌هاست. امضای Ed25519 روی JSON Canonical اعمال می‌شود.

## Verify و Install

روی سرور:

```bash
export REDFOX_UPDATE_PUBLIC_KEY='...'
php bin/update.php verify /path/RedFox-signed.zip /var/www/redfox
php bin/update.php install /path/RedFox-signed.zip /var/www/redfox
```

Updater موارد زیر را رد می‌کند:

- امضای نامعتبر؛
- کلید عمومی نامعتبر/مفقود؛
- Hash متفاوت؛
- فایل اضافه امضانشده؛
- ZIP entry تکراری؛
- Path Traversal یا مسیر مطلق؛
- Symlink؛
- حجم/تعداد فایل بیش از سقف؛
- محصولی غیر از Red Fox؛
- نسخه مساوی یا قدیمی‌تر؛
- نسخه فعلی کمتر از `min_current_version`.

## پنل بروزرسانی

`panel/update.php` دیگر GitHub zipball یا ZIP خام را نصب نمی‌کند و `extractTo()` ندارد. پنل فقط:

1. فایل Uploadشده را Verify می‌کند؛
2. بسته نامعتبر را حذف می‌کند؛
3. نسخه، تعداد فایل، حجم و SHA را نشان می‌دهد؛
4. فرمان CLI نصب را نمایش می‌دهد؛
5. تاریخچه Update را نشان می‌دهد.

نصب از مرورگر عمداً ممنوع است تا Timeout، Permission ناقص و نیمه‌کاره ماندن کپی فایل رخ ندهد.

## نصب اتمیک

CLI Updater بسته را در Release جدید Stage می‌کند، config فعلی را با Permission 640 کپی، Storage/Logs مشترک را متصل، Migration و Preflight را اجرا و بعد Symlink `current` را اتمیک عوض می‌کند. در خطای Verify/Migration/Preflight، Symlink تغییر نمی‌کند.

## تست Staging

1. دستکاری یک بایت ZIP و انتظار Signature/Hash failure؛
2. افزودن فایل PHP امضانشده و انتظار reject؛
3. بسته دارای `../` و Symlink و انتظار reject؛
4. نصب نسخه مساوی/قدیمی و انتظار reject؛
5. خطای عمدی Migration و اطمینان از ثابت ماندن current؛
6. Login نماینده با 2FA، کد اشتباه، انقضا و پنج تلاش؛
7. لغو نشست از دستگاه دیگر؛
8. تلاش IDOR روی Tracking تیکت.
