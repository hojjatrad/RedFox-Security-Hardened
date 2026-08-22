# مشارکت در RedFox

## روند پیشنهادی

1. Issue غیرحساس یا شرح تغییر ایجاد کنید؛ موارد امنیتی فقط طبق `SECURITY.md`.
2. branch جدا و commitهای کوچک بسازید.
3. ورودی‌ها، permission، secret redaction و compatibility هاست اشتراکی را در طراحی لحاظ کنید.
4. در صورت تغییر رفتار، test و مستند فارسی را هم به‌روز کنید.
5. gate کامل را پیش از Pull Request اجرا کنید.

## آزمون‌های پایه

```bash
composer validate --strict
find . -path './vendor' -prune -o -name '*.php' -type f -print0 | xargs -0 -n1 php -l
find tests -maxdepth 1 -name '*_test.py' -type f -print0 | sort -z | xargs -0 -n1 python3
php tests/runtime_core.php
php tests/runtime_dependencies.php
php tests/runtime_security.php
php tests/runtime_hosting_secrets.php
php tests/runtime_hosting_secrets_concurrency.php
php tests/runtime_hosting_secrets_concurrency.php --fallback
```

Builder نهایی:

```bash
python3 bin/build-release.py /tmp/redfox-release.zip
```

## قواعد کد

- SQL باید parameterized باشد؛
- secret یا raw provider response را log نکنید؛
- TLS verification و SSRF policy را bypass نکنید؛
- تغییر schema فقط با migration idempotent؛
- مسیرهای compiled/source باید مطابق manifest همگام باشند؛
- dependency جدید باید در `composer.lock`، vendor، runtime test و license review پوشش داده شود؛
- تغییر پرداخت باید idempotency، retry و reconciliation را پوشش دهد؛
- از commit کردن artifact، backup و credential جلوگیری کنید.

## مجوز مشارکت

با ارسال مشارکت تأیید می‌کنید که حق ارائه آن را دارید و آن را تحت مجوز `GPL-3.0-or-later` پروژه ارائه می‌کنید.
