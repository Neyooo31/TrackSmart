// ============================================
// DARK MODE FUNCTIONALITY
// Nag-toggle ng dark mode at nag-save sa localStorage
// ============================================

// Check saved theme on page load
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('theme');
    
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
        
        // Update theme select if exists (sa settings page)
        const themeSelect = document.querySelector('select[name="theme"]');
        if (themeSelect) {
            themeSelect.value = 'dark';
        }
    }
});

// Function to toggle dark mode
function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    
    // Save to localStorage
    if (document.body.classList.contains('dark-mode')) {
        localStorage.setItem('theme', 'dark');
    } else {
        localStorage.setItem('theme', 'light');
    }
}

// Update when theme is changed in settings
document.addEventListener('DOMContentLoaded', function() {
    const themeSelect = document.querySelector('select[name="theme"]');
    
    if (themeSelect) {
        themeSelect.addEventListener('change', function() {
            if (this.value === 'dark') {
                document.body.classList.add('dark-mode');
                localStorage.setItem('theme', 'dark');
            } else {
                document.body.classList.remove('dark-mode');
                localStorage.setItem('theme', 'light');
            }
        });
    }
});
