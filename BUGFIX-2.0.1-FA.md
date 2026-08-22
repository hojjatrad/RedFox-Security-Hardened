# Red Fox 2.0.1 — اصلاح کانال اجباری و Provisioning پنل وب

## علت ذخیره‌نشدن ظاهری کانال

نسخه قدیمی جدول `channels` را بدون ستون `id` می‌ساخت، اما پنل وب لیست را با `ORDER BY id` می‌خواند و حذف را نیز بر اساس id انجام می‌داد. INSERT موفق می‌شد و پیام موفقیت نمایش داده می‌شد، سپس Query لیست به‌دلیل نبود id خطا می‌داد و چون خطا مخفی بود، لیست خالی دیده می‌شد.

Migration 011 ستون `id AUTO_INCREMENT PRIMARY KEY` را به جدول‌های قدیمی اضافه می‌کند. پنل اکنون:

- Schema را صریح بررسی می‌کند؛
- خطای Migration را نشان می‌دهد؛
- فرمت عمومی/خصوصی کانال را Normalize می‌کند؛
- Duplicate را رد می‌کند؛
- بعد از INSERT شناسه رکورد را دوباره بررسی می‌کند؛
- خطای خواندن لیست را دیگر مخفی نمی‌کند.

کانال عمومی:

```text
@channelname
```

کانال خصوصی:

```text
-1001234567890 | https://t.me/+INVITE
```

## علت واقعی نبودن پنل پاسارگارد ساخته‌شده از وب

ربات هنگام افزودن پاسارگارد، آن را به `type=marzban` با `version_panel=1` تبدیل و ده‌ها مقدار پیش‌فرض را ذخیره می‌کرد. پنل وب فقط ۱۰ ستون ساده ثبت می‌کرد و `type=pasargard` را بدون پشتیبانی ManagePanel نگه می‌داشت. همچنین inbounds/proxies/group_ids/proxy_settings و تنظیمات تمدید خالی می‌ماندند.

اصلاحات:

- پاسارگارد در دیتابیس به `marzban + version_panel=1` Normalize می‌شود؛
- تمام Defaultهای همان جریان ربات در Web INSERT نوشته می‌شوند؛
- برای Marzban/Passargard/Marzneshin، Username نمونه موجود در پنل اجباری است؛
- پروفایل کاربر نمونه با API خوانده می‌شود؛
- Passargard: `group_ids` و `proxy_settings` ذخیره می‌شوند؛
- Marzban: `inbounds` و `proxies` ذخیره می‌شوند؛
- Marzneshin: `service_ids` ذخیره می‌شوند؛
- Passwordهای حساس proxy حذف می‌شوند؛
- وضعیت `provisioning_status` و خطای دقیق ذخیره و در کارت پنل نمایش داده می‌شود؛
- اگر Sync شکست بخورد، پنل inactive/incomplete می‌شود و پیام موفقیت کاذب نمی‌دهد؛
- ManagePanel قبل از ساخت اکانت کامل بودن Provisioning را بررسی می‌کند.

## Migration

```text
migrations/011_channels_panel_provisioning.sql
```

اجرا:

```bash
php bin/migrate.php --dry-run
php bin/migrate.php
```

یا روی هاست اشتراکی، فایل 011 را در phpMyAdmin اجرا کنید.

## تعمیر پنل پاسارگارد موجود

1. Migration 011 را اجرا کنید؛
2. پنل ادمین ← پنل‌های VPN؛
3. پنل پاسارگارد را ویرایش کنید؛
4. نوع را پاسارگارد انتخاب کنید؛
5. در «همگام‌سازی مجدد با کاربر نمونه»، Username یک کاربر موجود و سالم در پاسارگارد را وارد کنید؛
6. ذخیره کنید؛
7. کارت پنل باید `Provisioning: ready` و وضعیت فعال نشان دهد؛
8. با یک محصول تست، ساخت اکانت را آزمایش کنید.
