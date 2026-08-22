# سیاست Secretها در RedFox

نسخه جاری برای هاست اشتراکی هم process environment و هم store فایل‌محور حفاظت‌شده را پشتیبانی می‌کند. مسیر runtime اصلی در نصب‌های معمول:

```text
storage/secure.env.php
```

## کنترل‌ها

- فقط کلیدهای allow-list شده پذیرفته می‌شوند؛
- write با فایل موقت، flush و rename اتمیک انجام می‌شود؛
- symlink و permission نامعتبر رد می‌شوند؛
- lock با `flock` و در محیط محدود با fallback اتمیک `mkdir` انجام می‌شود؛
- mode فایل `0600` است؛
- برنامه با غیرفعال بودن `getenv()` و `putenv()` نیز از مسیر مستقیم امن کار می‌کند؛
- مقدار حساس در HTML و log بازنمایی نمی‌شود.

## کلید رمزنگاری

`REDFOX_MASTER_KEY` باید ۳۲ بایت و به‌شکل ۶۴ کاراکتر hex یا Base64 معتبر باشد. ciphertext با پیشوند `rxenc:v1:` مشخص می‌شود. داده قدیمی plaintext ممکن است فقط برای مهاجرت تدریجی خوانده شود، اما باید در اولین فرصت رمز شود.

تغییر master key بدون decrypt/re-encrypt برنامه‌ریزی‌شده ممنوع است و می‌تواند داده را غیرقابل بازیابی کند.

## ممنوعیت‌ها

- commit فایل secret؛
- قراردادن token در URL؛
- permission برابر `777`؛
- ارسال backup/secret در Issue؛
- خاموش‌کردن encryption یا TLS برای رفع خطا.

شرح کامل در [`docs/SECURITY-OPERATIONS-FA.md`](docs/SECURITY-OPERATIONS-FA.md) آمده است.
