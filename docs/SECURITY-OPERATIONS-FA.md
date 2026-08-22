# امنیت و عملیات RedFox

این سند روش صحیح مدیریت Secret، Webhook، Backup، Update، log و نگهداری دوره‌ای را توضیح می‌دهد.

## ۱. مدل تهدید

کنترل‌های این release روی این خطرها متمرکزند:

- افشای bot token، رمز دیتابیس، webhook/cron secret و کلید backup؛
- نوشتن ناقص یا هم‌زمان فایل تنظیمات روی هاست اشتراکی؛
- SSRF، DNS rebinding، redirect ناامن و TLS bypass؛
- callback تکراری و دوباره‌شارژ شدن؛
- CSRF/session theft در پنل؛
- update دست‌کاری‌شده یا backup غیرقابل بازیابی؛
- log حاوی پاسخ خام provider یا credential؛
- نصب دوباره یا hijack نصب‌کننده.

کنترل برنامه جایگزین patch سیستم‌عامل، امنیت حساب hosting، MFA، WAF صحیح و backup خارج سرور نیست.

## ۲. Secret Store

Secretهای عملیاتی در `storage/secure.env.php` قرار می‌گیرند. این فایل:

- PHP guard دارد و از طریق HTTP نباید خوانده شود؛
- فقط allow-list محدودی از کلیدها را می‌پذیرد؛
- با temporary file امن و atomic rename نوشته می‌شود؛
- در برابر symlink کنترل می‌شود؛
- با lock `flock` یا fallback اتمیک `mkdir` از write هم‌زمان محافظت می‌شود؛
- mode نهایی `0600` دارد؛
- بدون نیاز اجباری به `putenv()` قابل خواندن است.

### قواعد نگهداری

- فایل را commit نکنید؛
- آن را در DocumentRoot عمومی expose نکنید؛
- backup را فقط به‌صورت رمز‌شده و با دسترسی محدود نگه دارید؛
- پس از تغییر ownership/hosting، permission را دوباره بررسی کنید؛
- secret را با `phpinfo`، screenshot یا ticket عمومی ارسال نکنید.

## ۳. کلید Master و Rotation

اگر داده‌ای با `REDFOX_MASTER_KEY` رمز شده باشد، rotation بدون برنامه می‌تواند داده را غیرقابل خواندن کند. کلید باید ۳۲ بایت باشد و به‌شکل ۶۴ کاراکتر hex یا Base64 معتبر وارد شود.

روند rotation:

1. maintenance mode؛
2. backup رمز‌شده و snapshot دیتابیس؛
3. decrypt با کلید فعلی در محیط کنترل‌شده؛
4. re-encrypt با کلید جدید؛
5. تعویض اتمیک secret؛
6. runtime test؛
7. حذف امن نسخه‌های plaintext/کلید قدیمی پس از پایان بازه rollback.

هرگز ابتدا کلید را تغییر ندهید و بعداً سراغ re-encryption نروید.

## ۴. Webhook

- فقط HTTPS معتبر برای production؛
- secret token هدر `X-Telegram-Bot-Api-Secret-Token`؛
- فقط POST و JSON؛
- محدودیت body برابر 1MiB؛
- URL عمومی بدون IP private/reserved؛
- پورت‌های سازگار Telegram: 80، 88، 443 و 8443؛
- نتیجه ثبت با `getWebhookInfo` تأیید می‌شود.

کد خام خطای Telegram یا token در UI/log نشان داده نمی‌شود؛ کد allow-list و fingerprint برای تشخیص استفاده می‌شود.

## ۵. Session، CSRF و Proxy

- پنل باید فقط روی HTTPS باشد؛
- cookieهای session باید Secure/HttpOnly/SameSite بمانند؛
- فرم‌های state-changing باید CSRF سمت سرور داشته باشند؛
- trusted proxy فقط وقتی تنظیم شود که IP proxy واقعاً ثابت و تحت کنترل باشد؛
- headerهای Forwarded را از هر client نپذیرید؛
- پس از اولین ورود رمز موقت مدیر را تعویض و نشست‌های ناشناخته را invalidate کنید.

## ۶. اتصال خروجی و SSRF

Policy خروجی مقصد را پیش و پس از redirect ارزیابی می‌کند. برای رفع خطا این موارد ممنوع است:

- خاموش‌کردن `CURLOPT_SSL_VERIFYPEER`؛
- اجازه‌دادن عمومی به `localhost`، RFC1918 یا link-local؛
- استفاده از HTTP برای credential؛
- trust کردن hostname بدون resolution/IP validation؛
- قرار دادن URL دلخواه کاربر مستقیماً در cURL.

اگر connector واقعاً در شبکه خصوصی است، آن را در سازوکار صریح و محدود connector/trusted destination تنظیم کنید، نه با حذف policy عمومی.

## ۷. Backup و Restore

- backup باید رمز‌شده باشد؛
- کلید backup را جدا از فایل backup نگه دارید؛
- حداقل یک copy خارج hosting و ترجیحاً immutable داشته باشید؛
- retention تعریف کنید: نمونه روزانه ۷، هفتگی ۴ و ماهانه ۳؛
- restore را دوره‌ای روی staging آزمایش کنید؛
- checksum و زمان backup را ثبت کنید؛
- backup شامل secret را مانند credential production محافظت کنید.

Restore موفق یعنی برنامه، schema، uploadها، secretها، webhook و cron همگی پس از بازیابی بررسی شوند؛ import SQL به‌تنهایی کافی نیست.

## ۸. Update

Updater fail-closed است. اگر trusted public key پیکربندی نشده یا signature معتبر نباشد، update متوقف می‌شود. این رفتار را bypass نکنید.

قبل از update:

1. release source و checksum را بررسی کنید؛
2. backup/restore آزمایشی داشته باشید؛
3. pending payment و queue را ثبت کنید؛
4. staging را ارتقا دهید؛
5. maintenance window تعریف کنید.

بعد از update:

- migration state؛
- health/ready؛
- webhook؛
- cron؛
- QR/XLSX؛
- یک پرداخت sandbox و connector آزمایشی؛
- log و queue را کنترل کنید.

## ۹. Logging و حریم داده

Log مفید باید timestamp، component، error code، request/fingerprint و نتیجه را داشته باشد، اما نباید این داده‌ها را ذخیره کند:

- bot token و webhook secret؛
- password دیتابیس و پنل؛
- private/master/backup key؛
- Authorization/Cookie کامل؛
- raw provider response حاوی credential؛
- اطلاعات شخصی غیرضروری کاربر.

دسترسی log را محدود، retention را کوتاه و rotation را فعال کنید.

## ۱۰. برنامه نگهداری

### روزانه

- health/ready و وضعیت Webhook؛
- شکست Cron/queue؛
- payment pending/reconciliation؛
- ظرفیت دیسک و آخرین backup.

### هفتگی

- تست نمونه restore یا دست‌کم decrypt/list backup؛
- log خطا، login مشکوک و admin list؛
- connectorها و انقضای SSL.

### ماهانه

- Composer/security advisory review؛
- update سیستم‌عامل/PHP؛
- مرور دسترسی مدیران و hosting؛
- rotation secretهای لازم طبق policy؛
- disaster recovery drill.

## ۱۱. پاسخ به رخداد

1. دسترسی مشکوک را محدود و maintenance mode را فعال کنید؛
2. evidence/log را بدون انتشار credential حفظ کنید؛
3. token ربات، credential دیتابیس/درگاه و sessionها را حسب رخداد revoke/rotate کنید؛
4. Webhook و DNS را بررسی کنید؛
5. integrity source را با release/checksum مقایسه کنید؛
6. از backup سالم restore یا سیستم را rebuild کنید؛
7. علت و بازه زمانی را ثبت و پس از رفع، monitoring را تشدید کنید.

آسیب‌پذیری source را مطابق [SECURITY.md](../SECURITY.md) به‌صورت خصوصی گزارش کنید.
