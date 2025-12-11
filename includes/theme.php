<?php
/**
 * Theme System
 * Provides light/dark mode functionality across all pages
 */
?>
<script>
    // Check for saved theme preference or default to light for admin pages, dark for public pages
    const savedTheme = localStorage.getItem('theme');
    const isPublicPage = window.location.pathname.includes('index.php') || window.location.pathname.includes('scan.php') || window.location.pathname.includes('login.php') || window.location.pathname.includes('forgot-password.php');
    const defaultTheme = isPublicPage ? 'dark' : 'light';
    const theme = savedTheme || defaultTheme;
    
    if (theme === 'dark') {
        document.documentElement.classList.add('dark-mode');
    }
</script>

<style>
    /* Light mode (default) styles */
    :root {
        --bg-primary: #f9fafb;
        --bg-secondary: rgba(255, 255, 255, 0.8);
        --bg-card: rgba(255, 255, 255, 0.9);
        --bg-card-hover: rgb(255, 255, 255);
        --text-primary: #111827;
        --text-secondary: #374151;
        --text-muted: #6b7280;
        --border-color: rgba(229, 231, 235, 1);
        --shadow-color: rgba(0, 0, 0, 0.05);
    }
    
    /* Dark mode styles */
    .dark-mode {
        --bg-primary: #111827;
        --bg-secondary: rgba(17, 24, 39, 0.8);
        --bg-card: rgba(31, 41, 55, 0.9);
        --bg-card-hover: rgb(31, 41, 55);
        --text-primary: #ffffff;
        --text-secondary: #d1d5db;
        --text-muted: #9ca3af;
        --border-color: rgba(255, 255, 255, 0.1);
        --shadow-color: rgba(0, 0, 0, 0.3);
    }
    
    body {
        background-color: var(--bg-primary);
        color: var(--text-primary);
        transition: background-color 0.3s ease, color 0.3s ease;
    }
    
    /* Theme utility classes */
    .theme-bg-primary { background-color: var(--bg-primary) !important; }
    .theme-bg-secondary { background-color: var(--bg-secondary) !important; }
    .theme-bg-card { background-color: var(--bg-card) !important; }
    .theme-bg-card:hover { background-color: var(--bg-card-hover) !important; }
    .theme-text-primary { color: var(--text-primary) !important; }
    .theme-text-secondary { color: var(--text-secondary) !important; }
    .theme-text-muted { color: var(--text-muted) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    
    /* Show/hide elements based on theme */
    .dark-mode .light-only { display: none !important; }
    .dark-mode .dark-only { display: block !important; }
    :not(.dark-mode) .dark-only { display: none !important; }
    :not(.dark-mode) .light-only { display: block !important; }
    
    /* Dark mode background patterns */
    .dark-mode .dark-bg-pattern {
        background-image: linear-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px), 
                          linear-gradient(90deg, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
        background-size: 50px 50px;
    }
    
    /* Adjust main content background for dark mode */
    .dark-mode main {
        background-color: #111827 !important;
    }
    
    /* Adjust cards and containers for dark mode */
    .dark-mode .bg-white {
        background-color: rgb(31, 41, 55) !important;
    }
    
    .dark-mode .bg-gray-50,
    .dark-mode .bg-gray-50\/50 {
        background-color: rgb(17, 24, 39) !important;
    }
    
    .dark-mode .text-gray-900 {
        color: #f9fafb !important;
    }
    
    .dark-mode .text-gray-600,
    .dark-mode .text-gray-500 {
        color: #d1d5db !important;
    }
    
    .dark-mode .text-gray-400 {
        color: #9ca3af !important;
    }
    
    .dark-mode .text-gray-700 {
        color: #e5e7eb !important;
    }
    
    .dark-mode .border-gray-100,
    .dark-mode .border-gray-200,
    .dark-mode .border-gray-300 {
        border-color: rgba(255, 255, 255, 0.1) !important;
    }
    
    /* Input group prefix (phone number +63) */
    .dark-mode .bg-gray-50.border-gray-300,
    .dark-mode span.bg-gray-50 {
        background-color: rgb(55, 65, 81) !important;
        border-color: rgba(255, 255, 255, 0.2) !important;
        color: #d1d5db !important;
    }
    
    /* Form input sizing (both modes) */
    input[type="text"],
    input[type="email"],
    input[type="tel"],
    input[type="password"],
    input[type="number"],
    input[type="date"],
    input[type="search"],
    textarea,
    select {
        padding: 0.625rem 0.875rem !important;
        font-size: 0.9375rem !important;
        line-height: 1.5 !important;
        border-width: 1px !important;
    }
    
    /* Form inputs for dark mode */
    .dark-mode input,
    .dark-mode textarea,
    .dark-mode select {
        background-color: rgb(55, 65, 81) !important;
        border-color: rgba(255, 255, 255, 0.2) !important;
        color: #f9fafb !important;
    }
    
    .dark-mode input::placeholder,
    .dark-mode textarea::placeholder {
        color: #9ca3af !important;
    }
    
    .dark-mode input:focus,
    .dark-mode textarea:focus,
    .dark-mode select:focus {
        border-color: #8b5cf6 !important;
        box-shadow: 0 0 0 1px #8b5cf6 !important;
    }
    
    .dark-mode input[type="checkbox"] {
        background-color: rgb(55, 65, 81) !important;
    }
    
    .dark-mode input[type="checkbox"]:checked {
        background-color: #8b5cf6 !important;
    }
    
    /* Labels */
    .dark-mode label {
        color: #e5e7eb !important;
    }
    
    .dark-mode .text-red-500 {
        color: #ef4444 !important;
    }
    
    .dark-mode .text-red-600 {
        color: #dc2626 !important;
    }
    
    .dark-mode .text-red-700 {
        color: #fca5a5 !important;
    }
    
    /* Alert/Error boxes */
    .dark-mode .bg-red-50 {
        background-color: rgba(239, 68, 68, 0.1) !important;
    }
    
    .dark-mode .border-red-200 {
        border-color: rgba(239, 68, 68, 0.3) !important;
    }
    
    /* Status badges */
    .dark-mode .bg-green-50 {
        background-color: rgba(34, 197, 94, 0.1) !important;
    }
    
    .dark-mode .text-green-700 {
        color: #86efac !important;
    }
    
    .dark-mode .border-green-200 {
        border-color: rgba(34, 197, 94, 0.3) !important;
    }
    
    /* Gray backgrounds in cards */
    .dark-mode .bg-gray-50\/30 {
        background-color: rgba(17, 24, 39, 0.5) !important;
    }
    
    .dark-mode .divide-gray-100 > * {
        border-color: rgba(255, 255, 255, 0.1) !important;
    }
    
    /* Hover states for dark mode */
    .dark-mode .hover\:bg-gray-50:hover,
    .dark-mode .hover\:bg-gray-50\/50:hover {
        background-color: rgba(55, 65, 81, 0.5) !important;
    }
    
    .dark-mode .hover\:border-blue-600:hover {
        border-color: #3b82f6 !important;
    }
    
    .dark-mode .hover\:bg-blue-50:hover {
        background-color: rgba(59, 130, 246, 0.1) !important;
    }
    
    .dark-mode .hover\:border-green-600:hover {
        border-color: #10b981 !important;
    }
    
    .dark-mode .hover\:bg-green-50:hover {
        background-color: rgba(16, 185, 129, 0.1) !important;
    }
    
    .dark-mode .hover\:border-amber-600:hover {
        border-color: #f59e0b !important;
    }
    
    .dark-mode .hover\:bg-amber-50:hover {
        background-color: rgba(245, 158, 11, 0.1) !important;
    }
    
    /* Button hover states */
    button#themeToggle:hover {
        background-color: rgba(107, 114, 128, 0.1);
    }
    
    .dark-mode button#themeToggle:hover {
        background-color: rgba(255, 255, 255, 0.1);
    }
</style>

<script>
    // Theme toggle functionality
    function initThemeToggle() {
        const themeToggle = document.getElementById('themeToggle');
        if (!themeToggle) return;
        
        const html = document.documentElement;
        
        themeToggle.addEventListener('click', () => {
            if (html.classList.contains('dark-mode')) {
                html.classList.remove('dark-mode');
                localStorage.setItem('theme', 'light');
            } else {
                html.classList.add('dark-mode');
                localStorage.setItem('theme', 'dark');
            }
        });
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initThemeToggle);
    } else {
        initThemeToggle();
    }
</script>
