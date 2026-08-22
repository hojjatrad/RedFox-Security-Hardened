# 🦊 RedFox 2.4.12 — سامانه فروش و مدیریت سرویس در تلگرام

[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-777BB4)](https://www.php.net/)
[![License: GPL v3+](https://img.shields.io/badge/License-GPLv3%2B-blue.svg)](LICENSE)
[![Security Hardened](https://img.shields.io/badge/Security-Hardened-16a34a)](SECURITY.md)

**RedFox** یک سامانه PHP برای فروش خودکار سرویس‌های VPN از طریق ربات تلگرام، پنل مدیریت وب، مدیریت نمایندگان، پرداخت، عملیات زمان‌بندی‌شده، backup و update امن است. این نسخه یک فورک بازطراحی‌شده از پروژه متن‌باز Faoxima است و با مجوز `GPL-3.0-or-later` توزیع می‌شود.

> این repository شامل source کامل و dependencyهای vendor قفل‌شده است تا نصب روی هاست اشتراکی بدون Composer نیز ممکن باشد. فایل ZIP آماده نصب در بخش **Releases** قرار دارد.

## وضعیت نسخه

| مورد | مقدار |
|---|---|
| نسخه محصول | `2.4.12-redfox-dedicated-template-editor` |
| نسخه انتشار | `v2.4.12-security-hardened` |
| حداقل PHP | `8.4` |
| دیتابیس | MySQL / MariaDB با `utf8mb4` |
| وب‌سرور | Apache/LiteSpeed یا Nginx |
| HTTPS | اجباری برای Webhook واقعی تلگرام |
| Cron | برای عملیات دوره‌ای اجباری |

## راه‌های سریع

- [راهنمای کامل نصب](docs/INSTALLATION-FA.md)
- [فهرست امکانات](docs/FEATURES-FA.md)
- [معماری و اجزای برنامه](docs/ARCHITECTURE-FA.md)
- [امنیت، Secretها، Backup و Update](docs/SECURITY-OPERATIONS-FA.md)
- [رفع خطا و کدهای تشخیصی](docs/TROUBLESHOOTING-FA.md)
- [گزارش تست این انتشار](docs/TEST-REPORT-2.4.12-FA.md)
- [یادداشت انتشار](docs/RELEASE-NOTES-2.4.12-FA.md)
- [سیاست گزارش آسیب‌پذیری](SECURITY.md)

## امکانات اصلی

### فروش و مدیریت کاربر

- فروش خودکار سرویس و تحویل کانفیگ/Subscription؛
- محصول‌های حجمی، زمانی، ترکیبی، تمدید و افزایش حجم/زمان؛
- اکانت تست، کیف پول، کد هدیه، تخفیف، cashback و affiliate؛
- مشاهده سرویس، مصرف، تاریخ انقضا، وضعیت اتصال و QR Code؛
- عضویت اجباری کانال، احراز شماره تلفن و پیام‌های قابل سفارشی‌سازی؛
- Telegram Mini App و پنل وب کاربر/نماینده.

### پنل‌ها و سرویس‌دهنده‌ها

اتصال‌های موجود شامل خانواده‌های زیر است:

- Marzban، Marzneshin و سازگارهای مرتبط؛
- x-ui، Alireza و s-ui؛
- Hiddify؛
- Mikrotik؛
- IBSng؛
- WGDashboard / WireGuard.

> سازگاری نهایی هر connector باید با نسخه API پنل مقصد و یک حساب آزمایشی read-only بررسی شود.

### پرداخت

- کارت‌به‌کارت و گردش تأیید رسید؛
- Zarinpal و AqayePardakht؛
- NowPayments، Plisio و Tronado؛
- مسیرهای IranPay/Tetra و callbackهای امضاشده موجود در برنامه؛
- state machine پرداخت، idempotency و صف reconciliation برای کاهش دوباره‌کاری side effect.

### مدیریت و نمایندگی

- پنل مدیریت چندبخشی برای کاربران، محصول‌ها، مالی، پنل‌ها و تنظیمات؛
- مدیریت نماینده و سوپرنماینده، اعتبار، محصول و پورتال اختصاصی؛
- قالب bot نماینده با همگام‌سازی کنترل‌شده؛
- پیام همگانی صف‌بندی‌شده، اعلان مدیر و مرکز پشتیبانی؛
- ویرایش template پیام‌ها و گزارش‌ها؛
- Pre-Certification هاست و connectorها.

### عملیات

- migrationهای idempotent تا شماره `056`؛
- backup رمز‌شده و restore کنترل‌شده؛
- updater امضاشده و fail-closed؛
- health/ready endpoint، maintenance mode و audit log؛
- Cron orchestrator، صف پیام، reconciliation و مانیتورینگ؛
- نصب در ریشه دامنه یا زیرشاخه.

## سخت‌سازی مهم این انتشار

- Secretهای عملیاتی در `storage/secure.env.php` با mode اجباری `0600`؛
- writer اتمیک با temporary file + rename و lock چندپردازه؛
- پشتیبانی از `flock` و fallback اتمیک `mkdir` روی هاست محدود؛
- اجرای برنامه بدون وابستگی اجباری به `putenv()` یا `getenv()`؛
- احراز installer با توکن یک‌بارمصرف خارج از web root؛
- عدم انعکاس توکن ربات یا رمز دیتابیس در HTML/log؛
- Webhook دارای secret header و recovery غیرمخرب؛
- policy مرکزی SSRF/DNS/IP/TLS برای اتصال‌های خروجی؛
- CSRF، session hardening، security header و trusted proxy صریح؛
- سه قرارداد uniqueness دیتابیس، شامل SHA-256 کامل شناسه سفارش طولانی؛
- dependencyهای Composer قفل‌شده و bundled با lock/vendor parity؛
- حذف API قدیمی `PngBuilder` و smoke test واقعی QR/XLSX.

## نصب سریع روی هاست اشتراکی

1. ZIP را از Releases دریافت و SHA-256 را بررسی کنید.
2. فایل را در مسیر نهایی مانند `public_html/bot` استخراج کنید.
3. یک دیتابیس و کاربر MySQL/MariaDB با دسترسی کامل روی همان دیتابیس بسازید.
4. مسیر زیر را فقط با HTTPS باز کنید:

   ```text
   https://example.com/bot/installer/
   ```

5. مقدار فایل `.redfox-install-token` را که installer بیرون از web root می‌سازد، از File Manager بخوانید و در فرم وارد کنید. **توکن را در URL قرار ندهید.**
6. آیدی عددی مدیر، توکن ربات، اطلاعات دیتابیس و URL نهایی Webhook را ثبت کنید.
7. بعد از نصب، رمز موقت پنل را از پیام ربات دریافت و فوراً عوض کنید.
8. Cronهای تولیدشده در `پنل مدیریت ← Wizard هاست` را در کنترل‌پنل هاست ثبت کنید.
9. اگر Webhook در وضعیت pending بود، از `panel/bot_settings.php` گزینه «تعمیر Webhook» را اجرا کنید.

راهنمای جزئی و سناریوهای cPanel/DirectAdmin/VPS در [راهنمای نصب](docs/INSTALLATION-FA.md) آمده است.

## نصب توسعه‌دهنده

```bash
git clone https://github.com/hojjatrad/RedFox-Security-Hardened.git
cd RedFox-Security-Hardened
composer install --no-dev --prefer-dist
find . -path './vendor' -prune -o -name '*.php' -type f -print0 | xargs -0 -n1 php -l
find tests -maxdepth 1 -name '*_test.py' -type f -print0 | sort -z | xargs -0 -n1 python3
php tests/runtime_core.php
php tests/runtime_dependencies.php
php tests/runtime_security.php
php tests/runtime_hosting_secrets.php
php tests/runtime_hosting_secrets_concurrency.php
php tests/runtime_hosting_secrets_concurrency.php --fallback
```

ساخت release فقط با gate رسمی:

```bash
python3 bin/build-release.py /path/to/RedFox-release.zip
```

Builder حضور فایل‌های اجباری، تطابق compiled/source، تطابق composer.lock/vendor، Python suites و PHP lint را کنترل می‌کند.

## نکات امنیتی ضروری

- `storage/secure.env.php`، backup key، bot token و رمز دیتابیس را commit یا ارسال نکنید.
- TLS verification، Webhook secret یا outbound URL policy را برای «رفع موقت خطا» غیرفعال نکنید.
- updater بدون trusted public key عمداً غیرفعال می‌ماند.
- قبل از migration، update یا restore از دیتابیس و storage backup بگیرید.
- repository و release جایگزین تست پرداخت و connector واقعی شما نیست؛ ابتدا روی staging بررسی کنید.
- هیچ توکن GitHub، Telegram، دیتابیس یا درگاه پرداخت نباید در Issue عمومی قرار گیرد.

## تست این انتشار

خلاصه آخرین gate محلی:

- ۳۹ فایل Python regression: بدون شکست؛
- ۳۴۳ فایل PHP application/test: lint و parser بدون خطا؛
- runtime core، dependency، security و secret store: موفق؛
- concurrency با `flock` و fallback `mkdir`: موفق؛
- نصب واقعی روی MariaDB خالی با PHP 8.4 و `getenv,putenv` غیرفعال: ۵۵ migration؛
- تست قرارداد hash کامل سفارش و سه unique index: موفق؛
- Composer advisory audit: بدون advisory شناخته‌شده؛
- ZIP CRC و audit traversal/symlink/duplicate/secret: موفق.

جزئیات و محدودیت‌ها در [گزارش تست](docs/TEST-REPORT-2.4.12-FA.md) ثبت شده‌اند.

## مجوز و سلب مسئولیت

این نرم‌افزار تحت [GNU GPL v3 یا نسخه‌های بعدی](LICENSE) ارائه می‌شود. متن copyright و attribution فایل‌های third-party باید حفظ شود. نرم‌افزار بدون ضمانت ارائه می‌شود. مسئولیت رعایت قوانین محلی، شرایط Telegram، قوانین مالی/پرداخت و مجوز ارائه سرویس با بهره‌بردار است.
