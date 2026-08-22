# تعمیر نهایی جریان خرید ربات اصلی و نماینده — 2.3.5

- تمام ارسال‌های پیام limitedpanelfirst/limitedpanel از مسیر اجرایی خرید حذف شد.
- شمارش invoice فقط advisory log است و هیچ return/block ایجاد نمی‌کند.
- ظرفیت واقعی فقط هنگام createUser توسط API پنل VPN تعیین می‌شود.
- متن دکمه‌های ربات نماینده در صورت ناقص/قدیمی بودن text.json با پیش‌فرض‌های فارسی ترمیم می‌شود.
- خرید با متن سفارشی ذخیره‌شده، چهار عنوان رایج فارسی و callbackهای buy/buyback شناسایی می‌شود.
- keyboard.php و index.php هر دو دقیقاً از یک Default map استفاده می‌کنند.
- Installer تمام پوشه‌های واقعی vpnbot دارای config.php را اسکن می‌کند.
- sync پس از جایگزینی فایل OPcache را invalidate و template version را ثبت می‌کند.
