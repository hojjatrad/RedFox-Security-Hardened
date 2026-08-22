# رفع خطای RedFox

پیش از تغییر کد یا خاموش‌کردن کنترل امنیتی، کد تشخیصی، fingerprint، زمان و component را ثبت کنید. هیچ secretی را در Issue عمومی نفرستید.

## نصب‌کننده باز نمی‌شود

- PHP باید 8.2+ باشد؛
- `installer/` و `index.php` باید در ریشه صحیح extract شده باشند؛
- owner/permission فایل‌ها را بررسی کنید؛
- rewrite یا WAF را موقتاً از طریق rule محدود و ثبت‌شده بررسی کنید؛ کنترل TLS/secret را خاموش نکنید؛
- error log خصوصی PHP را ببینید، نه نمایش خطا در production.

## فایل توکن نصب پیدا نمی‌شود

- نمایش hidden files را در File Manager فعال کنید؛
- نام دقیق روی صفحه ممکن است namespaced باشد؛
- فایل در مسیر امن بالاتر از web root برنامه ایجاد می‌شود؛
- permission parent directory را بررسی کنید؛
- نصب‌کننده را هم‌زمان در چند tab اجرا نکنید.

توکن را در URL قرار ندهید. اگر افشا شد، فایل را حذف و اجازه دهید installer توکن تازه بسازد.

## خطای ذخیره امن تنظیمات

علت‌های رایج:

- `storage` برای PHP قابل نوشتن نیست؛
- owner فایل با PHP-FPM user متفاوت است؛
- هاست rename اتمیک یا ساخت lock directory را منع کرده؛
- `storage/secure.env.php` symlink یا permission نامعتبر دارد؛
- دیسک/Quota پر است؛
- چند درخواست هم‌زمان در حال تغییر تنظیمات‌اند.

راه‌حل:

1. owner و quota را بررسی کنید؛
2. `storage` را محدود ولی writable کنید؛
3. symlink ناشناخته را حذف نکنید تا منشأ آن روشن شود؛ ابتدا رخداد امنیتی را بررسی کنید؛
4. diagnostic هاست را اجرا کنید؛
5. `777` ندهید؛
6. بعد از اصلاح، runtime secret store و permission `0600` را کنترل کنید.

## کدهای Telegram API در نصب

- `TG-AUTH`: token اشتباه/لغوشده؛ از BotFather token تازه بگیرید.
- `TG-DNS`: DNS هاست `api.telegram.org` را resolve نمی‌کند.
- `TG-CONNECT`: egress TCP/443، firewall یا proxy مشکل دارد.
- `TG-TIMEOUT`: اتصال یا پاسخ دیر است؛ egress و timeout میزبان را بررسی کنید.
- `TG-TLS`: CA bundle، OpenSSL/cURL، SNI یا ساعت سرور مشکل دارد.
- `TG-RESPONSE`: WAF/proxy به‌جای JSON پاسخ دیگری داده است.
- `TG-POLICY`: مقصد/مسیر با policy امن URL/DNS/IP/TLS سازگار نیست.

TLS verification را غیرفعال نکنید. در صورت نیاز فقط endpoint HTTPS مورداعتماد و سازگار را با تنظیم رسمی برنامه پیکربندی کنید.

## کدهای Webhook

- `TG-WH-DNS`: رکورد A/AAAA عمومی صحیح نیست یا هنوز منتشر نشده.
- `TG-WH-TLS`: certificate، chain، SNI، hostname یا تاریخ اعتبار مشکل دارد.
- `TG-WH-PORT`: پورت باید یکی از 80، 88، 443 یا 8443 باشد.
- `TG-WH-IP`: DNS به IP private، local یا reserved اشاره می‌کند.
- `TG-WH-CONNECT`: endpoint از اینترنت قابل اتصال نیست.
- `TG-WH-HTTP`: endpoint/WAF پاسخ ناموفق داده است.
- `TG-WH-REDIRECT`: redirect ناامن یا loop وجود دارد.
- `TG-WH-URL` / `TG-WH-PATH`: دامنه یا مسیر نصب معتبر نیست.
- `TG-WH-SECRET`: secret نامعتبر است؛ repair مقدار استاندارد می‌سازد.
- `TG-WH-AUTH`: token ربات رد شده است.
- `TG-WH-RATE`: rate limit موقت؛ کمی بعد retry کنید.
- `TG-WH-UPSTREAM`: اختلال موقت Telegram.
- `TG-WH-TRANSPORT`: ارتباط خروجی با Telegram برقرار نیست.
- `TG-WH-UNKNOWN`: fingerprint را با log خصوصی تطبیق دهید.

بعد از اصلاح DNS/SSL، در `panel/bot_settings.php` گزینه تعمیر را اجرا کنید. نصب هسته در حالت pending حفظ می‌شود.

## ربات update دریافت نمی‌کند

- `getWebhookInfo` را از مسیر پنل بررسی کنید؛
- URL باید زیرشاخه نصب را کامل داشته باشد؛
- endpoint باید POST بگیرد؛ GET ناموفق/405 طبیعی است؛
- secret header باید با مقدار ثبت‌شده یکسان باشد؛
- WAF نباید header تلگرام یا JSON را حذف کند؛
- ساعت سیستم و SSL را کنترل کنید؛
- از ثبت چند ربات روی یک URL/secret اشتباه جلوگیری کنید.

## اتصال دیتابیس

- hostname رسمی میزبان، نام DB و username prefix را بررسی کنید؛
- extensionهای `pdo_mysql` و `mysqli` فعال باشند؛
- user روی همان database privilege داشته باشد؛
- charset را `utf8mb4` نگه دارید؛
- اگر DB remote است، firewall و allow-list IP را بررسی کنید؛
- raw DSN/password را در log عمومی چاپ نکنید.

در migration failure ابتدا backup بگیرید. SQL را دستی و بدون فهم idempotency اجرا نکنید.

## Cron اجرا نمی‌شود

- command را از Wizard هاست کپی کنید؛
- مسیر PHP CLI و نسخه آن ممکن است با PHP وب متفاوت باشد؛
- working directory و permission را بررسی کنید؛
- secret Cron را در log/email عمومی نمایش ندهید؛
- overlap jobها را با lock برنامه کنترل کنید؛
- timezone PHP/DB/سیستم را هماهنگ کنید؛
- آخرین run، exit status و queue lag را بررسی کنید.

## QR یا XLSX ساخته نمی‌شود

- وجود `vendor/autoload.php` و تطابق vendor با composer.lock؛
- extensionهای `gd`، `zip`، `xml`/`dom` و `mbstring`؛
- memory limit؛
- writable بودن مسیر موقت؛
- اجرای `php tests/runtime_dependencies.php`.

این نسخه از API واقعی `endroid/qr-code 6.1.3` استفاده می‌کند؛ بازگرداندن `Builder\PngBuilder` قدیمی خطاست.

## Update غیرفعال است

اگر trusted public key موجود نیست، updater عمداً fail-closed است. کلید عمومی release معتبر را از کانال مستقل دریافت و طبق پنل تنظیم کنید. signature check را حذف نکنید و ZIP ناشناس را جایگزین نکنید.

## Backup ساخته یا بازیابی نمی‌شود

- فضای دیسک و writable path؛
- وجود extension رمزنگاری/Sodium؛
- master/backup key صحیح؛
- permission و owner؛
- version و checksum فایل؛
- database privilege؛
- test restore روی staging.

تعویض کلید بدون decrypt/re-encrypt برنامه‌ریزی‌شده می‌تواند backup را غیرقابل بازیابی کند.

## خطای 403، 405 یا 500

- `405` روی GET endpoint Webhook می‌تواند رفتار صحیح باشد؛
- `403` ممکن است از CSRF/session/WAF/permission باشد؛ کد پنل و log را مقایسه کنید؛
- `500` را در PHP error log خصوصی بررسی کنید؛ `display_errors` را در production روشن نکنید؛
- نسخه PHP وب و CLI را مقایسه کنید؛
- ابتدا health/ready و سپس component خاص را جدا کنید.

## اطلاعات لازم برای گزارش خصوصی

- نسخه دقیق release و SHA-256؛
- نسخه PHP، DB و وب‌سرور؛
- کد خطای allow-list و fingerprint؛
- زمان و timezone؛
- مراحل بازتولید با مقادیر حساس حذف‌شده؛
- stack trace redacted در صورت وجود؛
- نتیجه health/runtime مرتبط.

ارسال token، password، cookie، key، فایل `secure.env.php` یا backup production ممنوع است.
