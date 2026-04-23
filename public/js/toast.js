/**
 * Toast Notification System
 * Simple, lightweight toast notifications
 */

class Toast {
  constructor() {
    this.container = null;
    this.init();
  }

  init() {
    // Create container if it doesn't exist
    if (!document.getElementById('toast-container')) {
      this.container = document.createElement('div');
      this.container.id = 'toast-container';
      this.container.className = 'toast-container';
      document.body.appendChild(this.container);
    } else {
      this.container = document.getElementById('toast-container');
    }
  }

  show(message, type = 'info', title = '', duration = 4000) {
    const toast = this.createToast(message, type, title);
    this.container.appendChild(toast);

    // Auto-remove after duration
    setTimeout(() => {
      this.remove(toast);
    }, duration);

    return toast;
  }

  createToast(message, type, title) {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;

    // Icon based on type
    const icons = {
      success: '✓',
      error: '✕',
      warning: '!',
      info: 'i'
    };

    // Default titles
    const defaultTitles = {
      success: 'Success',
      error: 'Error',
      warning: 'Warning',
      info: 'Info'
    };

    const finalTitle = title || defaultTitles[type] || 'Notification';

    toast.innerHTML = `
      <div class="toast-icon">${icons[type] || 'i'}</div>
      <div class="toast-content">
        <div class="toast-title">${finalTitle}</div>
        <div class="toast-message">${message}</div>
      </div>
      <button class="toast-close" onclick="window.toast.remove(this.parentElement)">×</button>
    `;

    return toast;
  }

  remove(toast) {
    toast.classList.add('hiding');
    setTimeout(() => {
      if (toast.parentElement) {
        toast.parentElement.removeChild(toast);
      }
    }, 300);
  }

  success(message, title = 'Success', duration = 4000) {
    return this.show(message, 'success', title, duration);
  }

  error(message, title = 'Error', duration = 5000) {
    return this.show(message, 'error', title, duration);
  }

  warning(message, title = 'Warning', duration = 4500) {
    return this.show(message, 'warning', title, duration);
  }

  info(message, title = 'Info', duration = 4000) {
    return this.show(message, 'info', title, duration);
  }
}

// Initialize global toast instance
window.toast = new Toast();