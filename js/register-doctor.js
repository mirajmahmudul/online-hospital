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
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        hideAlert(errorBox);
        hideAlert(successBox);

        var name     = nameInput.value.trim();
        var email    = emailInput.value.trim();
        var license  = licenseInput.value.trim();
        var specialty = specialtySelect.value;
        var password = passInput.value;

        // Validation
        if (!name) {
            showAlert(errorBox, 'Please enter your full name.');
            nameInput.classList.add('form-input--error');
            nameInput.focus();
            return;
        }
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showAlert(errorBox, 'Please enter a valid professional email.');
            emailInput.classList.add('form-input--error');
            emailInput.focus();
            return;
        }
        if (!license) {
            showAlert(errorBox, 'Please enter your medical license number.');
            licenseInput.classList.add('form-input--error');
            licenseInput.focus();
            return;
        }
        if (!specialty) {
            showAlert(errorBox, 'Please select your specialty.');
            specialtySelect.classList.add('form-select--error');
            specialtySelect.focus();
            return;
        }
        if (password.length < 8) {
            showAlert(errorBox, 'Password must be at least 8 characters.');
            passInput.classList.add('form-input--error');
            passInput.focus();
            return;
        }

        setLoading(true);

        try {
            var res = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name: name,
                    email: email,
                    password: password,
                    role: ROLE,
                    license_number: license,
                    specialty: specialty,
                }),
            });

            var data = await res.json();

            if (!res.ok) {
                showAlert(errorBox, data.message || 'Application failed. Please try again.');
                setLoading(false);
                return;
            }

            showAlert(successBox, 'Application submitted! Redirecting to your dashboard…');
            setTimeout(function () {
                window.location.href = REDIRECT;
            }, 1400);

        } catch (err) {
            console.error('Registration error:', err);
            showAlert(errorBox, 'Unable to connect to the server. Please try again later.');
            setLoading(false);
        }
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
