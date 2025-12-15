// API Configuration
const API_BASE = 'http://localhost/template-marketplace/backend/api';

// Utility Functions
class Utils {
    constructor() {
        this.notifications = [];
    }
    
    // Show notification
    showNotification(message, type = 'success', duration = 5000) {
        const container = document.getElementById('notifications-container');
        if (!container) {
            console.error('Notification container not found');
            return;
        }
        
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <i class="fas fa-${this.getNotificationIcon(type)}"></i>
            <span>${message}</span>
        `;
        
        container.appendChild(notification);
        
        // Show with animation
        setTimeout(() => notification.classList.add('show'), 10);
        
        // Remove after duration
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 500);
        }, duration);
        
        return notification;
    }
    
    getNotificationIcon(type) {
        const icons = {
            success: 'check-circle',
            warning: 'exclamation-triangle',
            error: 'exclamation-circle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    }
    
    // Show confirmation dialog
    showConfirmation(title, message) {
        return new Promise((resolve) => {
            const modal = document.getElementById('confirmation-modal');
            if (!modal) {
                resolve(confirm(message));
                return;
            }
            
            const titleEl = document.getElementById('confirmation-title');
            const messageEl = document.getElementById('confirmation-message');
            const cancelBtn = document.getElementById('cancel-action');
            const confirmBtn = document.getElementById('confirm-action');
            
            if (!titleEl || !messageEl || !cancelBtn || !confirmBtn) {
                resolve(confirm(message));
                return;
            }
            
            titleEl.textContent = title;
            messageEl.textContent = message;
            
            modal.style.display = 'flex';
            
            const handleConfirm = () => {
                modal.style.display = 'none';
                resolve(true);
                cleanup();
            };
            
            const handleCancel = () => {
                modal.style.display = 'none';
                resolve(false);
                cleanup();
            };
            
            const cleanup = () => {
                confirmBtn.removeEventListener('click', handleConfirm);
                cancelBtn.removeEventListener('click', handleCancel);
            };
            
            confirmBtn.addEventListener('click', handleConfirm);
            cancelBtn.addEventListener('click', handleCancel);
        });
    }
    
    // Format currency
    formatCurrency(amount) {
        return new Intl.NumberFormat('en-KE', {
            style: 'currency',
            currency: 'KES',
            minimumFractionDigits: 2
        }).format(amount);
    }
    
    // Format date
    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-KE', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    // Validate email
    validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    // Validate phone (Kenyan)
    validatePhone(phone) {
        const re = /^[0-9]{9}$/;
        return re.test(phone);
    }
    
    // Sanitize input
    sanitize(input) {
        const div = document.createElement('div');
        div.textContent = input;
        return div.innerHTML;
    }
    
    // Debounce function
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // Load platform settings
    async loadPlatformSettings() {
        try {
            const response = await fetch(`${API_BASE}/settings.php`);
            const data = await response.json();
            
            if (data.success) {
                return data.data;
            }
        } catch (error) {
            console.error('Failed to load settings:', error);
        }
        return null;
    }
    
    // Update platform UI with settings
    updatePlatformUI(settings) {
        if (!settings) return;
        
        // Platform name
        const nameElements = document.querySelectorAll('[id*="platform-name"], [id*="copyright-name"]');
        nameElements.forEach(el => {
            if (settings.platform_name) el.textContent = settings.platform_name;
        });
        
        // Contact info
        if (settings.contact_phone) {
            const phoneElements = document.querySelectorAll('[id*="contact-phone"], #footer-phone');
            phoneElements.forEach(el => el.textContent = settings.contact_phone);
            
            const phoneLink = document.getElementById('phone-link');
            if (phoneLink) phoneLink.href = `tel:${settings.contact_phone.replace(/\s+/g, '')}`;
        }
        
        if (settings.contact_email) {
            const emailElements = document.querySelectorAll('[id*="contact-email"], #footer-email');
            emailElements.forEach(el => el.textContent = settings.contact_email);
            
            const emailLink = document.getElementById('email-link');
            if (emailLink) emailLink.href = `mailto:${settings.contact_email}`;
        }
        
        // Social links
        if (settings.tiktok_url) {
            const tiktokLink = document.getElementById('tiktok-link');
            if (tiktokLink) tiktokLink.href = settings.tiktok_url;
        }
        
        if (settings.facebook_url) {
            const facebookLink = document.getElementById('facebook-link');
            if (facebookLink) facebookLink.href = settings.facebook_url;
        }
        
        // Logo
        if (settings.logo_url) {
            const logoElements = document.querySelectorAll('.platform-logo, .footer-logo');
            logoElements.forEach(el => {
                el.src = settings.logo_url;
                el.style.display = 'block';
            });
            
            // Favicon
            const favicon = document.querySelector('link[rel="icon"]');
            if (favicon) favicon.href = settings.logo_url;
        }
    }
    
    // Upload file
    async uploadFile(file, type = 'image') {
        const formData = new FormData();
        formData.append('file', file);
        
        try {
            const response = await fetch(`${API_BASE}/settings.php?action=upload`, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Upload error:', error);
            return { success: false, message: 'Upload failed' };
        }
    }
}

// Initialize utils
const utils = new Utils();

// Modal handling
function setupModals() {
    // Close modals when clicking outside
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal')) {
            e.target.style.display = 'none';
        }
    });
    
    // Close modal buttons
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('close-modal') || 
            e.target.closest('.close-modal')) {
            const modal = e.target.closest('.modal');
            if (modal) modal.style.display = 'none';
        }
    });
    
    // Escape key to close modals
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (modal.style.display === 'flex') {
                    modal.style.display = 'none';
                }
            });
        }
    });
}

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', () => {
    // Set current year
    const yearElements = document.querySelectorAll('[id*="current-year"]');
    const currentYear = new Date().getFullYear();
    yearElements.forEach(el => el.textContent = currentYear);
    
    // Setup modals
    setupModals();
    
    // Auto-hide loading screen after 5 seconds
    setTimeout(() => {
        const loadingScreen = document.getElementById('loading-screen');
        if (loadingScreen) {
            loadingScreen.style.opacity = '0';
            setTimeout(() => {
                loadingScreen.style.display = 'none';
            }, 500);
        }
    }, 5000);
});