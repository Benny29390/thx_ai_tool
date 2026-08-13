/**
 * KI Text Tool - Installer JavaScript
 */

(function() {
    'use strict';

    // Password Strength Indicator
    const passwordInput = document.getElementById('admin_password');
    const strengthIndicator = document.getElementById('password-strength');

    if (passwordInput && strengthIndicator) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;

            // Length
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;

            // Uppercase
            if (/[A-Z]/.test(password)) strength++;

            // Lowercase
            if (/[a-z]/.test(password)) strength++;

            // Numbers
            if (/[0-9]/.test(password)) strength++;

            // Special chars
            if (/[^A-Za-z0-9]/.test(password)) strength++;

            // Update indicator
            strengthIndicator.className = 'password-strength';
            if (password.length === 0) {
                // No class
            } else if (strength < 3) {
                strengthIndicator.classList.add('weak');
            } else if (strength < 5) {
                strengthIndicator.classList.add('medium');
            } else {
                strengthIndicator.classList.add('strong');
            }
        });
    }

    // Show/Hide Password
    document.querySelectorAll('.toggle-password').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const input = this.previousElementSibling;
            if (input.type === 'password') {
                input.type = 'text';
                this.textContent = 'Verbergen';
            } else {
                input.type = 'password';
                this.textContent = 'Anzeigen';
            }
        });
    });

    // Form Validation
    document.querySelectorAll('.install-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const submitBtn = form.querySelector('[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Bitte warten...';
            }
        });
    });

})();
