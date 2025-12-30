# ============================================
# POS System - Customer Deployment Package
# نظام نقاط البيع - حزمة النشر للزبون
# ============================================

## المتطلبات الأساسية (Prerequisites)

### البرامج المطلوبة:
1. **XAMPP** (يتضمن PHP 8.1+ و MySQL و Apache)
   - التحميل: https://www.apachefriends.org/download.html
   - اختر الإصدار مع PHP 8.1 أو أعلى

2. **Git** (لجلب التحديثات)
   - التحميل: https://git-scm.com/download/win

3. **Composer** (لإدارة مكتبات PHP)
   - التحميل: https://getcomposer.org/download/

4. **Node.js** (لبناء الواجهة الأمامية) - اختياري
   - التحميل: https://nodejs.org/

---

## خطوات التثبيت

### الخطوة 1: تثبيت XAMPP
```
1. قم بتحميل وتثبيت XAMPP
2. شغّل XAMPP Control Panel
3. شغّل Apache و MySQL
```

### الخطوة 2: نسخ ملفات النظام
```
1. انسخ مجلد POS إلى: C:\xampp\htdocs\POS
2. أو أي مسار تفضله
```

### الخطوة 3: إنشاء قاعدة البيانات
```
1. افتح phpMyAdmin: http://localhost/phpmyadmin
2. أنشئ قاعدة بيانات جديدة باسم: pos_database
3. الترميز: utf8mb4_unicode_ci
```

### الخطوة 4: تشغيل المثبت
```
1. افتح مجلد POS
2. انقر بزر الماوس الأيمن على install.bat
3. اختر "Run as administrator"
4. اتبع التعليمات على الشاشة
```

### الخطوة 5: إعداد ملف التكوين
قم بتعديل ملف `backend\.env`:
```env
APP_NAME="POS System"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pos_database
DB_USERNAME=root
DB_PASSWORD=

# إعدادات التحديث (اختياري)
UPDATE_SERVER_URL=
LICENSE_KEY=
```

---

## تشغيل النظام

### الطريقة 1: باستخدام start.bat (الأسهل)
```
انقر مرتين على start.bat واختر طريقة التشغيل
```

### الطريقة 2: يدوياً
```bash
cd C:\xampp\htdocs\POS\backend
php artisan serve
```
ثم افتح: http://localhost:8000

### الطريقة 3: عبر Apache
```
1. تأكد من تشغيل Apache في XAMPP
2. افتح: http://localhost/POS/backend/public
```

---

## تحديث النظام

### تحديث تلقائي (من داخل النظام):
```
1. سجّل الدخول كمسؤول
2. اذهب إلى: الإعدادات > تحديثات النظام
3. انقر على "فحص التحديثات"
4. إذا وُجد تحديث، انقر على "تثبيت التحديث"
```

### تحديث يدوي:
```
1. انقر مرتين على update.bat
2. انتظر اكتمال التحديث
```

---

## بيانات الدخول الافتراضية

| الحقل | القيمة |
|-------|--------|
| البريد الإلكتروني | admin@example.com |
| كلمة المرور | password |

**مهم:** قم بتغيير كلمة المرور فوراً بعد التثبيت!

---

## حل المشاكل الشائعة

### المشكلة: صفحة بيضاء أو خطأ 500
```
1. تأكد من تشغيل Apache و MySQL
2. تحقق من صلاحيات مجلد storage:
   cd backend
   php artisan storage:link
3. امسح الذاكرة المؤقتة:
   php artisan cache:clear
   php artisan config:clear
```

### المشكلة: خطأ في الاتصال بقاعدة البيانات
```
1. تأكد من تشغيل MySQL
2. تحقق من إعدادات .env
3. تأكد من إنشاء قاعدة البيانات
```

### المشكلة: فشل التحديث
```
1. تحقق من اتصال الإنترنت
2. تأكد من تثبيت Git
3. راجع ملف السجل في مجلد logs
4. يمكنك استعادة النسخة الاحتياطية من مجلد backups
```

### المشكلة: الواجهة لا تظهر بشكل صحيح
```
1. امسح ذاكرة المتصفح (Ctrl+Shift+Delete)
2. أعد بناء الواجهة:
   cd frontend
   npm run build
3. انسخ الملفات إلى backend/public
```

---

## النسخ الاحتياطي

### نسخ احتياطي يدوي:
```bash
cd backend
php artisan db:backup
```

### موقع النسخ الاحتياطية:
```
C:\xampp\htdocs\POS\backups\
```

### استعادة من نسخة احتياطية:
```
1. من الإعدادات > إدارة النظام > استعادة
2. أو يدوياً:
   mysql -u root pos_database < backups\backup-file.sql
```

---

## هيكل المجلدات

```
POS/
├── backend/           # كود Laravel (الخلفية)
│   ├── app/          # كود التطبيق
│   ├── config/       # ملفات الإعدادات
│   ├── database/     # التهجيرات والبذور
│   ├── public/       # الملفات العامة
│   ├── storage/      # الملفات المخزنة
│   └── .env          # إعدادات البيئة
├── frontend/          # كود React (الواجهة)
│   ├── src/          # كود المصدر
│   └── dist/         # الملفات المبنية
├── backups/           # النسخ الاحتياطية
├── logs/              # سجلات النظام
├── install.bat        # سكربت التثبيت
├── update.bat         # سكربت التحديث
└── start.bat          # سكربت التشغيل
```

---

## الدعم الفني

للمساعدة أو الإبلاغ عن مشاكل:
- راجع ملفات السجل في مجلد `logs`
- راجع سجلات Laravel في `backend/storage/logs`

---

## الترخيص

هذا النظام مرخص للاستخدام حسب شروط الاتفاقية.
