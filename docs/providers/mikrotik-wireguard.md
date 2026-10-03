# MikroTik WireGuard Provider Guide

این راهنما برای ادمینی است که ATD Panel را نصب کرده و می‌خواهد یک MikroTik RouterOS را برای مدیریت WireGuard آماده و به پنل متصل کند.

> دستورات را قبل از اجرا با نسخه RouterOS خود تطبیق دهید. در production، REST روی HTTPS ترجیح داده می‌شود و دسترسی مدیریتی باید به IP سرور ATD Panel محدود شود.

## 1. پیش‌نیازها

- RouterOS دارای WireGuard و REST API مورد نیاز این Provider باشد.
- IP/hostname مدیریتی MikroTik از سرور ATD Panel قابل دسترسی باشد.
- یک حساب اختصاصی برای ATD Panel ساخته شود.
- برای WireGuard یک interface و در صورت نیاز یک IP Pool آماده باشد.

## 2. ساخت کاربر اختصاصی RouteBox

یک کاربر اختصاصی بسازید و فقط policyهای مورد نیاز Provider را اختصاص دهید. نمونه زیر را متناسب با policyهای مورد نیاز نصب خود تکمیل کنید:

```routeros
/user group add name=routebox-policy policy=read,write,rest-api
/user add name=routebox group=routebox-policy password="CHANGE_THIS_PASSWORD"
```

اگر این policyها یا syntax در نسخه RouterOS شما متفاوت است، از policyهای موجود همان نسخه استفاده کنید و از دادن دسترسی بیشتر از نیاز Provider خودداری کنید.

## 3. فعال‌سازی REST API

برای تست کنترل‌شده می‌توان HTTP را فعال کرد؛ برای production استفاده از HTTPS/`www-ssl` توصیه می‌شود:

```routeros
/ip service print
/ip service set [find name=www-ssl] disabled=no
```

پورت سرویس را بررسی کنید و در Firewall فقط IP سرور ATD Panel را مجاز کنید.

## 4. WireGuard

لیست interfaceها و listen-port را بررسی کنید:

```routeros
/interface/wireguard/print detail
```

ATD Panel می‌تواند `listen-port` interface انتخاب‌شده را به‌عنوان WireGuard endpoint port تشخیص دهد؛ بنابراین در صورت استفاده از auto-detect لازم نیست port را دستی وارد کنید.

## 5. افزودن MikroTik در ATD Panel

به **MikroTik WireGuard** بروید و موارد زیر را وارد کنید:

- Host: IP یا hostname عمومی/مدیریتی MikroTik
- API Port: پورت REST
- Username: کاربر اختصاصی RouteBox
- Password: رمز همان کاربر
- TLS: برای HTTPS فعال
- VPN Endpoint: IP/hostname عمومی‌ای که کلاینت WireGuard استفاده می‌کند
- WireGuard Port: خالی برای Auto-detect یا مقدار صریح در صورت نیاز
- Interface: نام WireGuard interface
- Pool: نام IP Pool در صورت استفاده

سپس **Test Connection** را اجرا کنید.

## 6. تست ساخت Peer

بعد از موفقیت Test Connection، یک Plan آزمایشی ایجاد کنید و یک Peer بسازید. فایل Config و QR را دریافت و اتصال را از یک Client تست کنید.

## 7. Firewall

پورت REST را از اینترنت عمومی نکنید. دسترسی مدیریتی را به IP سرور ATD Panel محدود کنید و UDP پورت WireGuard را فقط در صورت نیاز برای Clientها باز بگذارید.

## 8. رفع خطا

- REST connection failed: سرویس REST، پورت و Firewall را بررسی کنید.
- Authentication failed: user/password و policyهای حساب RouteBox را بررسی کنید.
- WireGuard port detected incorrectly: `listen-port` interface را بررسی کنید.
- Peer creation failed: interface، pool، address allocation و permissionهای REST را بررسی کنید.
- Client connects but no traffic: Firewall، routing و DNS تنظیم‌شده برای Peer را بررسی کنید.
