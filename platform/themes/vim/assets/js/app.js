/*
 * Application
 */
(function($) {
    "use strict";

    jQuery(document).ready(function($) {
        /*-------------------------------
        WINTER CMS FLASH MESSAGE HANDLING
        ---------------------------------*/
        $(document).on('ajaxSetup', function(event, context) {
            // Enable AJAX handling of Flash messages on all AJAX requests
            context.options.flash = true;

            // Enable the StripeLoadIndicator on all AJAX requests
            context.options.loading = $.oc.stripeLoadIndicator;

            // Handle Flash Messages
            context.options.handleFlashMessage = function(message, type) {
                $.oc.flashMsg({ text: message, class: type });
            };

            // Handle Error Messages
            context.options.handleErrorMessage = function(message) {
                $.oc.flashMsg({ text: message, class: 'error' });
            };
        });
    });
}(jQuery));

if (typeof(gtag) !== 'function') {
    gtag = function() { console.log('GoogleAnalytics not present.'); }
}

(function() {
    "use strict";

    var translations = {
        en: {
            "meta.title": "VIM App - Instructions",
            "hero.kicker": "The immaterial suitcase",
            "hero.title": "Use the VIM app to collect and submit field forms.",
            "hero.copy": "Open the app from your phone, install it on the Home screen and fill in forms even when the connection is unstable. Submissions are queued and synced as soon as possible.",
            "hero.openApp": "Open app.vim-data.org",
            "hero.installLink": "How to install it",
            "access.ariaLabel": "Quick access to the VIM app",
            "access.title": "Quick access",
            "access.qrAlt": "QR code to open vim-data.org",
            "access.qrNote": "On mobile, tap the link. From a computer, scan this QR code with your phone camera.",
            "install.title": "Install the App on your device",
            "install.ios.1": "Open <strong>app.vim-data.org</strong> with Safari.",
            "install.ios.2": "Tap the share button.",
            "install.ios.3": "Choose <strong>Add to Home Screen</strong>.",
            "install.ios.4": "Confirm with <strong>Add</strong>.",
            "install.android.1": "Open <strong>app.vim-data.org</strong> with Chrome.",
            "install.android.2": "When it appears, tap <strong>Install app</strong>.",
            "install.android.3": "If it does not appear, open the browser menu.",
            "install.android.4": "Choose <strong>Add to Home screen</strong> or <strong>Install app</strong>.",
            "workflow.title": "Fill in and submit a form",
            "workflow.login.title": "Sign in",
            "workflow.login.copy": "Enter the username and password provided by the VIM project.",
            "workflow.fill.title": "Fill in",
            "workflow.fill.copy": "Open <em>Fill in form</em>, choose the language and complete the required fields.",
            "workflow.media.title": "Attach media",
            "workflow.media.copy": "Record audio or video, take photos or upload files already on the device.",
            "workflow.submit.title": "Submit",
            "workflow.submit.copy": "Tap <em>Completed</em> and choose automatic or manual sending. If you are offline, the form stays in the queue.",
            "screens.title": "Reference screens",
            "brand.name": "The immaterial suitcase",
            "screen.login.badge": "Sign in",
            "screen.login.username": "Username",
            "screen.login.password": "Password",
            "screen.login.button": "Sign in",
            "screen.login.copy": "Sign in with the credentials assigned to you.",
            "screen.login.alt": "VIM app sign-in screen",
            "screen.home.fill": "Fill in form",
            "screen.home.fillHelp": "Start the questionnaire",
            "screen.home.outbox": "Forms to send",
            "screen.home.outboxHelp": "Waiting to be sent",
            "screen.home.sent": "Submitted forms",
            "screen.home.sentHelp": "Sent answers",
            "screen.home.copy": "The Home screen contains the main actions and archive.",
            "screen.home.alt": "VIM app home screen",
            "screen.form.section": "Section 4 / 9",
            "screen.form.audio": "Record audio",
            "screen.form.record": "Record",
            "screen.form.uploadAudio": "Upload audio",
            "screen.form.saveDraft": "Save draft",
            "screen.form.completed": "Completed",
            "screen.form.copy": "You can save a draft and continue later.",
            "screen.form.alt": "VIM app form screen",
            "screen.record.copy": "Record audio or video directly from the browser when the form asks for it.",
            "screen.record.alt": "VIM app media recording screen",
            "screen.complete.copy": "Complete the form when all required information has been entered.",
            "screen.complete.alt": "VIM app completed form screen",
            "screen.send.copy": "Send pending forms or keep them in the queue until the connection is available.",
            "screen.send.alt": "VIM app submission queue screen",
            "screen.outbox.title": "Forms to send",
            "screen.outbox.formTitle": "Collection form",
            "screen.outbox.saved": "Saved in the queue",
            "screen.outbox.auto": "Automatic sending: on",
            "screen.outbox.sendAll": "Send all",
            "screen.outbox.copy": "Failed submissions remain available so you can try again.",
            "field.title": "Before going into the field",
            "field.1": "Open the app at least once with an active connection.",
            "field.2": "Check that sign-in, microphone, camera and free storage work.",
            "field.3": "Send whenever you can: data remains on the device, but it depends on browser limits."
        },
        it: {
            "meta.title": "VIM App - Istruzioni",
            "hero.kicker": "La valigia immateriale",
            "hero.title": "Usa la VIM app per raccogliere e inviare i moduli sul campo.",
            "hero.copy": "Apri l'app dal telefono, installala nella schermata Home e compila i moduli anche quando la connessione non è stabile. Gli invii vengono messi in coda e sincronizzati appena possibile.",
            "hero.openApp": "Apri app.vim-data.org",
            "hero.installLink": "Come installarla",
            "access.ariaLabel": "Accesso rapido alla app VIM",
            "access.title": "Accesso rapido",
            "access.qrAlt": "QR code per aprire vim-data.org",
            "access.qrNote": "Se sei su mobile, tocca il link. Da computer, inquadra il QR code con la fotocamera del telefono.",
            "install.title": "Installa l'app sul tuo dispositivo",
            "install.ios.1": "Apri <strong>app.vim-data.org</strong> con Safari.",
            "install.ios.2": "Tocca il pulsante di condivisione.",
            "install.ios.3": "Scegli <strong>Aggiungi alla schermata Home</strong>.",
            "install.ios.4": "Conferma con <strong>Aggiungi</strong>.",
            "install.android.1": "Apri <strong>app.vim-data.org</strong> con Chrome.",
            "install.android.2": "Quando compare, tocca <strong>Installa app</strong>.",
            "install.android.3": "Se non compare, apri il menu del browser.",
            "install.android.4": "Scegli <strong>Aggiungi a schermata Home</strong> o <strong>Installa app</strong>.",
            "workflow.title": "Compila e invia un modulo",
            "workflow.login.title": "Accedi",
            "workflow.login.copy": "Inserisci username e password forniti dal progetto VIM.",
            "workflow.fill.title": "Compila",
            "workflow.fill.copy": "Apri <em>Compila modulo</em>, scegli la lingua e completa i campi obbligatori.",
            "workflow.media.title": "Allega media",
            "workflow.media.copy": "Registra audio o video, scatta foto oppure carica file già presenti sul dispositivo.",
            "workflow.submit.title": "Invia",
            "workflow.submit.copy": "Tocca <em>Completato</em> e scegli invio automatico o manuale. Se sei offline, il modulo resta in coda.",
            "screens.title": "Schermate di riferimento",
            "brand.name": "La valigia immateriale",
            "screen.login.badge": "Accesso",
            "screen.login.username": "Username",
            "screen.login.password": "Password",
            "screen.login.button": "Entra",
            "screen.login.copy": "Accedi con le credenziali assegnate.",
            "screen.login.alt": "Schermata di accesso della app VIM",
            "screen.home.fill": "Compila modulo",
            "screen.home.fillHelp": "Avvia il questionario",
            "screen.home.outbox": "Moduli da inviare",
            "screen.home.outboxHelp": "In attesa di invio",
            "screen.home.sent": "Moduli inviati",
            "screen.home.sentHelp": "Risposte inviate",
            "screen.home.copy": "La Home contiene azioni principali e archivio.",
            "screen.home.alt": "Schermata Home della app VIM",
            "screen.form.section": "Sezione 4 / 9",
            "screen.form.audio": "Registra audio",
            "screen.form.record": "Registra",
            "screen.form.uploadAudio": "Carica audio",
            "screen.form.saveDraft": "Salva bozza",
            "screen.form.completed": "Completato",
            "screen.form.copy": "Puoi salvare una bozza e riprendere più tardi.",
            "screen.form.alt": "Schermata modulo della app VIM",
            "screen.record.copy": "Registra audio o video direttamente dal browser quando il modulo lo richiede.",
            "screen.record.alt": "Schermata registrazione media della app VIM",
            "screen.complete.copy": "Completa il modulo quando tutte le informazioni obbligatorie sono state inserite.",
            "screen.complete.alt": "Schermata modulo completato della app VIM",
            "screen.send.copy": "Invia i moduli in attesa oppure lasciali in coda finché la connessione è disponibile.",
            "screen.send.alt": "Schermata coda invio della app VIM",
            "screen.outbox.title": "Moduli da inviare",
            "screen.outbox.formTitle": "Modulo raccolta",
            "screen.outbox.saved": "Salvato nella coda",
            "screen.outbox.auto": "Invio automatico: on",
            "screen.outbox.sendAll": "Invia tutti",
            "screen.outbox.copy": "Gli invii falliti restano disponibili per riprovare.",
            "field.title": "Prima di uscire sul campo",
            "field.1": "Apri l'app almeno una volta con connessione attiva.",
            "field.2": "Verifica che login, microfono, fotocamera e spazio libero funzionino.",
            "field.3": "Invia appena puoi: i dati restano sul dispositivo, ma dipendono dai limiti del browser."
        },
        ar: {
            "meta.title": "تطبيق VIM - التعليمات",
            "hero.kicker": "الحقيبة اللامادية",
            "hero.title": "استخدم تطبيق VIM لجمع النماذج الميدانية وإرسالها.",
            "hero.copy": "افتح التطبيق من الهاتف، وثبته على الشاشة الرئيسية، واملأ النماذج حتى عندما يكون الاتصال غير مستقر. توضع عمليات الإرسال في قائمة انتظار وتتم مزامنتها بمجرد توفر الاتصال.",
            "hero.openApp": "افتح app.vim-data.org",
            "hero.installLink": "طريقة التثبيت",
            "access.ariaLabel": "وصول سريع إلى تطبيق VIM",
            "access.title": "وصول سريع",
            "access.qrAlt": "رمز QR لفتح vim-data.org",
            "access.qrNote": "إذا كنت تستخدم الهاتف، اضغط على الرابط. من الكمبيوتر، امسح رمز QR بكاميرا الهاتف.",
            "install.title": "ثبت تطبيق VIM على جهازك",
            "install.ios.1": "افتح <strong>app.vim-data.org</strong> باستخدام Safari.",
            "install.ios.2": "اضغط زر المشاركة.",
            "install.ios.3": "اختر <strong>إضافة إلى الشاشة الرئيسية</strong>.",
            "install.ios.4": "أكد بالضغط على <strong>إضافة</strong>.",
            "install.android.1": "افتح <strong>app.vim-data.org</strong> باستخدام Chrome.",
            "install.android.2": "عندما يظهر الخيار، اضغط <strong>تثبيت التطبيق</strong>.",
            "install.android.3": "إذا لم يظهر، افتح قائمة المتصفح.",
            "install.android.4": "اختر <strong>إضافة إلى الشاشة الرئيسية</strong> أو <strong>تثبيت التطبيق</strong>.",
            "workflow.title": "املأ النموذج وأرسله",
            "workflow.login.title": "تسجيل الدخول",
            "workflow.login.copy": "أدخل اسم المستخدم وكلمة المرور التي يوفرها مشروع VIM.",
            "workflow.fill.title": "املأ",
            "workflow.fill.copy": "افتح <em>املأ النموذج</em>، اختر اللغة وأكمل الحقول المطلوبة.",
            "workflow.media.title": "أرفق الوسائط",
            "workflow.media.copy": "سجل صوتا أو فيديو، التقط صورا أو حمل ملفات موجودة على الجهاز.",
            "workflow.submit.title": "أرسل",
            "workflow.submit.copy": "اضغط <em>مكتمل</em> واختر الإرسال التلقائي أو اليدوي. إذا كنت دون اتصال، يبقى النموذج في قائمة الانتظار.",
            "screens.title": "شاشات مرجعية",
            "brand.name": "الحقيبة اللامادية",
            "screen.login.badge": "تسجيل الدخول",
            "screen.login.username": "اسم المستخدم",
            "screen.login.password": "كلمة المرور",
            "screen.login.button": "دخول",
            "screen.login.copy": "سجل الدخول باستخدام بيانات الاعتماد المخصصة لك.",
            "screen.login.alt": "شاشة تسجيل الدخول في تطبيق VIM",
            "screen.home.fill": "املأ النموذج",
            "screen.home.fillHelp": "ابدأ الاستبيان",
            "screen.home.outbox": "نماذج للإرسال",
            "screen.home.outboxHelp": "بانتظار الإرسال",
            "screen.home.sent": "نماذج مرسلة",
            "screen.home.sentHelp": "إجابات مرسلة",
            "screen.home.copy": "تحتوي الشاشة الرئيسية على الإجراءات الأساسية والأرشيف.",
            "screen.home.alt": "الشاشة الرئيسية في تطبيق VIM",
            "screen.form.section": "القسم 4 / 9",
            "screen.form.audio": "تسجيل صوت",
            "screen.form.record": "تسجيل",
            "screen.form.uploadAudio": "تحميل صوت",
            "screen.form.saveDraft": "حفظ مسودة",
            "screen.form.completed": "مكتمل",
            "screen.form.copy": "يمكنك حفظ مسودة والمتابعة لاحقا.",
            "screen.form.alt": "شاشة النموذج في تطبيق VIM",
            "screen.record.copy": "سجل الصوت أو الفيديو مباشرة من المتصفح عندما يطلب النموذج ذلك.",
            "screen.record.alt": "شاشة تسجيل الوسائط في تطبيق VIM",
            "screen.complete.copy": "أكمل النموذج بعد إدخال جميع المعلومات المطلوبة.",
            "screen.complete.alt": "شاشة اكتمال النموذج في تطبيق VIM",
            "screen.send.copy": "أرسل النماذج المعلقة أو اتركها في قائمة الانتظار حتى يتوفر الاتصال.",
            "screen.send.alt": "شاشة قائمة الإرسال في تطبيق VIM",
            "screen.outbox.title": "نماذج للإرسال",
            "screen.outbox.formTitle": "نموذج جمع البيانات",
            "screen.outbox.saved": "محفوظ في قائمة الانتظار",
            "screen.outbox.auto": "الإرسال التلقائي: مفعل",
            "screen.outbox.sendAll": "إرسال الكل",
            "screen.outbox.copy": "تبقى الإرسالات التي فشلت متاحة لإعادة المحاولة.",
            "field.title": "قبل الخروج إلى الميدان",
            "field.1": "افتح التطبيق مرة واحدة على الأقل مع اتصال نشط.",
            "field.2": "تحقق من عمل تسجيل الدخول والميكروفون والكاميرا والمساحة الفارغة.",
            "field.3": "أرسل البيانات كلما أمكن: تبقى البيانات على الجهاز، لكنها تعتمد على حدود المتصفح."
        }
    };

    function getStoredLanguage() {
        try {
            return window.localStorage.getItem('vimGuideLanguage');
        } catch (error) {
            return null;
        }
    }

    function setStoredLanguage(language) {
        try {
            window.localStorage.setItem('vimGuideLanguage', language);
        } catch (error) {
            return;
        }
    }

    function getInitialLanguage() {
        var params = new URLSearchParams(window.location.search);
        var requested = params.get('lang');
        var stored = getStoredLanguage();

        if (translations[requested]) {
            return requested;
        }

        if (translations[stored]) {
            return stored;
        }

        return 'en';
    }

    function applyLanguage(language) {
        var copy = translations[language] || translations.en;
        var direction = language === 'ar' ? 'rtl' : 'ltr';

        document.documentElement.lang = language;
        document.documentElement.dir = direction;
        document.title = copy["meta.title"];

        document.querySelectorAll('[data-i18n]').forEach(function(element) {
            var key = element.getAttribute('data-i18n');
            if (copy[key]) {
                element.innerHTML = copy[key];
            }
        });

        document.querySelectorAll('[data-i18n-alt]').forEach(function(element) {
            var key = element.getAttribute('data-i18n-alt');
            if (copy[key]) {
                element.setAttribute('alt', copy[key]);
            }
        });

        document.querySelectorAll('[data-i18n-aria-label]').forEach(function(element) {
            var key = element.getAttribute('data-i18n-aria-label');
            if (copy[key]) {
                element.setAttribute('aria-label', copy[key]);
            }
        });

        document.querySelectorAll('[data-lang-option]').forEach(function(button) {
            var isActive = button.getAttribute('data-lang-option') === language;
            button.classList.toggle('active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        setStoredLanguage(language);
    }

    document.addEventListener('DOMContentLoaded', function() {
        if (!document.querySelector('.guide-page')) {
            return;
        }

        document.querySelectorAll('[data-lang-option]').forEach(function(button) {
            button.addEventListener('click', function() {
                applyLanguage(button.getAttribute('data-lang-option'));
            });
        });

        applyLanguage(getInitialLanguage());
    });
}());
