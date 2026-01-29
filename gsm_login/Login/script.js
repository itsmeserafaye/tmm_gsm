// Government Services Management System - Login Page JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize the page
    initializePage();
    
    // Set up event listeners
    setupEventListeners();
    
    // Update date and time
    updateDateTime();
    setInterval(updateDateTime, 1000);
});

const GSM_API_BASE = window.location.pathname.includes('/gsm_login/Login/') ? '' : 'Login/';

function resolveInputEl(arg) {
    return (arg && arg.target) ? arg.target : arg;
}

function getPortalMode() {
    const mode = new URLSearchParams(window.location.search).get('mode') || '';
    return (mode === 'operator') ? 'operator' : ((mode === 'commuter') ? 'commuter' : 'staff');
}

function getRootUrl() {
    const path = String(window.location.pathname || '');
    if (path.includes('/gsm_login/')) {
        const root = path.replace(/\/gsm_login\/.*$/, '');
        return root === '/' ? '' : root;
    }
    return '';
}

function setLoginAlert(message, type = 'error') {
    const el = document.getElementById('loginAlert');
    if (!el) return;
    const msg = String(message || '').trim();
    if (!msg) {
        el.classList.add('hidden');
        el.textContent = '';
        return;
    }
    el.classList.remove('hidden');
    if (type === 'success') {
        el.className = 'rounded-lg border px-4 py-3 text-sm font-semibold border-emerald-200 bg-emerald-50 text-emerald-800';
    } else if (type === 'warning') {
        el.className = 'rounded-lg border px-4 py-3 text-sm font-semibold border-amber-200 bg-amber-50 text-amber-800';
    } else {
        el.className = 'rounded-lg border px-4 py-3 text-sm font-semibold border-rose-200 bg-rose-50 text-rose-800';
    }
    el.textContent = msg;
}

window.addEventListener('error', function(e) {
    try {
        const msg = (e && e.message) ? String(e.message) : 'Unexpected error occurred.';
        setLoginAlert(msg, 'error');
        if (typeof showNotification === 'function') showNotification(msg, 'error');
    } catch (err) {}
});

function getOrCreateDeviceId() {
    try {
        const cookieMatch = document.cookie.match(/(?:^|;\s*)gsm_device_id=([^;]+)/);
        const cookieVal = cookieMatch ? decodeURIComponent(cookieMatch[1]) : '';
        if (cookieVal && cookieVal.length >= 12) {
            try { localStorage.setItem('gsm_device_id', cookieVal); } catch (e) {}
            return cookieVal;
        }

        const existing = localStorage.getItem('gsm_device_id');
        if (existing && existing.length >= 12) {
            try {
                const exp = new Date();
                exp.setFullYear(exp.getFullYear() + 10);
                document.cookie = 'gsm_device_id=' + encodeURIComponent(existing) + '; expires=' + exp.toUTCString() + '; path=/; SameSite=Lax';
            } catch (e) {}
            return existing;
        }

        const id = (window.crypto && typeof window.crypto.randomUUID === 'function')
            ? window.crypto.randomUUID()
            : (Date.now().toString(16) + '-' + Math.random().toString(16).slice(2) + '-' + Math.random().toString(16).slice(2));
        localStorage.setItem('gsm_device_id', id);
        try {
            const exp = new Date();
            exp.setFullYear(exp.getFullYear() + 10);
            document.cookie = 'gsm_device_id=' + encodeURIComponent(id) + '; expires=' + exp.toUTCString() + '; path=/; SameSite=Lax';
        } catch (e) {}
        return id;
    } catch (e) {
        return (Date.now().toString(16) + '-' + Math.random().toString(16).slice(2));
    }
}

function initializePage() {
    // Add loading animation to buttons
    addLoadingStates();
    
    // Initialize form validation
    initializeFormValidation();
    
    // Add smooth scrolling
    addSmoothScrolling();

    try {
        const portalMode = getPortalMode();
        const remembered = localStorage.getItem('gsm_remember_' + portalMode) === '1';
        const email = localStorage.getItem('gsm_email_' + portalMode) || '';
        const pwd = localStorage.getItem('gsm_password_' + portalMode) || '';
        const rememberEl = document.getElementById('rememberMe');
        const emailEl = document.getElementById('email');
        const pwdEl = document.getElementById('password');
        if (rememberEl) rememberEl.checked = remembered;
        if (remembered && emailEl && email) emailEl.value = email;
        if (remembered && pwdEl && pwd) pwdEl.value = pwd;
    } catch (e) {}
}

function setupEventListeners() {
    // Login form submission
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLoginSubmit);
    }

    const operatorPlateWrap = document.getElementById('operatorPlateWrap');
    const plateInput = document.getElementById('plate_number');
    if (operatorPlateWrap && plateInput) {
        operatorPlateWrap.classList.add('hidden');
        plateInput.required = false;
    }

    const portalMode = getPortalMode();
    const openForgotBtn = document.getElementById('openForgotPassword');
    if (openForgotBtn) openForgotBtn.classList.toggle('hidden', portalMode === 'staff');

    const rememberEl = document.getElementById('rememberMe');
    if (loginForm) {
        loginForm.addEventListener('submit', () => {
            try {
                const remember = !!(rememberEl && rememberEl.checked);
                if (remember) {
                    localStorage.setItem('gsm_remember_' + portalMode, '1');
                    localStorage.setItem('gsm_email_' + portalMode, String((document.getElementById('email') && document.getElementById('email').value) || '').trim());
                    localStorage.setItem('gsm_password_' + portalMode, String((document.getElementById('password') && document.getElementById('password').value) || ''));
                } else {
                    localStorage.removeItem('gsm_remember_' + portalMode);
                    localStorage.removeItem('gsm_email_' + portalMode);
                    localStorage.removeItem('gsm_password_' + portalMode);
                }
            } catch (e) {}
        });
    }

    const opRegModal = document.getElementById('operatorRegisterModal');
    const opRegOpen = document.getElementById('btnOperatorRegisterOpen');
    const opRegCancel = document.getElementById('btnOperatorRegisterCancel');
    const opRegClose = document.getElementById('btnOperatorRegisterClose');
    const opRegForm = document.getElementById('operatorRegisterForm');
    const opRegSubmit = document.getElementById('btnOperatorRegisterSubmit');
    if (opRegOpen && (portalMode === 'staff' || portalMode === 'operator')) {
        const row = opRegOpen.closest('p') || opRegOpen;
        row.classList.add('hidden');
    }
    if (opRegOpen && opRegModal && opRegCancel && opRegForm) {
        const opRecaptcha = document.getElementById('opRecaptcha');
        let opRecaptchaWidgetId = null;
        const tryRenderOpRecaptcha = () => {
            if (!opRecaptcha || !window.grecaptcha || opRecaptchaWidgetId !== null) return;
            const siteKey = opRecaptcha.getAttribute('data-sitekey') || '';
            if (!siteKey) return;
            opRecaptchaWidgetId = window.grecaptcha.render(opRecaptcha, { sitekey: siteKey });
        };
        const open = () => {
            opRegModal.classList.remove('hidden');
            opRegModal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
            tryRenderOpRecaptcha();
        };
        const close = () => {
            opRegModal.classList.add('hidden');
            opRegModal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
            opRegForm.reset();
            if (window.grecaptcha && opRecaptchaWidgetId !== null) window.grecaptcha.reset(opRecaptchaWidgetId);
        };
        opRegOpen.addEventListener('click', open);
        opRegCancel.addEventListener('click', close);
        if (opRegClose) opRegClose.addEventListener('click', close);
        opRegModal.addEventListener('click', (e) => { if (e.target === opRegModal) close(); });

        const opPwd = document.getElementById('opRegPassword');
        const opConfirm = document.getElementById('opRegConfirmPassword');
        if (opPwd) {
            opPwd.addEventListener('input', function(){
                validateRegPassword(this);
                updatePasswordChecklistFor('opPwdChecklist', this.value);
                if (opConfirm && opConfirm.value) { validateConfirmPasswordFor('opRegPassword', 'opRegConfirmPassword', true, 'op-confirm-error'); }
            });
            opPwd.addEventListener('blur', function(){
                validateRegPassword(this, true);
                updatePasswordChecklistFor('opPwdChecklist', this.value);
                if (opConfirm && opConfirm.value) { validateConfirmPasswordFor('opRegPassword', 'opRegConfirmPassword', true, 'op-confirm-error'); }
            });
        }
        if (opConfirm) {
            opConfirm.addEventListener('input', function(){ validateConfirmPasswordFor('opRegPassword', 'opRegConfirmPassword', true, 'op-confirm-error'); });
            opConfirm.addEventListener('blur', function(){ validateConfirmPasswordFor('opRegPassword', 'opRegConfirmPassword', true, 'op-confirm-error'); });
        }
        opRegForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const data = serializeForm(opRegForm);
            const pwd = String(data.password || '');
            const cpwd = String(data.confirm_password || '');
            const pwdEl = document.getElementById('opRegPassword');
            if (!validateRegPassword(pwdEl, true)) { showNotification('Password does not meet requirements.', 'warning'); return; }
            if (!validateConfirmPasswordFor('opRegPassword', 'opRegConfirmPassword', true, 'op-confirm-error')) { return; }
            tryRenderOpRecaptcha();
            const captchaRequired = !!opRecaptcha;
            const captchaResponse = (window.grecaptcha && opRecaptchaWidgetId !== null) ? window.grecaptcha.getResponse(opRecaptchaWidgetId) : '';
            if (captchaRequired && !captchaResponse) { showNotification('Please complete the reCAPTCHA.', 'warning'); return; }
            if (opRegSubmit) showLoadingState(opRegSubmit);
            makeAPICall('operator_register.php', {
                plate_number: String(data.plate_number || ''),
                full_name: String(data.full_name || ''),
                email: String(data.email || ''),
                password: pwd,
                confirm_password: cpwd,
                recaptcha_token: captchaResponse || ''
            }, 'POST')
            .then((res) => {
                if (!res || !res.ok) { showNotification((res && res.message) ? res.message : 'Operator registration failed', 'error'); return; }
                showNotification(res.message || 'Operator registration successful!', 'success');
                const redirect = res.data && res.data.redirect ? res.data.redirect : null;
                close();
                if (redirect) window.location.href = redirect;
            })
            .catch(() => {})
            .finally(() => {
                if (opRegSubmit) { opRegSubmit.innerHTML = 'Register'; opRegSubmit.disabled = false; }
            });
        });
    }

    const opFromCitizen = document.getElementById('openOperatorRegisterFromCitizen');
    if (opFromCitizen && opRegModal) {
        opFromCitizen.addEventListener('click', () => {
            hideRegisterForm();
            if (opRegOpen) {
                opRegOpen.click();
            } else {
                opRegModal.classList.remove('hidden');
                opRegModal.classList.add('flex');
                document.body.classList.add('overflow-hidden');
            }
        });
    }
    
    // Social login buttons (only target real social buttons)
    const socialButtons = document.querySelectorAll('.social-btn');
    socialButtons.forEach(button => {
        button.addEventListener('click', handleSocialLogin);
    });
    
    // Email input validation
    const emailInput = document.getElementById('email');
    if (emailInput) {
        emailInput.addEventListener('blur', validateEmail);
        emailInput.addEventListener('input', clearEmailError);
    }
    
    // Password input validation
    const passwordInput = document.getElementById('password');
    if (passwordInput) {
        passwordInput.addEventListener('blur', validatePassword);
        passwordInput.addEventListener('input', clearPasswordError);
    }

    const togglePasswordBtn = document.getElementById('togglePassword');
    if (togglePasswordBtn && passwordInput) {
        togglePasswordBtn.addEventListener('click', () => {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            const icon = togglePasswordBtn.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-eye', !isPassword);
                icon.classList.toggle('fa-eye-slash', isPassword);
            }
        });
    }
    
    // Register toggle
    const showRegister = document.getElementById('showRegister');
    const hideInline = (el) => {
        if (!el) return;
        const row = el.closest('p') || el;
        row.classList.add('hidden');
    };
    if (showRegister) {
        if (portalMode === 'staff') {
            hideInline(showRegister);
        } else if (portalMode === 'operator') {
            showRegister.textContent = 'Register as Operator';
            if (opRegOpen) hideInline(opRegOpen);
            showRegister.addEventListener('click', () => { if (opRegOpen) opRegOpen.click(); });
        } else {
            showRegister.addEventListener('click', showRegisterForm);
        }
    }
    const cancelRegister = document.getElementById('cancelRegister');
    if (cancelRegister) {
        cancelRegister.addEventListener('click', hideRegisterForm);
    }
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', handleRegisterSubmit);
    }
    const regPassword = document.getElementById('regPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    if (regPassword) {
        regPassword.addEventListener('input', function(){
            validateRegPassword(this);
            updatePasswordChecklist(this.value);
            const cp = document.getElementById('confirmPassword');
            if (cp && cp.value) { validateConfirmPassword(true); }
        });
        regPassword.addEventListener('blur', function(){
            validateRegPassword(this, true);
            updatePasswordChecklist(this.value);
            const cp = document.getElementById('confirmPassword');
            if (cp && cp.value) { validateConfirmPassword(true); }
        });
    }
    if (confirmPassword) {
        confirmPassword.addEventListener('input', function(){ validateConfirmPassword(true); });
        confirmPassword.addEventListener('blur', function(){ validateConfirmPassword(true); });
    }
    const toggles = document.querySelectorAll('.toggle-password');
    toggles.forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (!input) return;
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            const icon = btn.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            }
        });
    });
    const noMiddleName = document.getElementById('noMiddleName');
    if (noMiddleName) {
        noMiddleName.addEventListener('change', function() {
            const middle = document.getElementById('middleName');
            const asterisk = document.getElementById('middleAsterisk');
            if (!middle) return;
            middle.disabled = this.checked;
            middle.required = !this.checked;
            if (asterisk) {
                asterisk.style.display = this.checked ? 'none' : 'inline';
            }
            if (this.checked) middle.value = '';
        });
    }

    // Terms modal wiring
    const openTerms = document.getElementById('openTerms');
    const footerTerms = document.getElementById('footerTerms');
    const termsModal = document.getElementById('termsModal');
    const closeTerms = document.getElementById('closeTerms');
    const closeTermsBottom = document.getElementById('closeTermsBottom');
    const openPrivacy = document.getElementById('openPrivacy');
    const footerPrivacy = document.getElementById('footerPrivacy');
    const privacyModal = document.getElementById('privacyModal');
    const closePrivacy = document.getElementById('closePrivacy');
    const closePrivacyBottom = document.getElementById('closePrivacyBottom');
    function showTerms() {
        if (!termsModal) return;
        termsModal.classList.remove('hidden');
        termsModal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
    }
    function hideTerms() {
        if (!termsModal) return;
        termsModal.classList.add('hidden');
        termsModal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
    }
    if (openTerms) openTerms.addEventListener('click', showTerms);
    if (footerTerms) footerTerms.addEventListener('click', showTerms);
    if (closeTerms) closeTerms.addEventListener('click', hideTerms);
    if (closeTermsBottom) closeTermsBottom.addEventListener('click', hideTerms);
    if (termsModal) {
        termsModal.addEventListener('click', (e) => {
            if (e.target === termsModal) hideTerms();
        });
    }

    function showPrivacy() {
        if (!privacyModal) return;
        privacyModal.classList.remove('hidden');
        privacyModal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
    }
    function hidePrivacy() {
        if (!privacyModal) return;
        privacyModal.classList.add('hidden');
        privacyModal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
    }
    if (openPrivacy) openPrivacy.addEventListener('click', showPrivacy);
    if (footerPrivacy) footerPrivacy.addEventListener('click', showPrivacy);
    if (closePrivacy) closePrivacy.addEventListener('click', hidePrivacy);
    if (closePrivacyBottom) closePrivacyBottom.addEventListener('click', hidePrivacy);
    if (privacyModal) {
        privacyModal.addEventListener('click', (e) => {
            if (e.target === privacyModal) hidePrivacy();
        });
    }

    const openForgot = document.getElementById('openForgotPassword');
    const fpModal = document.getElementById('forgotPasswordModal');
    const fpClose = document.getElementById('fpClose');
    const fpCancel = document.getElementById('fpCancel');
    const fpSendOtp = document.getElementById('fpSendOtp');
    const fpForm = document.getElementById('forgotPasswordForm');
    const fpStep2 = document.getElementById('fpStep2');
    const fpEmail = document.getElementById('fpEmail');
    const fpNewPassword = document.getElementById('fpNewPassword');
    const fpConfirmPassword = document.getElementById('fpConfirmPassword');
    const fpConfirmError = document.getElementById('fpConfirmError');
    const fpOtpInputs = fpModal ? Array.from(fpModal.querySelectorAll('#fpOtpInputs .otp-input')) : [];
    let fpUserType = 'commuter';

    function fpOpen() {
        if (!fpModal) return;
        fpModal.classList.remove('hidden');
        fpModal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
        if (fpEmail) fpEmail.value = String((document.getElementById('email') && document.getElementById('email').value) || '').trim();
        fpUserType = portalMode === 'operator' ? 'operator' : 'commuter';
        if (fpStep2) fpStep2.classList.add('hidden');
        if (fpConfirmError) fpConfirmError.classList.add('hidden');
        fpOtpInputs.forEach(i => i.value = '');
        if (fpNewPassword) fpNewPassword.value = '';
        if (fpConfirmPassword) fpConfirmPassword.value = '';
        if (fpNewPassword) updatePasswordChecklistFor('fpPwdChecklist', fpNewPassword.value || '');
    }

    function fpCloseModal() {
        if (!fpModal) return;
        fpModal.classList.add('hidden');
        fpModal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
    }

    if (openForgot) openForgot.addEventListener('click', fpOpen);
    if (fpClose) fpClose.addEventListener('click', fpCloseModal);
    if (fpCancel) fpCancel.addEventListener('click', fpCloseModal);
    if (fpModal) fpModal.addEventListener('click', (e) => { if (e.target === fpModal) fpCloseModal(); });
    if (fpOtpInputs.length) setupOtpInputs(fpOtpInputs);
    if (fpNewPassword) {
        fpNewPassword.addEventListener('input', function(){ updatePasswordChecklistFor('fpPwdChecklist', this.value || ''); });
        fpNewPassword.addEventListener('blur', function(){ updatePasswordChecklistFor('fpPwdChecklist', this.value || ''); });
    }
    if (fpConfirmPassword) {
        fpConfirmPassword.addEventListener('input', function(){
            const ok = (this.value || '').trim() === ((fpNewPassword && fpNewPassword.value) ? fpNewPassword.value.trim() : '');
            if (fpConfirmError) fpConfirmError.classList.toggle('hidden', ok);
        });
    }
    if (fpSendOtp) fpSendOtp.addEventListener('click', () => {
        const emailVal = fpEmail ? String(fpEmail.value || '').trim() : '';
        if (!emailVal) { showNotification('Please enter your email.', 'warning'); return; }
        fpSendOtp.disabled = true;
        fpSendOtp.textContent = 'Sending...';
        makeAPICall('password_reset.php', {
            action: 'request',
            email: emailVal,
            user_type: fpUserType
        }, 'POST')
        .then((res) => {
            if (!res || !res.ok) { showNotification((res && res.message) ? res.message : 'Failed to send OTP.', 'error'); return; }
            showNotification(res.message || 'OTP sent. Please check your email.', 'success');
            if (fpStep2) fpStep2.classList.remove('hidden');
        })
        .finally(() => {
            fpSendOtp.disabled = false;
            fpSendOtp.textContent = 'Send OTP';
        });
    });
    if (fpForm) fpForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const emailVal = fpEmail ? String(fpEmail.value || '').trim() : '';
        const code = fpOtpInputs.map(i => i.value).join('');
        const np = fpNewPassword ? fpNewPassword.value : '';
        const cp = fpConfirmPassword ? fpConfirmPassword.value : '';
        if (!emailVal) { showNotification('Please enter your email.', 'warning'); return; }
        if (!code || code.length !== 6) { showNotification('Please enter the 6-digit OTP.', 'warning'); return; }
        if (!validateRegPassword(fpNewPassword, true)) { showNotification('Password does not meet requirements.', 'warning'); return; }
        if (np.trim() !== cp.trim()) { if (fpConfirmError) fpConfirmError.classList.remove('hidden'); showNotification('Passwords do not match.', 'error'); return; }
        const btn = document.getElementById('fpSubmit');
        if (btn) showLoadingState(btn);
        makeAPICall('password_reset.php', {
            action: 'confirm',
            email: emailVal,
            user_type: fpUserType,
            code,
            new_password: np,
            confirm_password: cp
        }, 'POST')
        .then((res) => {
            if (!res || !res.ok) { showNotification((res && res.message) ? res.message : 'Reset failed.', 'error'); return; }
            showNotification(res.message || 'Password updated.', 'success');
            fpCloseModal();
        })
        .finally(() => {
            if (btn) {
                btn.innerHTML = 'Reset Password';
                btn.disabled = false;
            }
        });
    });
}

function updateDateTime() {
    const now = new Date();
    const options = {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    };
    
    const dateTimeString = now.toLocaleDateString('en-US', options).toUpperCase();
    const dateTimeElement = document.getElementById('currentDateTime');
    
    if (dateTimeElement) {
        dateTimeElement.textContent = dateTimeString;
    }
}

function addLoadingStates() {
    const buttons = document.querySelectorAll('button');
    buttons.forEach(button => {
        button.addEventListener('click', function() {
            if (this.hasAttribute('data-no-loading')) return;
            if (this.classList.contains('social-btn')) {
                showLoadingState(this);
            }
        });
    });
}

function showLoadingState(button) {
    if (!button) return;
    if (button.dataset && button.dataset.loading === '1') return;
    if (button.dataset) {
        button.dataset.loading = '1';
        if (!button.dataset.originalHtml) button.dataset.originalHtml = button.innerHTML;
    }
    button.innerHTML = '<span class="loading"></span> Processing...';
    button.disabled = true;
    
    if (String(button.type || '') !== 'submit') {
        setTimeout(() => {
            if (!button || !button.dataset) return;
            if (button.dataset.loading !== '1') return;
            const original = button.dataset.originalHtml || button.innerHTML;
            button.innerHTML = original;
            button.disabled = false;
            delete button.dataset.loading;
            delete button.dataset.originalHtml;
        }, 2000);
    }
}

function resetLoadingState(button) {
    if (!button) return;
    if (button.dataset && button.dataset.originalHtml) {
        button.innerHTML = button.dataset.originalHtml;
    }
    button.disabled = false;
    if (button.dataset) {
        delete button.dataset.loading;
        delete button.dataset.originalHtml;
    }
}

function initializeFormValidation() {
    // Add real-time validation
    const inputs = document.querySelectorAll('input[required]');
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            validateField(this);
        });
    });
}

function validateField(field) {
    field = resolveInputEl(field);
    if (!field) return;
    const value = field.value.trim();
    const fieldName = field.name;
    
    // Remove existing error styling
    field.classList.remove('border-red-500', 'ring-red-500');
    field.classList.add('border-gray-300', 'ring-custom-secondary');
    
    // Remove existing error message
    const existingError = field.parentNode.querySelector('.error-message');
    if (existingError) {
        existingError.remove();
    }
    
    if (fieldName === 'email') {
        validateEmail(field);
    }
}

function validateEmail(input) {
    input = resolveInputEl(input);
    if (!input) return false;
    const email = input.value.trim();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (email && !emailRegex.test(email)) {
        showFieldError(input, 'Please enter a valid email address');
        return false;
    }
    
    clearEmailError(input);
    return true;
}

function clearEmailError(input) {
    input = resolveInputEl(input);
    if (!input) return;
    input.classList.remove('border-red-500', 'ring-red-500');
    input.classList.add('border-gray-300', 'ring-custom-secondary');
    
    const errorMessage = input.parentNode.querySelector('.error-message');
    if (errorMessage) {
        errorMessage.remove();
    }
}

function validatePassword(input) {
    input = resolveInputEl(input);
    if (!input) return false;
    const password = input.value.trim();
    
    if (password && password.length < 6) {
        showFieldError(input, 'Password must be at least 6 characters long');
        return false;
    }
    
    clearPasswordError(input);
    return true;
}

function clearPasswordError(input) {
    input = resolveInputEl(input);
    if (!input) return;
    input.classList.remove('border-red-500', 'ring-red-500');
    input.classList.add('border-gray-300', 'ring-custom-secondary');
    
    const errorMessage = input.parentNode.querySelector('.error-message');
    if (errorMessage) {
        errorMessage.remove();
    }
}

function showFieldError(field, message) {
    field = resolveInputEl(field);
    if (!field) return;
    field.classList.remove('border-gray-300', 'ring-custom-secondary');
    field.classList.add('border-red-500', 'ring-red-500');
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message text-red-500 text-sm mt-1';
    errorDiv.textContent = message;
    
    field.parentNode.appendChild(errorDiv);
}

function handleLoginSubmit(event) {
    event.preventDefault();
    
    const form = event.target;
    const email = form.email.value.trim();
    const password = form.password.value.trim();
    const plate = (form.plate_number ? String(form.plate_number.value || '').trim() : '');
    const operatorMode = getPortalMode() === 'operator';
    const captchaEl = form.querySelector('.g-recaptcha');
    let captchaResponse = '';
    if (captchaEl) {
        const ta = captchaEl.querySelector('textarea[name="g-recaptcha-response"], textarea.g-recaptcha-response');
        captchaResponse = ta ? String(ta.value || '') : '';
        if (!captchaResponse && window.grecaptcha && typeof window.grecaptcha.getResponse === 'function') {
            captchaResponse = String(window.grecaptcha.getResponse() || '');
        }
    }
    
    // Validate email
    if (!validateEmail(form.email)) {
        setLoginAlert('Please enter a valid email address.', 'warning');
        return;
    }
    
    // Validate password
    if (!validatePassword(form.password)) {
        setLoginAlert('Please enter your password.', 'warning');
        return;
    }
    
    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) showLoadingState(submitButton);
    setLoginAlert('', 'error');

    const deviceId = getOrCreateDeviceId();
    const payload = operatorMode
        ? { action: 'operator_login', email, password, plate_number: plate, device_id: deviceId }
        : { action: 'login', email, password, device_id: deviceId };

    if (captchaEl) {
        if (!captchaResponse) {
            const msg = 'Please complete the reCAPTCHA.';
            showNotification(msg, 'warning');
            setLoginAlert(msg, 'warning');
            if (submitButton) resetLoadingState(submitButton);
            return;
        }
        payload.recaptcha_token = captchaResponse || '';
    }

    makeAPICall('login.php', payload, 'POST')
        .then((res) => {
            if (!res || !res.ok) {
                const msg = (res && res.message) ? res.message : 'Login failed';
                setLoginAlert(msg, 'error');
                showNotification(msg, 'error');
                if (captchaEl && window.grecaptcha && typeof window.grecaptcha.reset === 'function') window.grecaptcha.reset();
                return;
            }
            const otpRequired = !!(res.data && res.data.otp_required);
            if (otpRequired) {
                const expiresIn = res.data && res.data.expires_in ? parseInt(res.data.expires_in, 10) : 180;
                const trustDays = res.data && res.data.otp_trust_days ? parseInt(res.data.otp_trust_days, 10) : 10;
                window.__otpLoginContext = { trust_days: trustDays };
                setLoginAlert('OTP sent. Please verify to continue.', 'success');
                showNotification('OTP sent. Please verify to continue.', 'info');
                openOtpModal(expiresIn, trustDays);
                return;
            }
            setLoginAlert('Login successful! Redirecting...', 'success');
            showNotification('Login successful! Redirecting...', 'success');
            const redirect = res.data && res.data.redirect ? res.data.redirect : (getRootUrl() + '/admin/index.php');
            window.location.href = redirect;
        })
        .catch(() => {
            setLoginAlert('Network error. Please try again.', 'error');
            showNotification('Network error. Please try again.', 'error');
            if (captchaEl && window.grecaptcha && typeof window.grecaptcha.reset === 'function') window.grecaptcha.reset();
        })
        .finally(() => {
            if (submitButton) resetLoadingState(submitButton);
        });
}

function handleSocialLogin(event) {
    const button = event.target.closest('button');
    const buttonText = button.textContent.trim();
    
    // Show loading state
    showLoadingState(button);
    
    // Simulate social login
    setTimeout(() => {
        console.log('Social login attempt:', buttonText);
        
        // Show appropriate message based on button
        if (buttonText.includes('Google')) {
            showNotification('Google login initiated...', 'info');
        } else if (buttonText.includes('Facebook')) {
            showNotification('Facebook login is currently unavailable. Please use email login.', 'warning');
        } else if (buttonText.includes('Apple')) {
            showNotification('Apple login initiated...', 'info');
        }
        
        // Reset button state
        setTimeout(() => {
            button.innerHTML = button.innerHTML.replace('<span class="loading"></span> Processing...', '');
            button.disabled = false;
        }, 2000);
    }, 1000);
}

function showRegisterForm() {
    const container = document.getElementById('registerFormContainer');
    const mainCard = document.querySelector('.glass-card');
    if (container && mainCard) {
        container.classList.remove('hidden');
        container.classList.add('flex');
        // Optionally dim the main card
        mainCard.classList.add('opacity-40');
    }
}

function hideRegisterForm() {
    const container = document.getElementById('registerFormContainer');
    const mainCard = document.querySelector('.glass-card');
    if (container && mainCard) {
        container.classList.add('hidden');
        container.classList.remove('flex');
        mainCard.classList.remove('opacity-40');
        try { if (window.grecaptcha && typeof window.grecaptcha.reset === 'function') window.grecaptcha.reset(); } catch (e) {}
    }
}

function handleRegisterSubmit(event) {
    event.preventDefault();
    const form = event.target;
    const data = serializeForm(form);
    if (!validateRegPassword(document.getElementById('regPassword'), true)) return;
    if (!validateConfirmPassword(true)) return;
    const captchaEl = form.querySelector('.g-recaptcha');
    let captchaResponse = '';
    if (captchaEl) {
        const ta = captchaEl.querySelector('textarea[name="g-recaptcha-response"], textarea.g-recaptcha-response');
        captchaResponse = ta ? String(ta.value || '') : '';
        if (!captchaResponse && window.grecaptcha && typeof window.grecaptcha.getResponse === 'function') {
            captchaResponse = String(window.grecaptcha.getResponse() || '');
        }
    }
    if (captchaEl && !captchaResponse) {
        showNotification('Please complete the reCAPTCHA.', 'warning');
        return;
    }
    if (!document.getElementById('agreeTerms').checked || !document.getElementById('agreePrivacy').checked) {
        showNotification('You must agree to the Terms and Privacy Policy.', 'warning');
        return;
    }
    if (data.regPassword !== data.confirmPassword) {
        showNotification('Passwords do not match.', 'error');
        return;
    }

    const payload = {
        firstName: data.firstName || '',
        lastName: data.lastName || '',
        middleName: data.middleName || '',
        suffix: data.suffix || '',
        birthdate: data.birthdate || '',
        email: data.regEmail || '',
        mobile: data.mobile || '',
        address: data.address || '',
        houseNumber: data.houseNumber || '',
        street: data.street || '',
        barangay: data.barangay || '',
        password: data.regPassword || '',
        confirmPassword: data.confirmPassword || '',
        recaptcha_token: captchaResponse || ''
    };

    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) showLoadingState(submitBtn);

    makeAPICall('register.php', payload, 'POST')
        .then((res) => {
            if (!res || !res.ok) {
                showNotification((res && res.message) ? res.message : 'Registration failed', 'error');
                return;
            }
            showNotification(res.message || 'Registration submitted!', 'success');
            hideRegisterForm();
            try { if (captchaEl && window.grecaptcha && typeof window.grecaptcha.reset === 'function') window.grecaptcha.reset(); } catch (e) {}
        })
        .catch(() => {})
        .finally(() => {
            if (submitBtn) {
                submitBtn.innerHTML = 'Register';
                submitBtn.disabled = false;
            }
        });
}

function validateRegPassword(inputEl, showMessage = false) {
    if (!inputEl) return false;
    const value = inputEl.value || '';
    const isValid = /[A-Z]/.test(value) && /[a-z]/.test(value) && /\d/.test(value) && /[^A-Za-z0-9]/.test(value) && value.length >= 10;
    // Clear previous message
    const parent = inputEl.parentNode;
    const existing = parent.querySelector('.pwd-error');
    if (existing) existing.remove();
    inputEl.classList.remove('border-red-500', 'ring-red-500');
    if (!isValid && showMessage) {
        inputEl.classList.add('border-red-500', 'ring-red-500');
        // No verbose error text per request; visual cue only
    }
    return isValid;
}

function validateConfirmPassword(showMessage = false) {
    return validateConfirmPasswordFor('regPassword', 'confirmPassword', showMessage, 'confirm-error');
}

function validateConfirmPasswordFor(pwdId, confirmId, showMessage, errorClass) {
    const pwd = document.getElementById(pwdId);
    const confirm = document.getElementById(confirmId);
    if (!pwd || !confirm) return false;
    const matches = (confirm.value || '') === (pwd.value || '');
    const wrapper = confirm.parentNode;
    const existing = wrapper.parentNode.querySelector('.' + errorClass);
    if (existing && existing.previousElementSibling !== wrapper) {
        existing.remove();
    }
    confirm.classList.remove('border-red-500', 'ring-red-500');
    if (!matches && showMessage) {
        confirm.classList.add('border-red-500', 'ring-red-500');
        let msg = wrapper.parentNode.querySelector('.' + errorClass);
        if (!msg) {
            msg = document.createElement('div');
            msg.className = errorClass + ' text-red-500 text-sm mt-1';
            if (wrapper.nextSibling) {
                wrapper.parentNode.insertBefore(msg, wrapper.nextSibling);
            } else {
                wrapper.parentNode.appendChild(msg);
            }
        }
        msg.textContent = 'Passwords do not match.';
    }
    return matches;
}

function updatePasswordChecklistFor(listId, value) {
    const checks = {
        length: value.length >= 10,
        upper: /[A-Z]/.test(value),
        lower: /[a-z]/.test(value),
        number: /\d/.test(value),
        special: /[^A-Za-z0-9]/.test(value)
    };
    const list = document.getElementById(listId);
    if (!list) return;
    Object.keys(checks).forEach(key => {
        const item = list.querySelector(`.req-item[data-check="${key}"]`);
        if (!item) return;
        if (checks[key]) {
            item.classList.add('met');
        } else {
            item.classList.remove('met');
        }
    });
}

function updatePasswordChecklist(value) {
    updatePasswordChecklistFor('pwdChecklist', value);
}

function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg transform transition-all duration-300 translate-x-full`;
    
    // Set notification style based on type
    switch (type) {
        case 'success':
            notification.classList.add('bg-green-500', 'text-white');
            break;
        case 'error':
            notification.classList.add('bg-red-500', 'text-white');
            break;
        case 'warning':
            notification.classList.add('bg-yellow-500', 'text-white');
            break;
        default:
            notification.classList.add('bg-blue-500', 'text-white');
    }
    
    notification.innerHTML = `
        <div class="flex items-center space-x-2">
            <i class="fas fa-${getNotificationIcon(type)}"></i>
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 100);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.classList.add('translate-x-full');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        }
    }, 5000);
}

function getNotificationIcon(type) {
    switch (type) {
        case 'success':
            return 'check-circle';
        case 'error':
            return 'exclamation-circle';
        case 'warning':
            return 'exclamation-triangle';
        default:
            return 'info-circle';
    }
}

function addSmoothScrolling() {
    // Add smooth scrolling to all anchor links
    const links = document.querySelectorAll('a[href^="#"]');
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

// Utility function for form data serialization
function serializeForm(form) {
    const formData = new FormData(form);
    const data = {};
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    return data;
}

// Utility function for API calls
async function makeAPICall(url, data, method = 'POST') {
    try {
        const response = await fetch(GSM_API_BASE + url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        const contentType = response.headers.get('content-type') || '';
        if (contentType.includes('application/json')) {
            const json = await response.json();
            if (!response.ok && (!json || typeof json.ok === 'undefined')) {
                return { ok: false, message: 'Request failed', data: null };
            }
            return json;
        }
        if (!response.ok) {
            return { ok: false, message: `Request failed (${response.status})`, data: null };
        }
        return { ok: true, message: 'OK', data: null };
    } catch (error) {
        console.error('API call failed:', error);
        showNotification('Network error. Please try again.', 'error');
        throw error;
    }
}

// Export functions for use in other scripts
window.GSM = {
    showNotification,
    validateEmail,
    makeAPICall
};

// OTP modal logic
let otpIntervalId = null;
let otpExpiresAt = null;

function openOtpModal(expiresInSeconds = 180, trustDays = 10) {
    const modal = document.getElementById('otpModal');
    const resend = document.getElementById('resendOtp');
    const error = document.getElementById('otpError');
    const submit = document.getElementById('submitOtp');
    const trustNote = document.getElementById('otpTrustNote');
    if (!modal) return;
    if (error) error.classList.add('hidden');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.classList.add('overflow-hidden');
    startOtpTimer(expiresInSeconds);
    if (resend) resend.disabled = true;
    if (submit) submit.disabled = false;
    if (trustNote) {
        trustNote.classList.remove('hidden');
        trustNote.textContent = `After verification, this device will be trusted for ${trustDays} days. If you use a different device, you will be asked again.`;
    }
    const inputs = Array.from(document.querySelectorAll('#otpInputs .otp-input'));
    inputs.forEach(i => i.value = '');
    setupOtpInputs(inputs);
    if (inputs[0]) inputs[0].focus();
}

function closeOtpModal() {
    const modal = document.getElementById('otpModal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.classList.remove('overflow-hidden');
    stopOtpTimer();
}

function startOtpTimer(seconds) {
    otpExpiresAt = Date.now() + seconds * 1000;
    updateOtpTimer();
    if (otpIntervalId) clearInterval(otpIntervalId);
    otpIntervalId = setInterval(updateOtpTimer, 1000);
}

function stopOtpTimer() {
    if (otpIntervalId) clearInterval(otpIntervalId);
    otpIntervalId = null;
}

function updateOtpTimer() {
    const timerEl = document.getElementById('otpTimer');
    const resend = document.getElementById('resendOtp');
    const submit = document.getElementById('submitOtp');
    const remaining = Math.max(0, Math.floor((otpExpiresAt - Date.now()) / 1000));
    const mm = String(Math.floor(remaining / 60)).padStart(2, '0');
    const ss = String(remaining % 60).padStart(2, '0');
    if (timerEl) timerEl.textContent = `${mm}:${ss}`;
    if (remaining === 0) {
        if (resend) resend.disabled = false;
        if (submit) submit.disabled = true;
        stopOtpTimer();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const cancelOtp = document.getElementById('cancelOtp');
    const otpForm = document.getElementById('otpForm');
    const resend = document.getElementById('resendOtp');
    const modal = document.getElementById('otpModal');
    if (cancelOtp) cancelOtp.addEventListener('click', closeOtpModal);
    if (resend) resend.addEventListener('click', () => {
        resend.disabled = true;
        makeAPICall('login.php', { action: 'login_otp_resend' }, 'POST')
            .then((res) => {
                if (!res || !res.ok) {
                    showNotification((res && res.message) ? res.message : 'Failed to resend OTP.', 'error');
                    resend.disabled = false;
                    return;
                }
                showNotification(res.message || 'A new OTP has been sent to your email.', 'info');
                const expiresIn = res.data && res.data.expires_in ? parseInt(res.data.expires_in, 10) : 180;
                startOtpTimer(expiresIn);
            })
            .catch(() => { resend.disabled = false; });
    });
    if (otpForm) otpForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const code = collectOtpCode();
        const error = document.getElementById('otpError');
        if (!code || code.length !== 6) {
            if (error) {
                error.textContent = 'Please enter the 6-digit OTP.';
                error.classList.remove('hidden');
            }
            return;
        }
        if (document.getElementById('submitOtp').disabled) {
            if (error) {
                error.textContent = 'OTP expired. Please resend a new OTP.';
                error.classList.remove('hidden');
            }
            return;
        }
        if (error) error.classList.add('hidden');
        const submitBtn = document.getElementById('submitOtp');
        if (submitBtn) submitBtn.disabled = true;
        makeAPICall('login.php', { action: 'login_otp_verify', code }, 'POST')
            .then((res) => {
                if (!res || !res.ok) {
                    if (error) {
                        error.textContent = (res && res.message) ? res.message : 'Invalid or expired OTP.';
                        error.classList.remove('hidden');
                    }
                    if (submitBtn) submitBtn.disabled = false;
                    return;
                }
                showNotification(res.message || 'OTP verified! Redirecting...', 'success');
                const redirect = res.data && res.data.redirect ? res.data.redirect : (getRootUrl() + '/admin/index.php');
                closeOtpModal();
                window.location.href = redirect;
            })
            .catch(() => { if (submitBtn) submitBtn.disabled = false; });
    });
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeOtpModal();
        });
    }
});

function setupOtpInputs(inputs) {
    inputs.forEach((input, idx) => {
        input.addEventListener('input', (e) => {
            const value = e.target.value.replace(/\D/g, '').slice(0,1);
            e.target.value = value;
            if (value && idx < inputs.length - 1) inputs[idx + 1].focus();
        });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !e.target.value && idx > 0) {
                inputs[idx - 1].focus();
            }
        });
        input.addEventListener('paste', (e) => {
            const text = (e.clipboardData || window.clipboardData).getData('text');
            if (!text) return;
            const digits = text.replace(/\D/g, '').slice(0, inputs.length).split('');
            inputs.forEach((i, iIdx) => { i.value = digits[iIdx] || ''; });
            e.preventDefault();
            const nextIndex = Math.min(digits.length, inputs.length - 1);
            inputs[nextIndex].focus();
        });
    });
}

function collectOtpCode() {
    const inputs = Array.from(document.querySelectorAll('#otpInputs .otp-input'));
    return inputs.map(i => i.value).join('');
}
