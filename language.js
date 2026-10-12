// ============================================
// LANGUAGE TOGGLE — English / Kurdish / Arabic
// ============================================

(function () {
    var savedLang = localStorage.getItem('lang') || 'en';
    var html = document.documentElement;
    html.setAttribute('data-lang', savedLang);
    html.lang = savedLang;
    html.dir = savedLang === 'ar' ? 'rtl' : 'ltr';

    document.addEventListener('DOMContentLoaded', function () {
        updateTexts();
        highlight();
    });
})();

function setLanguage(lang) {
    localStorage.setItem('lang', lang);
    var html = document.documentElement;
    html.setAttribute('data-lang', lang);
    html.lang = lang;
    html.dir = lang === 'ar' ? 'rtl' : 'ltr';
    updateTexts();
    highlight();
}

function highlight() {
    var lang = localStorage.getItem('lang') || 'en';
    document.querySelectorAll('.lang-btn').forEach(function (btn) {
        var onclick = btn.getAttribute('onclick') || '';
        if (onclick.indexOf("'" + lang + "'") !== -1) btn.classList.add('active');
        else btn.classList.remove('active');
    });
}

function updateTexts() {
    var lang = localStorage.getItem('lang') || 'en';
    document.querySelectorAll('[data-en]').forEach(function (el) {
        var text = el.getAttribute('data-' + lang) || el.getAttribute('data-en');
        if (text) el.textContent = text;
    });
}

document.addEventListener('DOMContentLoaded', updateTexts);