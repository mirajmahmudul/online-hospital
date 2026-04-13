(function () {
    'use strict';

    const API_URL  = 'api/register.php';
    const ROLE     = 'doctor';
    const REDIRECT = 'doctor-dashboard.html';

    // DOM
    const form            = document.getElementById('register-form');
    const nameInput       = document.getElementById('full-name');
    const emailInput      = document.getElementById('email');
    const licenseInput    = document.getElementById('license');
    const specialtySelect = document.getElementById('specialty');
    const passInput       = document.getElementById('password');
    const submitBtn       = document.getElementById('submit-btn');
    const errorBox        = document.getElementById('form-error');
    const successBox      = document.getElementById('form-success');
    const passToggle      = document.getElementById('password-toggle');
    const editorialPhoto  = document.getElementById('editorial-photo');
    const strBars = [
        document.getElementById('str-bar-1'),
        document.getElementById('str-bar-2'),
        document.getElementById('str-bar-3'),
        document.getElementById('str-bar-4'),
    ];
    const strLabel = document.getElementById('strength-label');


    // ── Image Fade-In ─────────────────────────
    editorialPhoto.addEventListener('load', function () {
        editorialPhoto.classList.add('register-image__photo--loaded');
    });
    if (editorialPhoto.complete && editorialPhoto.naturalWidth > 0) {
        editorialPhoto.classList.add('register-image__photo--loaded');
    }


    // ── Password Toggle ───────────────────────
    passToggle.addEventListener('click', function () {
        var isPass = passInput.type === 'password';
        passInput.type = isPass ? 'text' : 'password';
        passToggle.textContent = isPass ? 'Hide' : 'Show';
    });


    // ── Password Strength ─────────────────────
    passInput.addEventListener('input', function () {
        var val = passInput.value;
        var score = 0;
        if (val.length >= 8)          score++;
        if (/[A-Z]/.test(val))        score++;
        if (/[0-9]/.test(val))        score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        var levels  = ['', 'Weak', 'Fair', 'Good', 'Strong'];
        var classes = ['', 'weak', 'fair', 'strong', 'strong'];
        strLabel.textContent = val.length > 0 ? (levels[score] || '') : '';

        strBars.forEach(function (bar, i) {
            bar.className = 'password-strength__bar';
            if (i < score) {
                bar.classList.add('password-strength__bar--active-' + classes[score]);
            }
        });
    });


    // ── Clear Errors on Input ─────────────────
    [nameInput, emailInput, licenseInput, passInput].forEach(function (input) {
        input.addEventListener('input', function () {
            hideAlert(errorBox);
            input.classList.remove('form-input--error');
        });
    });

    specialtySelect.addEventListener('change', function () {
        hideAlert(errorBox);
        specialtySelect.classList.remove('form-select--error');
    });


    // ── Form Submission ───────────────────────
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        localStorage.setItem('mastermind_auth_token', 'secure_session_active');
        window.location.replace(REDIRECT);
    });


    // ── Helpers ───────────────────────────────
    function showAlert(el, msg) {
        el.textContent = msg;
        el.style.display = 'block';
        requestAnimationFrame(function () { el.classList.add('form-alert--visible'); });
    }

    function hideAlert(el) {
        el.classList.remove('form-alert--visible');
        setTimeout(function () {
            if (!el.classList.contains('form-alert--visible')) {
                el.style.display = 'none';
            }
        }, 350);
    }

    function setLoading(on) {
        submitBtn.disabled = on;
        submitBtn.classList.toggle('form-submit--loading', on);
    }

})();
