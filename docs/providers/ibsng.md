# IBSng Provider Guide

این راهنما برای ادمینی است که ATD Panel را نصب کرده و می‌خواهد IBSng را برای Provisioning سرویس آماده و به پنل متصل کند.

## 1. پیش‌نیازها

- نصب و دسترسی مدیریتی به IBSng برقرار باشد.
- ATD Panel بتواند به API/endpoint مورد استفاده IBSng دسترسی داشته باشد.
- اطلاعات Authentication و Group/Service مورد نیاز Provisioning مشخص باشد.

## 2. آماده‌سازی IBSng

دسترسی API را طبق نسخه IBSng خود فعال کنید و در Firewall فقط IP سرور ATD Panel را مجاز کنید.

## 3. افزودن در ATD Panel

در **IBSng Servers** سرور را اضافه کنید و Host، API Port، Username، Password و تنظیمات TLS/Connection را وارد کنید.

## 4. تست اتصال

**Test Connection** را اجرا کنید. بعد از موفقیت می‌توانید Planهای IBSng را تعریف و Provisioning را تست کنید.

## 5. تست Provisioning

ابتدا یک Plan آزمایشی با قیمت مناسب محیط تست ایجاد کنید، سپس ساخت یک حساب را از Telegram Bot آزمایش کنید و ایجاد Username/Password و Subscription را بررسی کنید.

## 6. رفع خطا

- Authentication failed: حساب API و دسترسی‌های آن را بررسی کنید.
- Timeout: Firewall، DNS و مسیر شبکه را بررسی کنید.
- Group/service error: mapping گروه و سرویس مورد استفاده Plan را بررسی کنید.
- Provisioning error: لاگ Worker و پاسخ API را بررسی کنید.
