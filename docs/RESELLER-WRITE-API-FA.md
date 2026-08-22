# Write API نمایندگی

## امضای POST

Headerها:

```text
Authorization: Bearer rf_r_...
Content-Type: application/json
X-RF-Timestamp: UNIX_SECONDS
X-RF-Nonce: RANDOM_16_TO_100
X-RF-Signature: HEX_HMAC_SHA256
Idempotency-Key: UNIQUE_16_TO_100
```

Canonical string:

```text
TIMESTAMP\nNONCE\nPOST\n/api/reseller.php\nSHA256_RAW_BODY
```

کلید HMAC همان Bearer Token خام است. اختلاف زمان بیش از ۵ دقیقه، Nonce تکراری، Signature اشتباه یا استفاده از Idempotency Key با بدنه متفاوت رد می‌شود.

## پیام مستقیم

```json
{"action":"message","user_id":"123456789","message":"متن"}
```

Scope لازم: `message_write`.

## حجم یا زمان اضافه

```json
{"action":"service_extra","invoice_id":"ORDER","kind":"volume","amount":10}
```

`kind` برابر `volume` یا `time`. Scope لازم: `service_write`.

## تمدید

```json
{"action":"service_renew","invoice_id":"ORDER","product_code":"P001"}
```

## ساخت محصول

```json
{"action":"product_create","owner":"RESELLER_ID","name":"ماهانه","price":50000,"volume":20,"days":30,"category_id":1}
```

Scope لازم: `product_write`. مقدار owner باید داخل Scope کلید باشد.

## درخواست تسویه

```json
{"action":"settlement_request","amount":500000}
```

Scope لازم: `settlement_write`.

## IP Allowlist

هنگام ساخت کلید می‌توان حداکثر ۱۰ IP دقیق IPv4/IPv6 ثبت کرد. در صورت خالی بودن، محدودیت IP اعمال نمی‌شود.
