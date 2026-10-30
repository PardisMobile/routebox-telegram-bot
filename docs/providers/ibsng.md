# IBSng Provider Guide

این راهنما برای ادمینی است که ATD Panel را نصب کرده و می‌خواهد IBSng را برای Provisioning سرویس آماده و به پنل متصل کند.

## 1. پیش‌نیازها

- نصب و دسترسی مدیریتی به IBSng برقرار باشد.
- ATD Panel بتواند به HTML Web Panel مورد استفاده IBSng دسترسی داشته باشد.
- اطلاعات Authentication و Group/Service مورد نیاز Provisioning مشخص باشد.

## 2. آماده‌سازی IBSng

دسترسی HTTP(S) به Web Panel واقعی IBSng A1.24 را فراهم کنید و در Firewall فقط IP سرور ATD Panel را مجاز کنید. هیچ API فرضی، XML-RPC یا RADIUS برای این ارتباط استفاده نمی‌شود.

## 3. افزودن در ATD Panel

در **IBSng Servers** سرور را اضافه کنید و Host، Web Panel port، Admin Username، Admin Password و تنظیمات HTTP/HTTPS را وارد کنید.

## 4. تست اتصال

**Test Connection** را اجرا کنید. بعد از موفقیت می‌توانید Planهای IBSng را تعریف و Provisioning را تست کنید.

## 5. تست Provisioning

ابتدا یک Plan آزمایشی با قیمت مناسب محیط تست ایجاد کنید، سپس ساخت یک حساب را از Telegram Bot آزمایش کنید و ایجاد Username/Password و Subscription را بررسی کنید.

## 6. رفع خطا

- Authentication failed: حساب API و دسترسی‌های آن را بررسی کنید.
- Timeout: Firewall، DNS و مسیر شبکه را بررسی کنید.
- Group/service error: mapping گروه و سرویس مورد استفاده Plan را بررسی کنید.
- Provisioning error: لاگ عملیات پنل و پاسخ HTML IBSng را بررسی کنید.
