<div dir="rtl" align="right">

# 🚀 RouteBox Telegram Bot

> 🤖 ربات تلگرام + 🖥️ پنل مدیریت مستقل برای RouteBox و AmneziaWG
>
> **نسخه: `0.1.0-beta.1` · وضعیت: 🧪 Beta**

</div>

![Status](https://img.shields.io/badge/status-BETA-orange?style=for-the-badge)
![Version](https://img.shields.io/badge/version-0.1.0--beta.1-blue?style=for-the-badge)
![Ubuntu](https://img.shields.io/badge/Ubuntu-22.04%2B-E95420?style=for-the-badge&logo=ubuntu&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Telegram](https://img.shields.io/badge/Telegram-Bot-26A5E4?style=for-the-badge&logo=telegram&logoColor=white)
![RouteBox](https://img.shields.io/badge/RouteBox-API-111827?style=for-the-badge)

---

<div dir="rtl" align="right">

## 🇮🇷 معرفی پروژه

**RouteBox Telegram Bot** یک Backend مستقل برای مدیریت سرویس‌های AmneziaWG روی چند سرور RouteBox است. تنظیمات ربات از طریق یک پنل وب فارسی و RTL انجام می‌شود و RouteBox اصلی بدون دستکاری فایل‌های داخلی خودش به کار ادامه می‌دهد.

نسخه فعلی **Beta** است و تمرکز آن روی یک مسیر پایدار و قابل تست است.

### ✨ قابلیت‌های فعلی ربات

</div>

```text
👤 Telegram User
        ↓
🤖 Telegram Bot
        ↓
🖥️ Backend / Admin Panel
        ↓
🌐 RouteBox API
        ↓
🔐 AmneziaWG Peer
        ↓
📄 .conf
        ↓
📲 Telegram
```

<div dir="rtl" align="right">

- 🎁 **Trial رایگان** با مدت قابل تنظیم از پنل
- 👤 **حساب کاربری** و نمایش سرویس فعال
- 🔑 **ساخت خودکار Peer** در RouteBox
- 🌍 **Multi-RouteBox**؛ در صورت فعال بودن چند سرور، همان کاربر روی همه سرورها ایجاد می‌شود
- 🆔 نام‌گذاری یکسان Peer بر اساس Telegram ID، مانند `user123456789`
- ⏱️ **Expiration** برای Peer
- 📄 ارسال مستقیم فایل **AmneziaWG `.conf`** برای کاربر
- 🛡️ جلوگیری از استفاده مجدد از Trial
- 🔄 Rollback در صورت شکست Provisioning بین چند سرور
- 📝 ثبت خطاها و رویدادهای مهم

## 🖥️ پنل مدیریت

پنل مستقل است و برای تنظیم Bot لازم نیست وارد Telegram شوید.

### ⚙️ تنظیمات

- 🤖 Telegram Bot Token
- ⏱️ مدت Trial
- 🌐 افزودن چند RouteBox
- 🔌 تست اتصال هر RouteBox
- 🟢 فعال/غیرفعال کردن سرورها
- 👥 مشاهده کاربران
- 📋 مشاهده وضعیت Provisioning
- 📝 لاگ‌های سیستم

### 🔐 امنیت

- Token ربات و Credential سرورهای RouteBox با **libsodium SecretBox** رمزنگاری می‌شوند.
- کلید برنامه در فایل Runtime محلی تولید می‌شود و داخل GitHub قرار نمی‌گیرد.
- رمز مدیر هنگام نصب به‌صورت تصادفی تولید می‌شود.
- فایل Database و تنظیمات Runtime خارج از Web Root نگهداری می‌شوند.
- Redirect خارجی و TLS verification قابل کنترل است؛ برای محیط Production استفاده از HTTPS توصیه می‌شود.
- اطلاعات حساس نباید داخل Issue، Pull Request یا README قرار گیرند.

</div>

---

## 🌍 Multi-Server Architecture

```text
                         📱 Telegram
                              │
                              ▼
                    ┌─────────────────┐
                    │  🤖 Bot Worker  │
                    └────────┬────────┘
                             │
                             ▼
                    ┌─────────────────┐
                    │ 🖥️ Admin Panel  │
                    └────────┬────────┘
                             │
              ┌──────────────┼──────────────┐
              ▼              ▼              ▼
        🌐 RouteBox #1  🌐 RouteBox #2  🌐 RouteBox #3
              │              │              │
             AWG            AWG            AWG
```

<div dir="rtl" align="right">

Backend فقط از API RouteBox استفاده می‌کند و فایل‌های داخلی پنل RouteBox را مستقیماً تغییر نمی‌دهد.

## 📦 نصب سریع — Ubuntu 22.04+

روی یک VPS تمیز Ubuntu 22.04 یا بالاتر، فقط یک دستور لازم است:

</div>

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/install.sh)
```

<div dir="rtl" align="right">

Installer به‌صورت خودکار:

1. 📦 وابستگی‌ها را نصب می‌کند.
2. 📥 پروژه را دریافت می‌کند.
3. 🗄️ SQLite را آماده می‌کند.
4. 🔐 کلید رمزنگاری و رمز مدیر را تولید می‌کند.
5. 🌐 Nginx + PHP-FPM را تنظیم می‌کند.
6. ⚙️ systemd service را فعال می‌کند.
7. 🧪 Syntax check تمام فایل‌های PHP را اجرا می‌کند.
8. ❤️ وضعیت سرویس Bot را بررسی می‌کند.

بعد از نصب، رمز مدیر فقط در خروجی Installer نمایش داده می‌شود؛ آن را ذخیره کنید.

### 📋 دستورات مدیریت

</div>

```bash
# مشاهده لاگ Bot
journalctl -u routebox-telegram-bot -f

# بررسی وضعیت
systemctl status routebox-telegram-bot

# آپدیت
bash /opt/routebox-telegram-bot/update.sh

# حذف
bash /opt/routebox-telegram-bot/uninstall.sh
```

<div dir="rtl" align="right">

> ⚠️ نسخه Beta است. قبل از استفاده عمومی، HTTPS، Firewall و دسترسی پنل را ایمن کنید.

## 🧪 مسیر تست Beta

</div>

```text
/start
  ↓
🎁 دریافت تست رایگان
  ↓
بررسی Trial قبلی
  ↓
🌐 دریافت لیست RouteBoxهای فعال
  ↓
🔎 پیدا کردن Peer با نام user<TelegramID>
  ↓
➕ ساخت Peer در صورت نبودن
  ↓
⏱️ تعیین Expiration
  ↓
📄 دریافت .conf
  ↓
📲 ارسال کانفیگ به Telegram
```

<div dir="rtl" align="right">

در Provisioning چندسروره، اگر یکی از مراحل شکست بخورد، Peerهایی که همین درخواست ایجاد کرده است تا حد امکان Rollback می‌شوند تا از Provision ناقص جلوگیری شود.

---

## 💳 برنامه توسعه پرداخت

### 🇮🇷 فارسی

در به‌روزرسانی‌های بعدی، سیستم فروش و تمدید سرویس به پروژه اضافه خواهد شد، از جمله:

- 💰 **درگاه پرداخت ریالی**
- 🪙 **پرداخت با ارزهای دیجیتال (Crypto)**
- 🔄 تمدید و ارتقای سرویس
- 🎟️ کد تخفیف و کمپین
- 🧾 فاکتور و تاریخچه پرداخت
- 🌍 انتخاب سرور یا Region
- 📊 داشبورد مصرف و وضعیت سرویس

</div>

### 🇬🇧 English

Future releases will add a complete sales and subscription layer, including:

- 💰 **Iranian Rial payment gateway**
- 🪙 **Cryptocurrency / Crypto payments**
- 🔄 Subscription renewal and upgrades
- 🎟️ Discount and campaign codes
- 🧾 Invoices and payment history
- 🌍 Server / Region selection
- 📊 Usage and service dashboards

---

## 🗺️ Roadmap

### v0.1.0-beta.1

- [x] Telegram Bot foundation
- [x] Independent RTL admin panel
- [x] RouteBox API client
- [x] Multiple RouteBox servers
- [x] Same Telegram user identity across servers
- [x] AWG Peer provisioning
- [x] Expiration
- [x] `.conf` delivery
- [x] Encrypted credentials
- [x] Trial abuse protection
- [x] Partial provisioning rollback
- [x] Ubuntu 22.04+ installer
- [x] Update / uninstall scripts
- [x] PHP syntax checks

### 🔜 Future Releases

- [ ] 💳 Iranian Rial payment gateway
- [ ] 🪙 Crypto payment gateway
- [ ] 🔄 Subscription renewal
- [ ] 🛒 Product / plan management
- [ ] 🌍 Region selection
- [ ] 📊 Traffic and usage dashboard
- [ ] 👨‍💼 Advanced user management
- [ ] 🎟️ Coupons and referral system
- [ ] 🔔 Expiration notifications
- [ ] 🌐 Full Persian / English Bot interface
- [ ] 🔗 Subscription links / QR workflow

---

<div dir="rtl" align="right">

## 🧩 سازگاری با RouteBox

این پروژه بر اساس API فعلی RouteBox طراحی شده است. RouteBox در نسخه‌های جدید APIهای `/api/awg/*` برای وضعیت، Peerها، دریافت کانفیگ و Expiration دارد. قابلیت‌های جدید AWG3 و `vpn://` نیز در نسخه‌های جدید RouteBox اضافه شده‌اند. برای جزئیات نسخه‌های RouteBox، مستندات و Changelog رسمی پروژه را بررسی کنید.

> 🧪 چون RouteBox ممکن است در نسخه‌های آینده API خود را تغییر دهد، تست End-to-End با نسخه RouteBox نصب‌شده روی سرور شما بخشی از فرآیند Beta است.

## 🛠️ توسعه و مشارکت

Pull Request و Issue برای گزارش Bug، پیشنهاد قابلیت و بهبود مستندات آزاد است.

برای گزارش Bug بهتر است این موارد را ذکر کنید:

- Ubuntu version
- RouteBox version
- Project version
- PHP version
- متن خطای `journalctl`

❌ هرگز Bot Token، Password، Private Key یا فایل `.conf` واقعی را در Issue یا Pull Request قرار ندهید.

## ⚠️ وضعیت فعلی

**این پروژه Beta است، نه نسخه Production نهایی.** هدف این نسخه تست واقعی زنجیره Telegram → Backend → RouteBox → AmneziaWG است. قابلیت‌های پرداخت و فروش در نسخه‌های بعدی اضافه خواهند شد.

## 📄 مجوز

License نهایی پروژه در حال تعیین است. پیش از استفاده تجاری یا Redistribute کردن پروژه، شرایط License Repository را بررسی کنید.

</div>
