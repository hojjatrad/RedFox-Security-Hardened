# گزارش نهایی اصلاح و سخت‌سازی امنیتی RedFox 2.4.12

تاریخ تهیه: ۲۰۲۶-۰۸-۱۹  
نوع خروجی: کپی مستقل اصلاح‌شده؛ بسته‌های ورودی اصلی تغییر نکرده‌اند.

## خلاصه نتیجه

موارد قابل‌اصلاح با تحلیل ایستا در محیط حاضر پیاده‌سازی شد. نسخه حاضر امنیت احراز هویت و نشست، پرداخت، وب‌هوک، API، پنل مدیریت، updater، backup/restore، installer، دسترسی فایل، آپلود، ثبت خطا و ارتباطات خروجی را به‌صورت fail-closed تقویت می‌کند. این نتیجه «تضمین نبود آسیب‌پذیری» نیست و پیش از بهره‌برداری عمومی، اجرای تست عملی روی staging همسان با production الزامی است.

## مهم‌ترین اصلاحات انجام‌شده

### ۱. پرداخت و یکپارچگی مالی
- state machine پرداخت، binding درگاه/شناسه فاکتور، جلوگیری از replay و تأیید روش پرداخت موردانتظار پیاده شد.
- migrationهای `049` تا `054` برای پرداخت، secret وب‌هوک reseller، eventهای کارت، capability اشتراک، idempotency API و lifecycle پردازش update اضافه شد.
- مسیرهای NowPayments، Plisio، ZarinPal/ZarinPay، AqayePardakht و سایر callbackهای بررسی‌شده در برابر تکرار، جعل provider و transition نامعتبر سخت‌سازی شدند.
- عملیات حساس مالی، تمدید، حجم اضافه، شارژ و reseller با transaction، lock، ledger/audit و validation تقویت شدند.

### ۲. احراز هویت، مجوز و نشست
- نشست امن، strict mode، کوکی HttpOnly/SameSite، revalidation مدیر جاری، session versioning و خروج امن متمرکز شد.
- CSRF و بررسی same-origin برای عملیات تغییردهنده پنل اعمال شد.
- bypassهای مجوز، IDORهای بررسی‌شده، stale privileged step و دسترسی‌های مستقیم مدیریتی بسته شدند.
- 2FA، rate limiting اتمیک و سیاست trusted proxy تقویت شد.
- Mini App فقط `initData` معتبر Telegram را می‌پذیرد؛ ورودی‌های ناامن query/form/JSON حذف شدند.

### ۳. وب‌هوک، API و Telegram
- secret token وب‌هوک Telegram و deduplication/claim بادوام updateها اضافه شد.
- IntegrationAuth مشترک، محدودیت روش و اندازه body، nonce/replay protection و idempotency برای APIهای حساس اعمال شد.
- خروجی خام خطای سرویس‌های بیرونی با class معنایی allowlisted یا fingerprint غیرحساس جایگزین شد.
- ثبت log و نمایش exceptionهای خام در مسیرهای اصلی API، پنل، bot، cron، payment و diagnostics حذف شد.

### ۴. SSRF، TLS و ارتباطات خروجی
- بررسی گواهی TLS و hostname اجباری است و escape-hatch غیرفعال‌سازی حذف شده است.
- سیاست مرکزی URL/IP/DNS اضافه شد: مقصدهای loopback، link-local، metadata، multicast و reserved همیشه رد می‌شوند؛ DNS پیش از اتصال resolve و با `CURLOPT_RESOLVE` pin می‌شود.
- redirect خودکار در کد غیرvendor حذف شد؛ updater هر hop را مستقل و فقط در allowlist HTTPS/GitHub بررسی می‌کند.
- transport مشترک، همه ماژول‌های پنل بررسی‌شده، user sync، campaign health-check، Certification، subscription fetch، AI، Telegram، Tronado، SwapWallet و updater به policy مرکزی متصل شدند.
- پاسخ‌های قابل‌دریافت سقف حجم، timeout و کنترل status دارند.
- probe سوکت قدیمی IBSng حذف شد و `checkConnection()` فقط به IPهای resolveشده و تأییدشده متصل می‌شود.
- دانلود پس‌زمینه Telegram دارای سقف ۵ MiB، اعتبارسنجی ساختار تصویر، محدودیت ۵۰۰۰×۵۰۰۰ و ۱۶ میلیون پیکسل و بازکدگذاری JPEG است.

### ۵. updater، backup/restore و installer
- updater فقط بسته دارای امضای معتبر Ed25519 را قبول می‌کند؛ مسیر unsigned حذف شده است.
- backup پیش از update و rollback در شکست اجباری است.
- کنترل path traversal و extraction، staging، integrity و redirect هر hop تقویت شد.
- installer/reset به‌صورت fail-closed، دارای token/lock و کنترل schema/constraint سخت‌سازی شد.
- فایل‌های داخلی، secret-bearing، migration، vendor و upload با سیاست web-server محدود شدند.

### ۶. آپلود، فایل، QR و dependency
- uploadها با allowlist نوع/پسوند، محدودیت حجم، نام تصادفی، دسترسی محدود و پردازش امن‌تر تقویت شدند.
- مسیرهای QR، attachment، backup/restore و فایل‌های مدیریتی در برابر traversal و دسترسی خارج از scope سخت‌سازی شدند.
- PhpSpreadsheet به نسخه رسمی `5.9.0` ارتقا یافت. بررسی OSV انجام‌شده برای ۱۲ dependency نصب‌شده در زمان بررسی، صفر advisory برگرداند؛ این نتیجه وابسته به تاریخ بررسی است و باید در CI تکرار شود.

## اصلاح تکمیلی installer و خطای Telegram

- علت خطای مبهم «عدم توانایی دریافت جزئیات ربات» ریشه‌یابی شد: سیاست DNS pinning، رکوردهای A و AAAA را به‌صورت چند سطر هم‌نام به `CURLOPT_RESOLVE` می‌داد. libcurl سطر قبلی را حذف می‌کرد و در عمل IPv6 جای IPv4 را می‌گرفت؛ روی هاست فاقد IPv6، درخواست `getMe` شکست می‌خورد. این رفتار با cURL مستقل بازتولید شد و قالب جدید با fallback به IPv4 موفق بود.
- پیاده‌سازی مرکزی اکنون در cURL جدید یک رکورد host:port با فهرست چند IP می‌سازد و در cURL قدیمی، IPv4 عمومی را مقدم می‌کند؛ تمام مصرف‌کنندگان policy مرکزی از اصلاح بهره می‌برند.
- transport نصب Telegram دارای دو تلاش محدود برای خطاهای گذرا، timeout کنترل‌شده، سقف پاسخ ۱ MiB، TLS اجباری، DNS pinning و پاسخ JSON محدود است.
- خطاها بدون افشای توکن به کلاس‌های DNS، connect، timeout، TLS/CA، reset، پاسخ خالی/نامعتبر، پاسخ بیش‌ازحد، policy، rate limit و توکن ردشده تفکیک می‌شوند و در رابط کد راهنمای فارسی مانند `TG-DNS` و `TG-TLS` دارند.
- قالب توکن Telegram از قید قدیمی و دقیق ۳۵ کاراکتر به قالب محدود اما آینده‌سازگار تغییر کرد. الگوی redaction در loggerها نیز با همین بازه هماهنگ شد و پایان توکن با `-` یا `_` را پوشش می‌دهد.
- تحویل پیام شامل رمز موقت مدیر fail-closed شد؛ نصب بدون تأیید `sendMessage` موفق اعلام نمی‌شود.
- ساخت رکورد مدیر اکنون همان اتصال PDO و host واقعی دیتابیس را به‌کار می‌برد و اتصال ثانویه hard-coded به `localhost` حذف شد.
- رمز دیتابیس بدون تغییر sanitizer به PDO می‌رسد، ولی پس از خطا در DOM/HTML بازتاب داده نمی‌شود؛ توکن ربات نیز masked و بدون بازتاب است.
- در نصب‌های زیرپوشه `public_html`، توکن نصب نام‌فضاشده بالاتر از document root ساخته می‌شود تا داخل webroot قرار نگیرد.
- صفحه مجوز نصب کاملاً واکنش‌گرا، دسترس‌پذیر و با Vazirmatn محلی بازطراحی شد. ۸ فایل WOFF2 وزن‌های ۴۰۰، ۵۰۰، ۶۰۰ و ۷۰۰ برای زیرمجموعه عربی/لاتین به همراه مجوز SIL OFL 1.1 در بسته قرار گرفت؛ Google Fonts و فونت قدیمی installer حذف شدند.

## وضعیت اعتبارسنجی این خروجی

- چهار مجموعه generated (`index`، `function`، `admin` و `keyboard`) از source بازسازی و parity آن‌ها تأیید شد.
- parser مستقل `php-parser 3.7.0`: تعداد ۳۳۸ فایل PHP غیرvendor، خطا: صفر.
- مجموعه تست‌های ایستا/قراردادی Python: `TOTAL=38 FAILED=0 PASSED=38`.
- تست اختصاصی جدید installer شامل regression مربوط به IPv4/IPv6، retry و response cap، diagnostics امن، عدم بازتاب credential، PDO مدیر، محل امن token، فایل‌های فونت و مجوز آن است.
- اسکن نهایی کد PHP غیرvendor و غیرcompiled:
  - `file_get_contents()` با URL ثابت شبکه‌ای: صفر
  - `fsockopen()`/`pfsockopen()` خام: صفر
  - redirect خودکار cURL: صفر
  - غیرفعال‌سازی TLS peer/host در cURL/stream: صفر
  - `phpinfo()` و `var_dump()`: صفر
  - وابستگی به Google Fonts: صفر
  - artifact ممنوع: صفر
- هر ۸ فایل Vazirmatn دارای امضای معتبر WOFF2 هستند و فایل مجوز OFL موجود است؛ فونت‌ها، مجوز، دو compiled set تکمیلی و migrationهای `053`/`054` نیز به manifest اجباری release افزوده شدند تا بسته ناقص fail-closed شود.
- parity هر چهار `compiled.php` و قالب‌های `vpnbot/Default` و `vpnbot/update`: موفق.

## تنظیمات و secrets لازم پیش از استقرار

1. کلیدها و secretهای واقعی را در مخزن یا ZIP قرار ندهید؛ آن‌ها را با دسترسی حداقلی در hosting/environment یا storage امن تنظیم کنید.
2. secret وب‌هوک Telegram را با `setWebhook.secret_token` تنظیم و با مقدار ثبت‌شده برنامه یکسان کنید.
3. tokenهای Integration API، reseller، capability اشتراک و کلیدهای درگاه‌ها را تولید/rotate کنید.
4. کلید عمومی معتبر Ed25519 ناشر update را تنظیم کنید؛ بدون امضای معتبر update عمداً fail می‌شود.
5. `REDFOX_DOMAIN` را روی دامنه HTTPS واقعی قرار دهید و در صورت reverse proxy، فقط CIDRهای واقعی proxy را در `REDFOX_TRUSTED_PROXY_CIDRS` ثبت کنید.
6. به‌طور پیش‌فرض endpoint پنل باید HTTPS و دارای IP عمومی باشد. فقط اگر پنل واقعاً روی RFC1918/HTTP قرار دارد، opt-in سطح میزبانی `REDFOX_ALLOW_PRIVATE_PANEL_ENDPOINTS=1` را آگاهانه فعال کنید. این گزینه اجازه HTTP پنل را نیز می‌دهد؛ آن را فقط در شبکه کنترل‌شده و ترجیحاً پشت tunnel امن فعال کنید.
7. مجوز نوشتن را فقط به مسیرهای runtime موردنیاز بدهید؛ فایل‌های config، key و backup نباید از وب قابل‌خواندن باشند.

## مواردی که در این محیط قابل اثبات نبود

- PHP CLI نصب نبود؛ بنابراین `php -l` و اجرای runtime واقعی helperهای PHP قابل اجرا نبود. رفتار جایگزینی `CURLOPT_RESOLVE` در سطح libcurl با cURL مستقل بازتولید و fallback جدید تأیید شد، اما باید روی PHP/cURL همان هاست نیز آزمایش شود.
- MySQL نصب نبود؛ بنابراین اجرای واقعی migrationهای `049` تا `054`، constraintها، transaction/locking و claim lifecycle روی DB آزمایش نشد.
- Composer CLI نصب نبود و `composer.lock` موجود نبود؛ dependency audit با metadata نصب‌شده و OSV انجام شد، نه `composer audit` استاندارد.
- قرارداد و پاسخ واقعی همه درگاه‌ها/پنل‌ها، secretهای production، Telegram واقعی، DNS/TLS production و cPanel در اختیار نبود.
- تست end-to-end پرداخت، provisioning، updater/rollback، backup/restore، cron و webhook واقعی انجام نشد.

## چک‌لیست اجباری staging/cPanel

1. از کل production و DB بکاپ خارج از webroot تهیه و امکان restore را عملاً آزمایش کنید.
2. PHP 8.4+ و extensionهای لازم شامل `curl`, `pdo_mysql`, `mysqli`, `json`, `mbstring`, `openssl`, `sodium`, `zip`, `gd`, `fileinfo`, `dom/xml` را فعال کنید.
3. روی یک DB staging خالی و یک clone ارتقایی، همه migrationها را با کاربر محدود اجرا و schema/index/constraintها را کنترل کنید.
4. `php -l` را روی همه فایل‌های PHP و Composer audit را در همان سرور اجرا کنید.
5. webhook Telegram با secret، replay update، update هم‌زمان و خطای DB را تست کنید.
6. برای هر درگاه: callback معتبر، امضای غلط، provider mismatch، invoice تکراری، مبلغ/ارز غلط و race هم‌زمان را تست کنید.
7. SSRF را با loopback، RFC1918، metadata (`169.254.169.254`)، DNS rebinding و redirect زنجیره‌ای آزمایش کنید.
8. پنل‌های Marzban/Marzneshin/Hiddify/X-UI/S-UI/Mikrotik/IBSng مورد استفاده را جداگانه از staging تست کنید.
9. update امضاشده، امضای خراب، ZIP traversal، قطع update و rollback/restore واقعی را آزمایش کنید.
10. دسترسی وب به `config.php`, `lib/`, `bin/`, `migrations/`, `vendor/`, backupها، logs و uploads اجرایی را از بیرون بررسی کنید.
11. پس از موفقیت staging، secrets قبلی را rotate و انتشار production را با window بازگشت انجام دهید.

## منشأ و تمامیت ورودی

SHA-256 هر دو ZIP ورودی اصلی:

`37ce8afcb2806aafe337191e11158b30bdbc701809f12000474eee6086a614a5`

بسته‌های ورودی امضای قابل‌اعتبارسنجی ناشر، LICENSE مستقل و provenance عمومی قابل‌اثبات نداشتند؛ فقط metadata پروژه ادعای `GPL-3.0-or-later` دارد. پیش از بازتوزیع تجاری، وضعیت مجوز، notices و source متناظر را با مالک حقوقی بررسی کنید.
