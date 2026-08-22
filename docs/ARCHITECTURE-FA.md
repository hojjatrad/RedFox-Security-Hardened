# معماری و اجزای RedFox

RedFox یک برنامه PHP چندبخشی با معماری legacy include-based و لایه‌های hardening افزوده‌شده است. این سند برای نگهدارنده‌ای است که می‌خواهد مسیرهای اصلی را سریع پیدا کند.

## نقشه مسیرها

| مسیر | مسئولیت |
|---|---|
| `index.php` و bootstrapهای ریشه | ورودی Webhook اصلی و bootstrap برنامه |
| `installer/` | احراز نصب، فرم، migration، ایجاد secret و finalization |
| `panel/` | پنل مدیر، تنظیمات، عملیات، backup/update و recovery |
| `portal/` | پورتال نماینده/کاربر |
| `vpnbot/` | botها و templateهای نمایندگی |
| `api/` | endpointهای API داخلی/خارجی |
| `payment/` و مسیرهای callback | درگاه‌ها و state machine پرداخت |
| `cronbot/` و مسیرهای Cron | jobهای زمان‌بندی‌شده و صف |
| `database/` | schema، migrationها و adapterهای دیتابیس |
| `storage/` | فایل‌های عملیاتی حفاظت‌شده؛ قابل نوشتن و غیرعمومی |
| `updates/` | staging کنترل‌شده update |
| `vendor/` | dependencyهای Composer production |
| `tests/` | regression، runtime، concurrency و security tests |
| `bin/` | builder رسمی release و ابزار gate |
| `docs/` | مستندات نگهداری و انتشار |

## پیکربندی و Secret

خواندن تنظیمات حساس از لایه مرکزی HostingSecrets انجام می‌شود. اولویت منطقی بسته به کلید می‌تواند شامل process environment و store فایل‌محور باشد، اما برنامه برای محیط‌هایی که `getenv`/`putenv` غیرفعال است نیز مسیر معتبر دارد. writer فقط allow-list کلیدهای شناخته‌شده را می‌پذیرد، write را با lock و rename انجام می‌دهد و mode فایل را روی `0600` نگه می‌دارد.

Secretها نباید داخل repository باشند. فایل runtime اصلی `storage/secure.env.php` است و توسط `.gitignore` پوشش داده می‌شود.

## دیتابیس و Migration

- PDO/MySQL مسیر اصلی اتصال است؛
- migrationها idempotent طراحی شده‌اند؛
- release حاضر migrationهای `001` تا `056` را دارد؛
- migration 055 وضعیت recovery وب‌هوک را نگه می‌دارد؛
- migration 056 uniqueness کامل شناسه سفارش را با SHA-256 باینری ۳۲بایتی enforce می‌کند؛
- سه unique index برای قراردادهای order/payment وجود دارد.

نام یا ترتیب migration اجراشده را دستی تغییر ندهید. schema باید با runner رسمی به‌روزرسانی شود.

## Webhook

ورودی Telegram فقط POST، JSON محدود و secret header صحیح را می‌پذیرد. URL عمومی با شناخت مسیر نصب در زیرشاخه ساخته می‌شود. ثبت یا repair توسط coordinator مرکزی انجام و با `getWebhookInfo` تأیید مستقل می‌شود. شکست ثبت پس از نصب هسته به حالت `pending` می‌رود و نصب موفق دیتابیس را نابود نمی‌کند.

## HTTP خروجی

اتصال به Telegram، panel و gateway از policy مشترک استفاده می‌کند:

1. parse و normalize URL؛
2. scheme و port allow-list؛
3. DNS resolution؛
4. رد IP خصوصی، loopback، link-local و reserved برای مقصد عمومی؛
5. TLS verification و CA معتبر؛
6. redirect محدود و ارزیابی دوباره مقصد؛
7. timeout و طبقه‌بندی خطای امن.

Proxyهای مورداعتماد باید صریح پیکربندی شوند. نباید برای دورزدن policy، verification خام cURL را غیرفعال کرد.

## پرداخت و Idempotency

Callback ابتدا اعتبار/امضا را بررسی و سپس انتقال وضعیت سفارش را انجام می‌دهد. unique index و fingerprint جلوی اجرای دوباره side effect را می‌گیرد. کارهای نامطمئن برای reconciliation صف می‌شوند. اضافه‌کردن gateway جدید باید قراردادهای success/failure/retry، verification و amount/currency را پوشش دهد.

## Release pipeline

فایل `release-required-files.json` قرارداد presence و non-empty بودن اجزای حیاتی است. `bin/build-release.py` به‌صورت fail-closed موارد زیر را اجرا/بررسی می‌کند:

- suiteهای Python؛
- lint همه فایل‌های PHP؛
- runtimeهای وابستگی و امنیت؛
- parity مجموعه‌های compiled؛
- composer lock/vendor؛
- manifest فایل‌های اجباری؛
- حذف artifactها و secretهای runtime؛
- ساخت ZIP reproducible در حد metadata کنترل‌شده.

هر ZIP تولیدشده خارج از builder رسمی، release پشتیبانی‌شده محسوب نمی‌شود.

## الگوی توسعه امن

1. تغییر کوچک و متمرکز ایجاد کنید؛
2. ورودی را در مرز سیستم validate کنید؛
3. SQL را parameterized نگه دارید؛
4. secret را log نکنید؛
5. مسیرهای compiled/source را در صورت نیاز همگام کنید؛
6. regression متناسب اضافه کنید؛
7. gate کامل و builder را اجرا کنید؛
8. روی staging با API واقعی تست کنید؛
9. release را با checksum و notes منتشر کنید.
