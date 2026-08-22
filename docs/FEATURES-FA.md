# فهرست امکانات RedFox

این سند نمای کلی قابلیت‌های موجود در source نسخه 2.4.12 است. فعال‌بودن هر connector یا درگاه به تنظیمات، حساب تجاری و API نسخه مقصد بستگی دارد.

## فروشگاه و چرخه سرویس

- فروش سرویس‌های حجمی، زمانی و ترکیبی؛
- ساخت و تحویل خودکار کانفیگ یا Subscription؛
- تمدید، افزایش حجم و افزایش زمان؛
- اکانت تست/رایگان بر اساس سیاست مدیر؛
- موجودی و انبار سرویس برای مسیرهای دستی یا اضطراری؛
- QR Code واقعی برای import سریع؛
- کنترل stock، وضعیت سفارش و جلوگیری از side effect تکراری؛
- viewهای کاربر برای سرویس‌ها، مصرف، انقضا و راهنما.

## تجربه کاربر تلگرام

- منو و متن‌های قابل‌سفارشی‌سازی؛
- کیف پول و تاریخچه مالی؛
- کد تخفیف، کد هدیه، cashback و affiliate؛
- عضویت اجباری کانال و تأیید شماره تلفن؛
- پشتیبانی، FAQ و آموزش اتصال؛
- اعلان انقضا، وضعیت خرید و پرداخت؛
- Mini App و viewهای وب مرتبط.

## پنل مدیریت وب

- مدیریت مدیران و session ورود محافظت‌شده؛
- مدیریت کاربران، کیف پول، سفارش و تراکنش؛
- مدیریت محصول، دسته، پنل و connector؛
- تنظیمات ربات، Webhook و دامنه؛
- مدیریت پرداخت و رسید کارت‌به‌کارت؛
- پیام انبوه صف‌بندی‌شده؛
- template editor و شخصی‌سازی ظاهر/متن؛
- backup، update، maintenance، health و گزارش‌ها؛
- مدیریت نمایندگان و درخواست‌های نمایندگی؛
- Wizard هاست، Cron و Pre-Certification.

## نمایندگی و سوپرنمایندگی

- درخواست، تأیید، رد و لغو نمایندگی؛
- نماینده عادی و پیشرفته؛
- پورتال وب نماینده و تولید credential تصادفی؛
- محصول‌ها و تنظیمات مستقل؛
- template ربات نماینده و همگام‌سازی فقط با دسترسی مدیر؛
- کنترل اعتبار؛ مسیر فروش نماینده پیشرفته کف موجودی صفر دارد و فروش بدون اعتبار کافی متوقف می‌شود؛
- اعلان هم‌زمان به نماینده و مدیر برای تلاش فروش ناموفق.

## connectorهای شبکه

خانواده connectorهای کد موجود:

- Marzban / Marzneshin / Pasargad؛
- x-ui و گونه‌های سازگار؛
- s-ui؛
- Hiddify؛
- Mikrotik؛
- IBSng؛
- WGDashboard / WireGuard.

برنامه برای برخی endpointها proxy خروجی مجزا فراهم می‌کند. مقصدها تحت policy URL/DNS/IP/TLS ارزیابی می‌شوند. قبل از production باید عملیات create/read/update/delete با نسخه دقیق API پنل مقصد روی یک product آزمایشی تست شود.

## پرداخت

- کارت‌به‌کارت، ثبت رسید، تأیید دستی و تأیید زمان‌بندی‌شده؛
- Zarinpal؛
- AqayePardakht؛
- NowPayments؛
- Plisio با verify hash مطابق payload؛
- Tronado؛
- مسیرهای IranPay/Tetra موجود در کد؛
- callback protection، fingerprint، idempotency و reconciliation queue؛
- unique constraintهای دیتابیس برای جلوگیری از order replay.

> نام connector در source به‌معنی فعال بودن قرارداد تجاری یا تضمین سازگاری دائمی API ثالث نیست.

## زمان‌بندی و عملیات

- Cron orchestration؛
- یادآوری انقضا و اتمام حجم؛
- بررسی payment/receipt؛
- ارسال پیام انبوه صفی؛
- مانیتورینگ panel؛
- backup زمان‌بندی‌شده؛
- reconciliation عملیات بین سیستم و provider؛
- health/ready endpoint و maintenance mode؛
- audit log با redaction داده حساس.

## امنیت کاربردی

- نصب‌کننده دارای توکن یک‌بارمصرف خارج web root؛
- secret store فایل‌محور با مجوز 0600 و write اتمیک؛
- lock سازگار با هاست اشتراکی؛
- session و CSRF hardening؛
- Webhook secret header و محدودیت request؛
- SSRF protection و جلوگیری از destination خصوصی/رزروشده در endpoint عمومی؛
- TLS verification اجباری و جلوگیری از redirect ناامن؛
- updater امضاشده و fail-closed؛
- backup رمز‌شده؛
- شناسه سفارش با full SHA-256 و uniqueness دیتابیس؛
- schema migration idempotent.

## dependencyهای واقعی bundled

- `endroid/qr-code 6.1.3` برای QR PNG؛
- `phpoffice/phpspreadsheet 5.9.0` برای XLSX؛
- `paragonie/sodium_compat v1.24.2` برای fallback رمزنگاری؛
- در مجموع ۱۱ package production با `composer.lock` و vendor منطبق.

## محدودیت‌های عملی

- Telegram Bot API، درگاه‌ها و panelهای ثالث خارج از کنترل repository هستند؛
- DNS عمومی، CA/TLS، firewall و egress policy هاست باید جداگانه صحیح باشند؛
- backup فقط زمانی مفید است که restore آن آزمایش شده باشد؛
- تست release جایگزین تست با credentialها و APIهای واقعی بهره‌بردار نیست؛
- مسئولیت قوانین محلی و شرایط سرویس‌های ثالث با بهره‌بردار است.
