# بازیابی امن Webhook تلگرام — RedFox 2.4.12

## رفتار نهایی

شکست ثبت Webhook پس از نصب هسته، دیتابیس و migrationها موجب rollback مخرب نمی‌شود. وضعیت `pending` همراه با کد تشخیصی امن ثبت می‌شود و مدیر می‌تواند بدون نمایش یا تغییر bot token آن را از پنل تعمیر کند.

## روش تعمیر

1. با مدیر اصلی وارد پنل شوید.
2. `panel/bot_settings.php` را باز کنید.
3. وضعیت و کد آخرین تلاش را ببینید.
4. DNS، SSL، مسیر زیرشاخه یا firewall را اصلاح کنید.
5. «تعمیر Webhook» را اجرا کنید.
6. coordinator، URL/DNS/IP/TLS/port را preflight، `setWebhook` را با retry محدود اجرا و نتیجه را با `getWebhookInfo` مستقل تأیید می‌کند.

## کنترل‌های امنیتی

- فقط POST با JSON حداکثر 1MiB؛
- الزام `X-Telegram-Bot-Api-Secret-Token`؛
- رد IP خصوصی/رزروشده برای endpoint عمومی؛
- TLS verification اجباری؛
- پورت‌های 80، 88، 443 و 8443؛
- ارزیابی redirect و جلوگیری از loop/downgrade؛
- ثبت فقط code/fingerprint، نه پاسخ خام یا token؛
- پشتیبانی نصب در زیرشاخه؛
- coordinator مشترک برای ربات اصلی و نماینده.

## کدهای مهم

- `TG-WH-DNS`: DNS عمومی؛
- `TG-WH-TLS`: certificate/chain/SNI؛
- `TG-WH-PORT`: پورت نامجاز؛
- `TG-WH-IP`: IP خصوصی یا reserved؛
- `TG-WH-CONNECT`: اتصال endpoint؛
- `TG-WH-HTTP`: پاسخ HTTP ناموفق؛
- `TG-WH-REDIRECT`: redirect یا loop؛
- `TG-WH-URL` / `TG-WH-PATH`: URL یا مسیر نامعتبر؛
- `TG-WH-SECRET`: secret نامعتبر؛
- `TG-WH-AUTH`: bot token رد شده؛
- `TG-WH-RATE`: rate limit؛
- `TG-WH-UPSTREAM`: اختلال Telegram؛
- `TG-WH-TRANSPORT`: ارتباط خروجی؛
- `TG-WH-UNKNOWN`: خطای دیگر با fingerprint.

## اعتبارسنجی release

Regressionهای Webhook، security runtime، نصب واقعی با Telegram test double و سناریوی recovery کنترل‌شده اجرا شده‌اند. سناریوی recovery کد امن `TG-WH-DNS` را بدون ازبین‌بردن نصب موفق تأیید کرده است. تماس production همچنان باید روی دامنه و bot واقعی بهره‌بردار بررسی شود.

جدول کامل رفع خطا در [`docs/TROUBLESHOOTING-FA.md`](docs/TROUBLESHOOTING-FA.md) است.
