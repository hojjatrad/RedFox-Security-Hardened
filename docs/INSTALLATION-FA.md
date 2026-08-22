# راهنمای کامل نصب RedFox 2.4.12

این سند نصب روی هاست اشتراکی و VPS، ارتقا از نسخه موجود، تنظیم Cron و بررسی پس از نصب را پوشش می‌دهد.

## ۱. پیش‌نیازها

### نرم‌افزار

- PHP `8.2` یا جدیدتر؛ نسخه‌های هدف این release: 8.2، 8.3 و 8.4؛
- MySQL 8 یا MariaDB معاصر با InnoDB و `utf8mb4`؛
- Apache/LiteSpeed یا Nginx؛
- HTTPS معتبر و دامنه عمومی برای Webhook تلگرام؛
- Cron Job؛
- File Manager یا SSH برای مشاهده فایل مخفی توکن نصب.

### افزونه‌های PHP

ضروری: `pdo_mysql`، `mysqli`، `curl`، `json`، `mbstring`، `openssl`، `zip`، `fileinfo` و `gd`.

توصیه‌شده: `sodium`، `intl` و `opcache`. اگر extension بومی Sodium موجود نباشد، dependency قفل‌شده `paragonie/sodium_compat` همراه بسته وجود دارد.

### منابع پیشنهادی

- `memory_limit`: حداقل 256MB و برای export/update بهتر است 512MB؛
- `max_execution_time`: حداقل 60 ثانیه برای درخواست‌های مدیریتی؛
- `upload_max_filesize` و `post_max_size`: متناسب با backup/update؛
- فضای آزاد: حداقل سه برابر اندازه بسته برای extract، backup و update موقت.

## ۲. آماده‌سازی امن

1. با BotFather ربات بسازید و bot token را نزد خود نگه دارید.
2. با همان حساب مدیر، ربات را Start کنید.
3. آیدی عددی Telegram مدیر را آماده کنید.
4. در کنترل‌پنل هاست یک دیتابیس و یک کاربر اختصاصی بسازید؛ کاربر فقط روی دیتابیس RedFox دسترسی کامل داشته باشد.
5. DNS دامنه و SSL را قبل از نصب نهایی کنید.
6. ZIP و فایل `.sha256` را از یک Release یکسان دریافت کنید.

بررسی checksum در Linux/macOS:

```bash
sha256sum -c RedFox-2.4.12-Security-Hardened-Hosting-Compatible-Final.zip.sha256
```

در PowerShell:

```powershell
Get-FileHash .\RedFox-2.4.12-Security-Hardened-Hosting-Compatible-Final.zip -Algorithm SHA256
```

مقدار خروجی باید با فایل checksum همان Release یکسان باشد.

## ۳. نصب روی cPanel، DirectAdmin یا aaPanel

1. ZIP را در مسیر نهایی مانند `public_html/bot` بارگذاری کنید.
2. همان‌جا Extract کنید. نباید یک پوشه اضافه بین مسیر URL و فایل‌های برنامه ایجاد شود؛ `index.php`، `config.php`، `storage/` و `installer/` باید مستقیماً در ریشه برنامه باشند.
3. PHP دامنه را روی 8.2+ تنظیم و extensionهای لازم را فعال کنید.
4. owner فایل‌ها باید همان کاربر PHP باشد. از permission عمومی `777` استفاده نکنید.
5. این مسیر را فقط با HTTPS باز کنید:

   ```text
   https://YOUR-DOMAIN/YOUR-PATH/installer/
   ```

6. installer در اولین درخواست فایل مخفی `.redfox-install-token` یا نام namespaced مشابه `.redfox-install-token-<suffix>` را در مسیر امن بالاتر از web root ایجاد می‌کند. نام دقیق روی صفحه گفته می‌شود. نمایش hidden files را در File Manager فعال و مقدار ۶۴ کاراکتری فایل را در فرم احراز وارد کنید.
7. توکن را در query string، Issue، پیام‌رسان یا screenshot قرار ندهید.
8. فرم نصب را با اطلاعات مدیر، ربات و دیتابیس کامل کنید. فیلدهای حساس پس از خطا دوباره در HTML نمایش داده نمی‌شوند.
9. نصب‌کننده Telegram، دیتابیس، migrationها، secret store و مسیر عمومی را کنترل می‌کند؛ سپس مدیر را می‌سازد و برای ثبت Webhook تلاش می‌کند.
10. بعد از موفقیت، installer و فایل token حذف و install lock ایجاد می‌شوند.
11. وارد `https://YOUR-DOMAIN/YOUR-PATH/panel/` شوید و رمز موقت را فوراً تغییر دهید.

### Database host غیرپیش‌فرض

در بیشتر هاست‌ها مقدار پیش‌فرض `localhost` صحیح است. اگر میزبان مقدار دیگری داده، از همان hostname رسمی استفاده کنید. استفاده از IP خصوصی برای دیتابیس داخلی میزبان طبیعی است؛ policy عمومی SSRF مربوط به endpointهای خروجی/وب‌هوک است، نه اتصال دیتابیس محلی.

### Permissionها

در هاست‌هایی که PHP با owner حساب اجرا می‌شود معمولاً این مقادیر کافی است:

```bash
find . -type d -exec chmod 750 {} \;
find . -type f -exec chmod 640 {} \;
chmod 750 storage Upload updates
```

برنامه مجوز `storage/secure.env.php` را روی `0600` enforce می‌کند. اگر PHP-FPM user با owner فایل متفاوت است، به‌جای `777` از group اختصاصی وب‌سرور و permission محدود استفاده کنید.

## ۴. نصب روی VPS

دو مسیر وجود دارد:

### installer وب

VirtualHost را به ریشه برنامه متصل، HTTPS را فعال، owner را به PHP-FPM user بدهید و مراحل بخش قبل را اجرا کنید.

نمونه permission:

```bash
sudo chown -R www-data:www-data /var/www/redfox
sudo find /var/www/redfox -type d -exec chmod 750 {} \;
sudo find /var/www/redfox -type f -exec chmod 640 {} \;
```

### اسکریپت نصب سرور

`install.sh` برای سناریوی سرور در source موجود است. قبل از اجرا آن را بازبینی کنید؛ اسکریپت تغییرات سیستمی ایجاد می‌کند.

```bash
sudo bash install.sh
```

از اجرای مستقیم script دریافت‌شده با pipe شبکه به shell خودداری کنید.

## ۵. تنظیم Cron

Cron برای صف پیام، بررسی سرویس‌ها، پرداخت، backup و سایر عملیات دوره‌ای لازم است. پس از ورود:

1. `پنل مدیریت ← Wizard هاست` را باز کنید؛
2. commandهای متناسب با مسیر و secret نصب را کپی کنید؛
3. آن‌ها را در بخش Cron Jobs کنترل‌پنل اضافه کنید؛
4. timezone سیستم و PHP را بررسی کنید؛
5. یک اجرای دستی کنترل‌شده انجام و نتیجه را در health/audit بررسی کنید.

Command یا secret کرون را حدس نزنید و نمونه عمومی را عیناً استفاده نکنید؛ Wizard مقدار مخصوص نصب شما را تولید می‌کند. اگر PHP CLI در هاست موجود نیست، از URL امن تولیدشده توسط Wizard استفاده کنید و secret را در log عمومی قرار ندهید.

## ۶. Webhook پس از نصب

اگر مرحله اصلی نصب کامل شود ولی محیط نتواند Webhook را ثبت کند، برنامه نصب را rollback مخرب نمی‌کند. وضعیت `pending` و کد امن ثبت می‌شود:

1. وارد پنل شوید؛
2. `panel/bot_settings.php` را باز کنید؛
3. DNS، SSL و URL را اصلاح کنید؛
4. «تعمیر Webhook» را اجرا کنید؛
5. نتیجه مستقل `getWebhookInfo` را بررسی کنید.

جزئیات کدها در [رفع خطا](TROUBLESHOOTING-FA.md) آمده است.

## ۷. بررسی پس از نصب

- `ready.php` باید برای محیط آماده پاسخ موفق دهد؛
- `health.php` باید بدون نمایش secret وضعیت مناسب داشته باشد؛
- `panel/login.php` باید فقط روی HTTPS باز شود؛
- درخواست GET به endpoint اصلی webhook نباید update پردازش کند؛
- installer و فایل `.redfox-install-token*` نباید باقی مانده باشند؛
- `storage/secure.env.php` باید خارج از دسترسی HTTP و با mode `0600` باشد؛
- وضعیت Webhook در پنل `active` باشد؛
- Cron آخرین اجرای موفق داشته باشد؛
- backup رمز‌شده آزمایشی تولید و امکان decrypt آن روی staging تأیید شود.

## ۸. ارتقا از نسخه قبلی

1. maintenance mode را فعال کنید.
2. از دیتابیس، `storage`، فایل‌های upload و secretها backup رمز‌شده بگیرید.
3. pending payment/receipt و عملیات صف را بررسی کنید.
4. release و checksum را تأیید کنید.
5. ابتدا روی clone دیتابیس و staging ارتقا را آزمایش کنید.
6. updater معتبر یا روش مستند host را اجرا کنید؛ migrationها idempotent هستند، اما backup همچنان اجباری است.
7. health، webhook، cron، پرداخت آزمایشی و connector آزمایشی را بررسی کنید.
8. maintenance mode را بردارید و ۲۴ ساعت اول log/queue را پایش کنید.

### هشدار رسیدهای قدیمی

اگر تأیید خودکار رسید فعال باشد، پس از ارتقا رسیدهای `waiting` قدیمی که از زمان تنظیم‌شده گذشته‌اند می‌توانند در اجرای Cron پردازش شوند. پیش از ارتقا، رسیدهایی را که نباید تأیید شوند دستی رد یا تعیین تکلیف کنید.

## ۹. برگشت نسخه

Rollback فقط جایگزینی فایل نیست، چون migration دیتابیس ممکن است اجرا شده باشد:

- قبل از ارتقا snapshot/backup معتبر داشته باشید؛
- نسخه فایل و schema/database را با هم برگردانید؛
- secret file را بدون برنامه rotation جایگزین نکنید؛
- ابتدا restore را در staging تست کنید؛
- پس از rollback، Webhook و Cron را دوباره کنترل کنید.
