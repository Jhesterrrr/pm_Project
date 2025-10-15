/**
 * Theme Management System
 * Handles dark/light mode switching and persistence
 */

class ThemeManager {
  constructor() {
    this.theme = this.getStoredTheme() || this.getSystemTheme();
    this.init();
  }

  init() {
    this.applyTheme();
    this.createThemeToggle();
    this.bindEvents();
  }

  getSystemTheme() {
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }

  getStoredTheme() {
    try {
      return localStorage.getItem('theme');
    } catch (e) {
      return null;
    }
  }

  setStoredTheme(theme) {
    try {
      localStorage.setItem('theme', theme);
    } catch (e) {
      console.warn('Could not save theme preference');
    }
  }

  applyTheme() {
    document.documentElement.setAttribute('data-theme', this.theme);
    document.body.classList.add('fade-in');
    
    // Update theme toggle if it exists
    const toggle = document.querySelector('.theme-toggle');
    if (toggle) {
      toggle.setAttribute('data-theme', this.theme);
    }
  }

  toggleTheme() {
    this.theme = this.theme === 'light' ? 'dark' : 'light';
    this.setStoredTheme(this.theme);
    this.applyTheme();
    
    // Dispatch custom event for other components
    window.dispatchEvent(new CustomEvent('themeChanged', { 
      detail: { theme: this.theme } 
    }));
  }

  createThemeToggle() {
    // Check if toggle already exists
    if (document.querySelector('.theme-toggle')) {
      return;
    }

    const toggle = document.createElement('div');
    toggle.className = 'theme-toggle';
    toggle.setAttribute('data-theme', this.theme);
    toggle.innerHTML = `
      <span class="theme-toggle-icon sun">☀️</span>
      <span class="theme-toggle-icon moon">🌙</span>
    `;
    
    toggle.addEventListener('click', () => this.toggleTheme());
    
    // Add to header if it exists
    const header = document.querySelector('.main-header');
    if (header) {
      const userArea = header.querySelector('.user-area');
      if (userArea) {
        userArea.insertBefore(toggle, userArea.firstChild);
      } else {
        header.appendChild(toggle);
      }
    }
  }

  bindEvents() {
    // Listen for system theme changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
      if (!this.getStoredTheme()) {
        this.theme = e.matches ? 'dark' : 'light';
        this.applyTheme();
      }
    });

    // Listen for theme changes from other tabs
    window.addEventListener('storage', (e) => {
      if (e.key === 'theme' && e.newValue !== this.theme) {
        this.theme = e.newValue;
        this.applyTheme();
      }
    });
  }

  // Public API
  getCurrentTheme() {
    return this.theme;
  }

  setTheme(theme) {
    if (theme === 'light' || theme === 'dark') {
      this.theme = theme;
      this.setStoredTheme(this.theme);
      this.applyTheme();
    }
  }
}

// Initialize theme manager when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  window.themeManager = new ThemeManager();
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
  module.exports = ThemeManager;
}