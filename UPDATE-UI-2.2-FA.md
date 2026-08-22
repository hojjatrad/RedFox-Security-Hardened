# Red Fox 2.2 — اعلان Upload، Progress واقعی و رابط حرفه‌ای

## پوشه‌های بروزرسانی

سیستم هر پنج دقیقه در اولین درخواست پنل و هر ساعت از طریق Cron این دو مسیر را بررسی می‌کند:

```text
updates/
Upload/
```

اگر ZIP دارای نسخه جدیدتر باشد، Metadata در `update_sources` ثبت و در Header تمام صفحات پنل پیام زیر نمایش داده می‌شود:

```text
نسخه X از Upload/updates موجود است — نصب
```

دسترسی مستقیم وب به هر دو پوشه در Apache و Nginx مسدود است.

## Progress واقعی

نصب بروزرسانی با AJAX شروع می‌شود و صفحه هر ۷۰۰ میلی‌ثانیه وضعیت Job را از دیتابیس می‌خواند. درصدهای واقعی مراحل:

- آماده‌سازی Job؛
- Maintenance Mode؛
- بکاپ رمزنگاری‌شده دیتابیس؛
- بکاپ و جایگزینی فایل‌ها؛
- اجرای Migrationها؛
- ثبت یکپارچگی و پاک‌سازی Cache؛
- تکمیل یا Rollback.

نوار پیشرفت، درصد، Stage جاری و تعداد فایل پردازش‌شده از کل فایل‌ها را نشان می‌دهد. Session Lock قبل از عملیات طولانی آزاد می‌شود تا Polling مسدود نشود. فقط Endpoint احراز هویت‌شده Progress در Maintenance Mode باز می‌ماند.

## دکمه نصب

در جدول بسته‌های آماده برای هر بسته معتبر دکمه واضح زیر وجود دارد:

```text
⬆️ نصب بروزرسانی
```

نسخه باید دقیق تأیید شود و Snapshot بدون امضا همچنان عبارت `UNSIGNED` می‌خواهد.

## رابط حرفه‌ای

- کارت‌های جدا برای Upload، GitHub و پوشه سرور؛
- راهنمای فارسی مرحله‌به‌مرحله؛
- Diff، نوع Signed/Unsigned، پوشه منبع و حجم؛
- Progress Modal شیشه‌ای با Gradient bar؛
- تاریخچه Jobها؛
- وضعیت و خطای قابل خواندن؛
- طراحی Responsive موبایل.

## فونت و UI سراسری

لایه UI مشترک به `panel/css/theme.css` اضافه شد:

- فونت محلی Arad برای تمام input/button/select/textarea/table/modal؛
- وزن صحیح تیترها؛
- Focus state؛
- Card، Modal، Table و Button یکپارچه؛
- جدول با Header ثابت؛
- Form Hint و Page Guide؛
- Responsive بهتر.

صفحات پرداخت مستقل نیز از `assets/css/public-fa.css` استفاده می‌کنند. Mini App قدیمی پنل نیز به فونت محلی Arad منتقل شد.

## Migration

```text
migrations/013_update_progress_ui.sql
```

ستون‌های Progress و منبع فایل محلی را اضافه می‌کند. قبل از استفاده از Progress Bar، Migration 013 باید اجرا شود.
