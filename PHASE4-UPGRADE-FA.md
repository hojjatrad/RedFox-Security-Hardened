# Red Fox 1.3 — پیام‌رسانی، گردش مالی و API نمایندگی

## ارتقا

```bash
php bin/migrate.php --dry-run
php bin/migrate.php
```

Migration جدید: `004_messaging_finance_api.sql`.

## Cron صف پیام

روش توصیه‌شده، اجرای CLI هر دقیقه است:

```cron
* * * * * /usr/bin/php /var/www/html/redfox/cron/reseller_messages.php >/dev/null 2>&1
```

اگر فقط HTTP Cron در دسترس است، یک Secret تصادفی در Environment با نام `REDFOX_CRON_SECRET` قرار دهید و آن را فقط در Header `X-Cron-Secret` بفرستید. قرار دادن Secret در URL/query string ممنوع است. CLI به Secret نیاز ندارد.

## کنترل پیام نمایندگان

- دسترسی `broadcast` باید توسط ادمین فعال شود.
- پیام مستقیم فقط به مشتری Scope‌شده پذیرفته می‌شود.
- پیام همگانی هنگام ایجاد، مخاطبان Scope را snapshot می‌کند.
- حداکثر ۳ Broadcast در روز و ۵۰۰۰ مخاطب در هر صف.
- پیام‌ها Plain Text هستند و Parse Mode ندارند.
- صف Retry با backoff نمایی و حداکثر ۵ تلاش دارد.
- اگر Worker پس از Claim قطع شود، پیام خودکار دوباره ارسال نمی‌شود و پس از ۱۵ دقیقه وارد `needs_reconcile` می‌شود؛ ادمین درباره «قبلاً ارسال شده» یا «ارسال مجدد» تصمیم می‌گیرد تا ریسک پیام تکراری کنترل شود.
- مشتری از bot مربوط به خودش پیام می‌گیرد؛ برای مشتری اصلی از Bot اصلی استفاده می‌شود.
- ادمین از «پیام و API نمایندگان» می‌تواند صف را لغو کند.

## API نمایندگی

ادمین ابتدا دسترسی `api` را فعال می‌کند. نماینده از پورتال کلید با عمر حداکثر ۹۰ روز می‌سازد. مقدار خام فقط یک بار نمایش داده می‌شود و دیتابیس فقط SHA-256 آن را نگه می‌دارد.

```http
GET /api/reseller.php?ep=stats
Authorization: Bearer rf_r_...
```

Endpointها:

- `stats`
- `customers&page=1&limit=50`
- `invoices&page=1&limit=50`

هر کلید Scope جدا دارد، فقط GET است، اطلاعات Secret/پنل/شماره تلفن را برنمی‌گرداند و Rate Limit آن ۱۲۰ درخواست در دقیقه برای ترکیب Key+IP است.

## گردش مالی

صفحه `portal/finance.php` Ledger عملیات نماینده و زیرنمایندگان مستقیم را نمایش می‌دهد و CSV می‌دهد. عملیات سرویس و انتقال اعتبار قبلی در همین گزارش دیده می‌شوند.

## نظارت ادمین

`panel/reseller_security.php`:

- مشاهده و لغو صف پیام نماینده؛
- مشاهده و لغو فوری API Key نماینده.

## تست Staging لازم

- ارسال مستقیم از Bot اصلی و Bot نماینده؛
- Block شدن ربات توسط کاربر و رسیدن صف به failed بعد از ۵ تلاش؛
- لغو صف وسط پردازش؛
- Broadcast سوپرنماینده و کنترل نبود مشتری خارج Scope؛
- انقضا و لغو API Key؛
- تست Rate Limit؛
- اجرای هم‌زمان دو Worker و اطمینان از نبود ارسال تکراری.
