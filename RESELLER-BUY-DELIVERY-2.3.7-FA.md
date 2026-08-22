# ممیزی جامع عدم واکنش خرید ربات نماینده — 2.3.7

علت‌های هم‌زمان:
1. show_product=true ساخته‌شده در وب، مسیر محصول را معکوس می‌کرد.
2. مسیر خرید از mysqli استفاده می‌کرد و خطای آن خاموش می‌شد.
3. rowCount روی SELECT نتیجه وابسته به Driver داشت.
4. style در Inline Keyboard توسط برخی Bot API/Proxyها رد می‌شد و کل sendMessage شکست می‌خورد.
5. keyboard قبل از تعریف dataBase به آن دسترسی داشت.
6. پنل‌های اختصاصی owner در Query کیبورد لحاظ نمی‌شدند.
7. خطای Telegram API ثبت/Retry نمی‌شد.

رفع:
- محصولات هر زمان وجود دارند نمایش داده می‌شوند و show_product مانع نیست.
- مسیر خرید فقط PDO/fetchAll است.
- Inline style پیش‌فرض خاموش و با Environment اختیاری است.
- reply_markup ناسازگار بدون style خودکار Retry می‌شود.
- خطای HTML با متن Plain Retry می‌شود.
- خطاهای Telegram با Method و Description لاگ می‌شوند.
- botinfo به‌جای dataBase زودهنگام استفاده می‌شود.
- پنل agent/owner/all در Queryها پوشش داده می‌شود.
- Exception به کاربر کد پیگیری می‌دهد.
