# IBSng Architecture & Renewal Discovery (RouteBox)

> این سند ثبت یافته‌ها و تصمیم‌های معماری دربارهٔ اتصال RouteBox به IBSng است. هدف آن حفظ دانش کشف‌شده در بررسی‌های امروز و جلوگیری از بازکشف یا تغییر ناخواستهٔ مسیرهای موجود است.
>
> **وضعیت:** سند نیازمندی‌ها و یافته‌های معماری؛ قبل از پیاده‌سازی جزئیات درخواست‌های HTTP باید با سورس IBSng A1.24 و کد فعلی RouteBox تطبیق نهایی داده شود. مواردی که هنوز با کد/آرشیو خط‌به‌خط تأیید نشده‌اند، صریحاً به‌عنوان مورد بررسی ثبت شده‌اند.

## 1. محدوده و اصل محافظت از رفتار موجود

- این قابلیت بخشی از خود RouteBox است؛ IBSng یک اتصال/ارائه‌دهندهٔ سرویس در معماری موجود است، نه سایت جدا و نه پروژه‌ای که باید جایگزین شود.
- پیاده‌سازی فعلی IBSng در RouteBox قبلاً کار کرده و تست شده است. تغییرات باید افزایشی و محدود باشند؛ مسیرهای سالم موجود نباید بازنویسی یا خراب شوند.
- ارتباط با IBSng باید از **HTTP/Web Panel و کلاینت/سشن موجود RouteBox** انجام شود. از اتصال مستقیم دیتابیس IBSng یا جایگزینی معماری با XML-RPC/JSON-RPC استفاده نشود.
- تنظیمات و گروه‌های IBSng (Group) در خود IBSng مدیریت می‌شوند؛ RouteBox نباید گروه‌ها را بسازد یا تنظیماتشان را دستکاری کند.
- **Worker خارج از محدودهٔ این مرحله است.** فعلاً هیچ گزینه یا منطق تمدیدی به Worker اضافه و هیچ Worker یا سرویس/تایمر مرتبطی تغییر داده نشود. پس از تکمیل تمدید در مسیرهای مجاز، جداگانه بررسی شود آیا Worker خودکار گزینهٔ تمدید را دریافت می‌کند یا خیر.

## 2. اتصال طرح RouteBox به IBSng

- طرح IBSng در RouteBox به نام دقیق IBSng Group متصل است؛ مقدار نگاشت‌شده باید همان نام واقعی Group باشد.
- برای طرح‌های IBSng فیلد **مدت اعتبار (Validity duration)** بر حسب روز لازم است.
- افزودن این فیلد نباید طرح‌های قبلی را خراب کند: migration باید backward-compatible باشد و مقدار طرح‌های موجود طبق سیاست سازگار (مثلاً nullable/پیش‌فرض امن) مدیریت شود.
- این مدت، مبنای محاسبهٔ تمدید است؛ منطق عمومی طرح‌های providerهای دیگر نباید تغییر کند.

## 3. دو مسیر تمدید با هویت و مجوز متفاوت

### 3.1 تمدید از پنل ادمین بات

- ادمین می‌تواند **هر حساب IBSng** را تمدید کند، حتی اگر حساب بیرون RouteBox ساخته شده باشد یا به هیچ subscription داخلی RouteBox متصل نباشد.
- ادمین username را وارد می‌کند؛ سپس از میان طرح‌های IBSng تعریف‌شده در بخش ادمین، طرح را انتخاب می‌کند.
- در این مسیر از کاربر IBSng رمز عبور خواسته نمی‌شود. درخواست IBSng با سشن/اعتبار ادمین RouteBox انجام می‌شود.
- نباید وجود subscription داخلی RouteBox شرط تمدید حساب IBSng باشد.

### 3.2 تمدید توسط کاربر نهایی در My Services

- کاربر از بخش **My Services** سرویس خودش را انتخاب و Renew را می‌زند.
- از رابطهٔ ذخیره‌شدهٔ سرویس با حساب IBSng استفاده شود؛ username/password مجدداً از کاربر پرسیده نشود.
- مالکیت سرویس باید با هویت Telegram کاربر جاری بررسی شود. کاربر نباید بتواند سرویس کاربر دیگری را تمدید کند.
- این مسیر فقط سرویس/اشتراک متعلق به همان کاربر را تمدید می‌کند.

### 3.3 تمایز با خرید سایت

- جریان خرید/تمدید وب‌سایت که username/password می‌گیرد، مختص وب‌سایت است.
- آن الگو نباید به بات تعمیم داده شود؛ بات از سشن ادمین و پیوند حساب ذخیره‌شده استفاده می‌کند.

## 4. رفتار تمدید IBSng بر اساس وضعیت حساب

### حساب منقضی‌شده

- **First Login ریست شود و اعتبار تازه به حساب تعلق بگیرد.**
- Relative Expiration از مبنای صفر محاسبه شود و برابر مدت اعتبار پلن + روزهای هدیه باشد؛ اعتبار منقضی‌شدهٔ قبلی به محاسبه اضافه نشود.
- **Absolute Expiration تغییر نکند.**
- بنابراین تمدید حساب منقضی هم شامل مدت پلن و هدیه است؛ تفاوت آن با حساب فعال، ریست First Login و شروع محاسبهٔ Relative Expiration از صفر است.

### حساب فعال

- اعتبار مؤثر باقی‌مانده حفظ شود.
- مدت طرح + روزهای هدیه به Relative Expiration اضافه شود.
- **First Login ریست نشود.**
- **Absolute Expiration تغییر نکند.**
- مثال: ۵ روز باقی‌مانده + ۳۰ روز طرح + ۲ روز هدیه = ۳۷ روز Relative Expiration جدید؛ First Login و Absolute Expiration ثابت می‌مانند.

## 5. روزهای هدیه و جلوگیری از تمدید تکراری

- هدیهٔ تمدید باید از بخش ادمین قابل تنظیم باشد.
- نمونهٔ سیاست قابل تنظیم: تمدید موفق اول +۱ روز، تمدیدهای موفق دوم به بعد +۵ روز. این اعداد نمونه‌اند و نباید به‌صورت ثابت در کد hard-code شوند.
- شمارنده فقط پس از تمدید موفق افزایش یابد.
- retry، timeout یا callback تکراری نباید مدت یا هدیه را دوباره اضافه کند.
- برای جلوگیری از دوباره‌کاری، عملیات تمدید باید شناسه/کلید یکتای idempotency یا وضعیت پایدار مناسب داشته باشد و نتیجهٔ هر عملیات ثبت شود.

## 6. مرز معماری فعلی RouteBox

- حفظ لایه‌بندی فعلی IBSng در RouteBox و اتصال آن به مسیر تمدید موجود، به‌جای ساخت مسیر موازی.
- نام‌های دیده‌شده در خلاصهٔ بررسی قبلی که باید با کد فعلی مجدداً راستی‌آزمایی شوند:
  - `IBSngService::renew()`
  - `IBSngClient::renewUser()`
  - `IBSngProvider::renewSubscription()`
- گزارش قبلی اشاره می‌کرد که مسیر عملیاتی تمدید ممکن است غیرفعال/no-op باشد؛ این موضوع باید با بازبینی repository در چت/مرحلهٔ توسعه تأیید شود، نه اینکه بدون بررسی قطعی فرض شود.
- نگاشت‌های معماری که باید در سورس تأیید شوند:
  - `service_plans.provider_plan_key`: نام دقیق IBSng Group
  - `service_subscriptions.provider_reference`: شناسهٔ داخلی کاربر IBSng
- این نام‌ها و رابطه‌ها تا زمان تطبیق با سورس فعلی، «سرنخ بررسی» محسوب می‌شوند.

## 7. نکات سورس IBSng A1.24 برای بررسی Web Panel

مسیرهای مرتبطی که در بررسی قبلی شناسایی شده‌اند و باید با آرشیو مرجع A1.24 تطبیق داده شوند:

- `interface/IBSng/admin/plugins/edit.php`
- `interface/IBSng/admin/plugins/edit_funcs.php`
- `interface/IBSng/inc/attr_update.php`
- `interface/smarty/plugins/function.attrUpdateMethod.php`
- `interface/smarty/templates/plugins/user/edit/first_login.tpl`
- `interface/smarty/templates/plugins/user/edit/rel_exp_date.tpl`
- `interface/smarty/templates/plugins/user/edit/abs_exp_date.tpl`

سرنخ‌های شناخته‌شده:
- شناسهٔ updater برای First Login: `firstLogin`
- فیلد مرتبط با ریست First Login: `reset_first_login=t`
- updater مربوط به Absolute Expiration: `absExpDate`
- فیلد Absolute Expiration: `abs_exp_date`

**مواردی که باید از سورس دقیقاً استخراج و ثبت شوند، نه حدس زده شوند:**
1. endpoint و ترتیب درخواست‌ها برای تغییر هر attribute؛
2. تمام فیلدهای POST و hidden fields؛
3. نحوهٔ ارسال/حفظ cookie و session؛
4. متد updater/callback و نام دقیق پارامترها؛
5. پاسخ موفق/ناموفق و پیام‌ها یا redirectها؛
6. آیا هر عملیات یک درخواست است یا چند درخواست؛
7. نحوهٔ محاسبه و فرمت تاریخ/مدت نسبی؛
8. رفتار First Login برای حساب منقضی و فعال در نسخهٔ مرجع.

## 8. الزامات امنیتی و صحت عملیات

- در تمدید از My Services، مالکیت subscription قبل از هر درخواست به IBSng بررسی شود.
- عملیات ادمین از مجوز و سشن معتبر ادمین RouteBox استفاده کند.
- رمز IBSng کاربر نهایی در جریان بات درخواست یا ذخیره نشود.
- درخواست‌های تکراری یا ناموفق نباید تمدید مضاعف ایجاد کنند.
- در logها از ثبت رمز، cookie/session حساس یا اطلاعات محرمانه اجتناب شود.
- تغییرات دیتابیس و API باید با داده‌های موجود سازگار باشند.

## 9. ترتیب پیشنهادی پیاده‌سازی و آزمون

1. بازبینی سورس فعلی RouteBox و سورس مرجع IBSng A1.24؛ تأیید مسیرها و POSTهای واقعی.
2. افزودن Validity duration به طرح‌های IBSng با migration سازگار.
3. پیاده‌سازی تمدید ادمین برای username دلخواه و طرح انتخابی.
4. پیاده‌سازی تمدید My Services با کنترل مالکیت و استفاده از پیوند ذخیره‌شده.
5. پیاده‌سازی هدیهٔ قابل تنظیم، شمارش فقط در موفقیت و idempotency.
6. آزمون حساب منقضی: First Login reset شود؛ Relative Expiration برابر مدت پلن + هدیه از مبنای صفر قرار گیرد؛ Absolute Expiration ثابت بماند.
7. آزمون حساب فعال: Relative Expiration با باقی‌مانده + مدت طرح + هدیه افزایش یابد؛ First Login و Absolute Expiration بدون تغییر بمانند.
8. آزمون حساب IBSng بیرون RouteBox از مسیر ادمین.
9. آزمون کاربر نهایی با حساب متعلق به خودش و رد دسترسی به سرویس دیگران.
10. آزمون retry/timeout و اطمینان از عدم تمدید دوباره.
11. بررسی Worker فقط پس از پایان این مرحله و در کاری جداگانه؛ فعلاً تغییر نکند.

## 10. وضعیت مستندات

این فایل یک سند مستقل برای یافته‌های IBSng است. هر تغییری در معماری یا رفتار تمدید باید همین سند را به‌روزرسانی کند. در زمان پیاده‌سازی، تفاوت «یافتهٔ تأییدشده از سورس» و «تصمیم/نیازمندی محصول» روشن نگه داشته شود.

## 11. مرجع مستقل درخواست‌های HTTP پنل IBSng A1.24

> این بخش برای نگهداری جزئیات عملیاتی Web Panel است تا هنگام پیاده‌سازی یا عیب‌یابی دوباره از ابتدا استخراج نشوند. موارد زیر بر اساس مشاهدات/اطلاعات ثبت‌شده از پنل هستند؛ تا زمانی که با سورس دقیق IBSng A1.24 و پاسخ واقعی درخواست‌ها تطبیق نشده‌اند، «مرجع بررسی» محسوب می‌شوند و نباید بدون تأیید به‌عنوان قرارداد قطعی کد استفاده شوند. نام کاربری/رمز و مقدار واقعی cookie/session هرگز در مستندات یا log ثبت نشود.

### 11.1 ورود و نشست ادمین

- مسیر ورود مشاهده‌شده: `POST /IBSng/admin/`
- ورودی‌های ورود: username و password ادمین IBSng.
- نشست HTTP از طریق cookie با نام `IBS_SESSID` نگه‌داری می‌شود؛ مقدار cookie محرمانه و وابسته به نشست است و نباید hard-code یا log شود.
- معیار موفقیت ورود باید با پاسخ HTTP و redirect مقصد/صفحهٔ معتبر پنل بررسی شود؛ صرف دریافت پاسخ یا status به‌تنهایی موفقیت احراز هویت را اثبات نمی‌کند.
- درخواست‌های بعدی باید از همان HTTP client/session موجود RouteBox استفاده کنند و cookie jar/سشن را حفظ کنند؛ کلاینت یا اتصال موازی جدید ساخته نشود.

### 11.2 مشاهدهٔ Groupها و اطلاعات کاربر

- فهرست Groupها: `GET /IBSng/admin/group/group_list.php`
- جزئیات Group: `GET /IBSng/admin/group/group_info.php?group_name=<group_name>`
- جست‌وجو/مشاهدهٔ کاربر بر اساس username: `GET /IBSng/admin/user/user_info.php?normal_username=<username>`
- پارامترهای query باید با روش مناسب URL-encode شوند. نام Group باید دقیقاً همان نام ثبت‌شده در IBSng باشد.
- ساختار HTML، فیلدهای واقعی اطلاعات کاربر، و تشخیص پاسخ خالی/خطا باید از A1.24 استخراج و در پیاده‌سازی verify شوند.

### 11.3 ایجاد کاربر

مسیر مشاهده‌شده:

`POST /IBSng/admin/user/add_new_users.php`

فیلدهای ثبت‌شده در نمونهٔ درخواست:

- `count`
- `credit`
- `owner_name`
- `group_name`
- `credit_comment`

این فهرست، فهرست فیلدهای دیده‌شده است و لزوماً تمام فیلدهای اجباری فرم نیست. پیش از استفاده، فیلدهای hidden، مقادیر پیش‌فرض، اعتبارسنجی‌ها و پاسخ موفق/ناموفق از فرم و سورس A1.24 بررسی شوند. این مرجع به معنی تغییر یا جایگزینی جریان فعلی خرید/ساخت کاربر نیست.

### 11.4 تغییر username و password کاربر

- ابتدا صفحهٔ ویرایش کاربر از مسیر edit-user مربوط به پنل خوانده می‌شود تا شناسه‌ها و فیلدهای فرم واقعی به دست آیند.
- مسیر POST مشاهده‌شده: `/IBSng/admin/plugins/edit.php`
- پارامترها/فیلدهای مشاهده‌شده در نمونه:
  - `update=1`
  - `edit_tpl_cs=normal_username`
  - `target=user`
  - `target_id`
  - `user_id`
  - `has_normal_username=t`
  - `current_normal_username`
  - `normal_username`
  - `password`
  - `normal_save_user_add=t`
  - `attr_update_method_0=normalAttrs`
- مقدارهای `target_id` و `user_id` باید از فرم/پاسخ معتبر همان کاربر استخراج شوند؛ مقادیر ثابت یا حدسی استفاده نشود.
- این نمونه برای ثبت جزئیات و بررسی است؛ برای قابلیت تمدید، تغییر username/password انجام نشود مگر اینکه رفتار موجود RouteBox صراحتاً آن را لازم بداند.

### 11.5 به‌روزرسانی Attributeها

- مسیر POST ویرایش attributeها: `/IBSng/admin/plugins/edit.php`؛ در سورس `edit.php`، شاخهٔ `update/edit_tpl_cs/target/target_id` به `intUpdateAttrs()` می‌رسد و `edit_funcs.php`، `updateTargetAttrs(FALSE)` را اجرا می‌کند.
- طبق سورس `attrs.php` و `UpdateAttrsHelper::mustBeInRequest()`، فیلدهای زمینه‌ای `group_id`، `group_name`، `owner_name` و `comment` برای update لازم‌اند. Client اکنون این فیلدها را در renewal POST می‌فرستد؛ `group_id` از صفحهٔ واقعی IBSng Group استخراج می‌شود و owner از ادمین متصل.
- موفقیت در مسیر Web Panel با redirect به صفحهٔ اطلاعات همان کاربر انجام می‌شود؛ Client اکنون این redirect و تطابق `user_id` را بررسی می‌کند، نه صرفاً وجود/عدم وجود واژهٔ `error` در body.
- سرنخ‌های ثبت‌شده در بخش سورس:
  - First Login updater: `firstLogin`
  - فیلد reset: `reset_first_login=t`
  - Absolute Expiration updater: `absExpDate`
  - فیلد تاریخ مطلق: `abs_exp_date`
- برای Relative Expiration هنوز endpoint، updater، فیلدهای POST، فرمت مقدار و محاسبهٔ دقیق باید از سورس A1.24 تأیید و همین بخش تکمیل شوند.
- لازم است برای هر عملیات ثبت شود: درخواست آغازین/صفحهٔ فرم، endpoint نهایی، method، تمام POST و hidden fields، کوکی/سشن مورد نیاز، تعداد درخواست‌ها، پاسخ موفق/ناموفق و اثر واقعی بر دادهٔ کاربر.

### 11.6 قواعد تمدید مرتبط با این مرجع

**این قواعد تصمیم صریح محصول هستند و در کد و آزمون‌ها باید عیناً رعایت شوند.**

- حساب منقضی: First Login ریست شود؛ Relative Expiration از مبنای صفر برابر مدت طرح + روزهای هدیه تنظیم شود؛ Absolute Expiration تغییر نکند.
- حساب فعال: باقی‌ماندهٔ اعتبار + مدت روزهای طرح + روزهای هدیه به Relative Expiration افزوده شود؛ First Login و Absolute Expiration بدون تغییر بمانند.
- نمونهٔ محاسبه: ۳۰ روز اعتبار باقی‌مانده + ۳۰ روز طرح + ۱ روز هدیه = ۶۱ روز اعتبار نسبی جدید. این صرفاً نمونهٔ سیاست RouteBox است، نه مقدار ثابت IBSng.
- روز هدیه باید تنظیم‌پذیر باشد و فقط برای تمدید موفق منظور شود.
- timeout یا retry نباید باعث اجرای دوبارهٔ تغییر attribute یا اعطای مجدد هدیه شود؛ هر عملیات تمدید به شناسهٔ یکتا و ثبت وضعیت نیاز دارد.
- مسیر ادمین از نشست ادمین RouteBox استفاده می‌کند و می‌تواند username خارج از RouteBox را تمدید کند. مسیر My Services فقط از ارتباط ذخیره‌شدهٔ subscription متعلق به کاربر جاری استفاده می‌کند؛ رمز کاربر IBSng دوباره درخواست نشود.
- **Worker همچنان خارج از محدوده است** و این مرجع مجوزی برای تغییر Worker، سرویس یا تایمر وابسته به آن نیست.

### 11.7 چک‌لیست تکمیل مرجع با سورس A1.24

- [ ] تأیید login POST، redirect موفق، مدیریت خطا و حفظ `IBS_SESSID`.
- [ ] استخراج فیلدهای کامل فرم Group list/info و user info.
- [ ] استخراج تمام فیلدهای لازم/hidden ایجاد کاربر.
- [ ] استخراج کامل فرم تغییر username/password، شامل شناسه‌ها و updater.
- [ ] ثبت دقیق درخواست First Login و اثر آن برای کاربر فعال و منقضی.
- [ ] استخراج دقیق Relative Expiration: updater، endpoint، فیلدها، فرمت تاریخ و الگوریتم محاسبه.
- [ ] تأیید اینکه Absolute Expiration در تمدیدهای تعریف‌شده دست‌نخورده می‌ماند.
- [ ] ثبت پاسخ‌ها و روش قطعی تشخیص موفقیت؛ آزمون retry/timeout و idempotency.
- [ ] به‌روزرسانی این بخش با یافته‌های قطعی و مشخص کردن مواردی که هنوز تأیید نشده‌اند.

## 12. تصمیم قطعی دربارهٔ مدت اعتبار پلن IBSng

این تصمیم در ادامهٔ بررسی schema و مصرف فیلدهای پلن ثبت می‌شود:

- فیلد `provider_plan_key` یک فیلد مشترک در `service_plans` است؛ برای IBSng نام Group را نگه می‌دارد و در مسیر RouteBox نیز استفاده می‌شود. معنا یا ساختار آن تغییر نکند.
- فیلد عمومی `duration_days` در مسیر RouteBox برای محاسبهٔ مدت Peer مصرف می‌شود؛ منطق آن برای IBSng renewal بازتعریف نشود.
- مدت اعتبار موردنیاز برای تمدید IBSng با ستون جداگانهٔ `ibsng_validity_days` در `service_plans` ذخیره شود.
- `ibsng_validity_days` فقط برای `provider_key='ibsng'` کاربرد دارد و در UI مدیریت IBSng، کنار نگاشت پلن به Group قابل تنظیم خواهد بود.
- برای پلن‌های IBSng موجود مقدار قبلی از `duration_days` کپی یا حدس زده نشود؛ مقدار این فیلد جدید تا زمان تعیین توسط ادمین می‌تواند `NULL` بماند.
- migration باید افزایشی و سازگار با نصب‌های قبلی باشد؛ هیچ ستون یا دادهٔ مشترک فعلی حذف یا تغییر معنا داده نشود.
- تغییرات مربوط به اعتبار IBSng نباید مسیرهای RouteBox را تحت تأثیر قرار دهد.



## 13. نقش فایل مرکزی تمدید و سازگاری با Worker

تصمیم معماری تکمیلی:

- تمدید باید از یک ماژول/مسیر مرکزی مشترک انجام شود؛ صفحهٔ پنل وب فقط ورودی را دریافت می‌کند، درخواست را به این منطق می‌سپارد و نتیجه را نشان می‌دهد.
- کاربرد فایل مرکزی تمدید، اجرای قواعد تمدید IBSng است: اعتبارسنجی درخواست، تعیین وضعیت حساب (فعال/منقضی)، اعمال مدت اعتبار پلن و روز هدیه، استفاده از کلاینت و نشست موجود IBSng، ثبت نتیجه و جلوگیری از اجرای تکراری.
- این فایل نباید جایگزین `IBSngClient` یا `IBSngService` شود، UI را در خود نگه دارد، یا مسیر HTTP موازی/اتصال مستقیم به دیتابیس IBSng بسازد. جزئیات درخواست‌ها باید با سورس IBSng A1.24 تطبیق داده شوند.
- این طراحی با استفادهٔ احتمالی آینده از Worker سازگار است: در صورت نیاز، Worker می‌تواند همان منطق مرکزی را فراخوانی کند؛ نباید الگوریتم تمدید را جداگانه تکرار کند.
- **در مرحلهٔ فعلی Worker خارج از محدوده است و نباید تغییر کند.** هیچ Worker، سرویس، تایمر یا اجرای پس‌زمینه‌ای برای این کار اضافه نشود. تمرکز فعلی فقط بر تکمیل تمدید از پنل وب است.
- نام و مسیر فایل مرکزی (از جمله نام پیشنهادی `ibsng-renewal.php`) باید با ساختار واقعی پروژه تطبیق داده شود. ثبت این تصمیم به معنی وجود یا تکمیل کد آن فایل نیست.



## Implementation trace (in progress)

The implementation slice on `atd-panel-ibsng-renewal` now includes:

- `IBSngSchema::ensure()`: adds nullable `service_plans.ibsng_validity_days` without deriving it from generic `duration_days`; creates a persistent renewal ledger with a unique idempotency key; stores gift-day policy separately.
- `IBSngService`: validates/persists plan validity and gift-day settings; exposes `renewWithVerifiedState()` as the integration entry to the central policy.
- `IBSngRenewal.php` / `IBSngRenewal`: centralizes policy, gift selection, idempotency reservation, and ledger completion/failure. It fails closed if verified account state is missing.
- `IBSngModule.php`: loads the central module.
- `IBSngClient::renewUser()`: remains the HTTP/session adapter. The legacy two-argument call is now non-fatal but rejected safely unless a complete, verified renewal request is supplied.
- `IBSngProvider::renewSubscription()`: now delegates to `IBSngService::renewWithVerifiedState()` and therefore uses the central renewal policy/ledger. Its caller must provide verified account state and an idempotency key; this input is not yet sourced automatically from the A1.24 user-info page.

**Current implementation status (not yet production-verified):** `IBSngClient::getUserRenewalStateByUsername()` now parses `user_id`, `first_login`, `rel_exp` and the displayed Nearest Expiration Date from the authenticated A1.24 user-info page. `IBSngService::renewByUsername()` now provides the non-bot integration entry: lookup by IBSng username → derive verified state → invoke the central ledger/policy. Missing users are separated from session/transport and unexpected-page errors. The pure renewal calculator has focused CI tests for expired, active and zero-gift cases.

Still not release-ready: parser behavior and POST/update results must be validated against a live IBSng A1.24 instance, and timeout/reconciliation cases need integration/regression coverage. The account with no First Login and no displayed nearest expiry currently preserves the numeric Relative Expiration duration as not-yet-started validity; this behavior also requires live-instance confirmation. No admin UI, My Services, bot flow, Worker, or background task is included in this slice. Generic RouteBox duration/provisioning remains unchanged.
