/**
 * KI Text Tool - Haupt-JavaScript
 */

(function() {
    'use strict';

    // ========================================
    // App Namespace
    // ========================================
    window.App = {
        // API Base URL
        apiUrl: '/api/v1',

        // CSRF Token
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.content ||
                   document.querySelector('input[name="csrf_token"]')?.value || '',

        // Initialize
        init: function() {
            this.initFlashMessages();
            this.initForms();
            this.initModals();
            this.initSidebar();
        },

        // Flash Messages Auto-Hide
        initFlashMessages: function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(function() {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
        },

        // Form Validation & AJAX
        initForms: function() {
            document.querySelectorAll('form[data-ajax]').forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    App.submitForm(form);
                });
            });
        },

        // Modal System
        initModals: function() {
            // Close on backdrop click
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('modal')) {
                    App.closeModal(e.target);
                }
            });

            // Close on ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    document.querySelectorAll('.modal.open').forEach(App.closeModal);
                }
            });

            // Close buttons
            document.querySelectorAll('[data-close-modal]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const modal = btn.closest('.modal');
                    if (modal) App.closeModal(modal);
                });
            });
        },

        // Mobile Sidebar (Toggle entfernt — Funktion bleibt als no-op fuer Kompatibilitaet)
        initSidebar: function() {},

        // ========================================
        // API Methods
        // ========================================
        request: async function(method, endpoint, data = null) {
            const url = this.apiUrl + endpoint;
            const options = {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': this.csrfToken
                }
            };

            if (data && method !== 'GET') {
                options.body = JSON.stringify(data);
            }

            try {
                const response = await fetch(url, options);
                const json = await response.json();

                if (!response.ok) {
                    throw new Error(json.message || 'Anfrage fehlgeschlagen');
                }

                return json;
            } catch (error) {
                console.error('API Error:', error);
                throw error;
            }
        },

        get: function(endpoint) {
            return this.request('GET', endpoint);
        },

        post: function(endpoint, data) {
            return this.request('POST', endpoint, data);
        },

        put: function(endpoint, data) {
            return this.request('PUT', endpoint, data);
        },

        delete: function(endpoint) {
            return this.request('DELETE', endpoint);
        },

        // ========================================
        // Form Handling
        // ========================================
        submitForm: async function(form) {
            const submitBtn = form.querySelector('[type="submit"]');
            const originalText = submitBtn?.textContent;

            try {
                // Disable form
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Wird gespeichert...';
                }

                // Collect data (mit Array-Support für Checkboxen)
                const formData = new FormData(form);
                const data = {};
                for (const [key, value] of formData.entries()) {
                    // Handle array fields (e.g., rule_ids[])
                    if (key.endsWith('[]')) {
                        const arrayKey = key.slice(0, -2);
                        if (!data[arrayKey]) {
                            data[arrayKey] = [];
                        }
                        data[arrayKey].push(value);
                    } else {
                        data[key] = value;
                    }
                }

                // Get method and endpoint
                const method = form.dataset.method || form.method || 'POST';
                const endpoint = form.dataset.endpoint || form.action.replace(this.apiUrl, '');

                // Submit
                const response = await this.request(method.toUpperCase(), endpoint, data);

                // Success callback
                if (form.dataset.onSuccess) {
                    const callback = window[form.dataset.onSuccess];
                    if (typeof callback === 'function') {
                        callback(response);
                    }
                }

                // Show success message
                if (response.message) {
                    this.showNotification(response.message, 'success');
                }

                // Redirect if specified
                if (form.dataset.redirect) {
                    window.location.href = form.dataset.redirect;
                }

            } catch (error) {
                this.showNotification(error.message, 'error');
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            }
        },

        // ========================================
        // Toast Notifications
        // ========================================
        toastContainer: null,
        toastQueue: [],

        showNotification: function(message, type = 'info', duration = 5000) {
            // Container erstellen falls nicht vorhanden
            if (!this.toastContainer) {
                this.toastContainer = document.createElement('div');
                this.toastContainer.className = 'toast-container';
                document.body.appendChild(this.toastContainer);
            }

            // Icon basierend auf Typ
            const icons = {
                success: '✓',
                error: '✕',
                warning: '⚠',
                info: 'ℹ'
            };

            // Toast erstellen
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <div class="toast-icon">${icons[type] || icons.info}</div>
                <div class="toast-content">
                    <div class="toast-message">${this.escapeHtml(message)}</div>
                </div>
                <button class="toast-close" aria-label="Schließen">×</button>
                <div class="toast-progress">
                    <div class="toast-progress-bar"></div>
                </div>
            `;

            // Close Button Handler
            const closeBtn = toast.querySelector('.toast-close');
            closeBtn.addEventListener('click', () => this.removeToast(toast));

            // Zum Container hinzufügen
            this.toastContainer.appendChild(toast);

            // Animation starten
            requestAnimationFrame(() => {
                toast.classList.add('toast-visible');
            });

            // Progress-Bar Animation
            const progressBar = toast.querySelector('.toast-progress-bar');
            progressBar.style.transition = `width ${duration}ms linear`;
            requestAnimationFrame(() => {
                progressBar.style.width = '0%';
            });

            // Auto-Close Timer
            const timeoutId = setTimeout(() => {
                this.removeToast(toast);
            }, duration);

            // Timer stoppen bei Hover
            toast.addEventListener('mouseenter', () => {
                clearTimeout(timeoutId);
                progressBar.style.transition = 'none';
                progressBar.style.width = progressBar.offsetWidth + 'px';
            });

            toast.addEventListener('mouseleave', () => {
                const remainingWidth = progressBar.offsetWidth;
                const containerWidth = toast.offsetWidth;
                const remainingTime = (remainingWidth / containerWidth) * duration;

                progressBar.style.transition = `width ${remainingTime}ms linear`;
                requestAnimationFrame(() => {
                    progressBar.style.width = '0%';
                });

                toast._timeoutId = setTimeout(() => {
                    this.removeToast(toast);
                }, remainingTime);
            });

            toast._timeoutId = timeoutId;

            return toast;
        },

        removeToast: function(toast) {
            if (!toast || toast._removing) return;
            toast._removing = true;

            clearTimeout(toast._timeoutId);
            toast.classList.remove('toast-visible');
            toast.classList.add('toast-hiding');

            setTimeout(() => {
                toast.remove();
                // Container entfernen wenn leer
                if (this.toastContainer && this.toastContainer.children.length === 0) {
                    this.toastContainer.remove();
                    this.toastContainer = null;
                }
            }, 300);
        },

        // ========================================
        // Modals
        // ========================================
        openModal: function(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('open');
                document.body.style.overflow = 'hidden';
            }
        },

        closeModal: function(modal) {
            if (typeof modal === 'string') {
                modal = document.getElementById(modal);
            }
            if (modal) {
                modal.classList.remove('open');
                document.body.style.overflow = '';
            }
        },

        // ========================================
        // Utilities
        // ========================================
        formatNumber: function(num) {
            return new Intl.NumberFormat('de-DE').format(num);
        },

        formatDate: function(date) {
            return new Intl.DateTimeFormat('de-DE', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            }).format(new Date(date));
        },

        formatDateTime: function(date) {
            return new Intl.DateTimeFormat('de-DE', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            }).format(new Date(date));
        },

        debounce: function(func, wait) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        },

        // Word Count
        countWords: function(text) {
            if (!text) return 0;
            return text.trim().split(/\s+/).filter(w => w.length > 0).length;
        },

        // Escape HTML
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        // Confirm Dialog
        confirm: function(message) {
            return new Promise(function(resolve) {
                resolve(window.confirm(message));
            });
        },

        // Switch Customer
        switchCustomer: function(customerId) {
            fetch(this.apiUrl + '/switch-customer', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfToken
                },
                body: JSON.stringify({ customer_id: customerId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Seite neu laden um Daten für neuen Kunden anzuzeigen
                    window.location.reload();
                } else {
                    this.showNotification(data.message || 'Fehler beim Wechseln', 'error');
                }
            })
            .catch(error => {
                this.showNotification('Fehler beim Wechseln des Kunden', 'error');
            });
        }
    };

    // Global switchCustomer function for inline handlers
    window.switchCustomer = function(customerId) {
        App.switchCustomer(customerId);
    };

    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        App.init();
    });

})();
