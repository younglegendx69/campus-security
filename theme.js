// Theme toggle - remembers the user's choice in localStorage
(function() {
    var saved = localStorage.getItem('theme') || 'light';
    if (saved === 'dark') {
        document.documentElement.classList.add('dark-mode');
    }
})();

function toggleTheme() {
    var isDark = document.documentElement.classList.toggle('dark-mode');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
}