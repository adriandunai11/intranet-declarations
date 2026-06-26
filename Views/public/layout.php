<!doctype html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <title><?= esc($title ?? 'Miell nyilatkozatok') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#50b848">
    <link rel="stylesheet" href="<?= base_url('assets/declarations/css/public.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/declarations/css/public_redesign.css') ?>">
    <?php $localPublicCss = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public.css'; ?>
    <?php if (is_file($localPublicCss)): ?>
        <style>
            <?= file_get_contents($localPublicCss) ?>
        </style>
    <?php endif; ?>
    <?php $localRedesignCss = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public_redesign.css'; ?>
    <?php if (is_file($localRedesignCss)): ?>
        <style>
            <?= file_get_contents($localRedesignCss) ?>
        </style>
    <?php endif; ?>
</head>
<body>
<div class="page">
    <header class="topbar">
        <div class="topbar-inner">
            <a href="#" class="brand" aria-label="Miell Group nyilatkozatok">
                <img src="/assets/declarations/img/logo.svg" alt="Miell Group" class="brand-logo-img">
                <span class="brand-copy">
                    <span class="brand-name">Miell Group nyilatkozatok</span>
                    <span class="brand-subtitle">Online kitöltés és beküldés</span>
                </span>
            </a>

            <div class="security-pill">
                <span class="security-mark" aria-hidden="true"></span>
                <span>Meghívóval védett felület</span>
            </div>
        </div>
    </header>

    <main class="main">
        <div class="container">
            <?php if (session()->getFlashdata('sSuccess')): ?>
                <div class="notice notice-success page-notice">
                    <?= esc(session()->getFlashdata('sSuccess')) ?>
                </div>
            <?php endif; ?>

            <?php if (session()->getFlashdata('sError')): ?>
                <div class="notice notice-danger page-notice">
                    <?= esc(session()->getFlashdata('sError')) ?>
                </div>
            <?php endif; ?>

            <?= $this->renderSection('content') ?>
        </div>
    </main>

    <footer class="footer">
        © <?= date('Y') ?> Miell Group · A hozzáférés a meghívó link lejáratáig él.
    </footer>
</div>
<script>
    (function () {
        function digits(value) {
            return String(value || '').replace(/\D+/g, '');
        }

        function escapeHtml(value) {
            return String(value).replace(/[&<>"']/g, function (char) {
                return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char];
            });
        }

        function labelFor(input) {
            if (input.dataset.label) {
                return input.dataset.label;
            }

            var label = input.id ? document.querySelector('label[for="' + input.id + '"]') : null;

            return label ? label.textContent.trim() : 'A mező';
        }

        function isEmpty(input) {
            if (input.type === 'checkbox' || input.type === 'radio') {
                return !input.checked;
            }

            return String(input.value || '').trim() === '';
        }

        function isValidTaxNumber(value) {
            value = digits(value);

            if (!/^8\d{9}$/.test(value)) {
                return false;
            }

            var sum = 0;

            for (var i = 0; i < 9; i++) {
                sum += Number(value.charAt(i)) * (i + 1);
            }

            return sum % 11 === Number(value.charAt(9));
        }

        function isValidTajNumber(value) {
            value = digits(value);

            if (!/^\d{9}$/.test(value)) {
                return false;
            }

            var sum = 0;

            for (var i = 0; i < 8; i++) {
                sum += Number(value.charAt(i)) * (i % 2 === 0 ? 3 : 7);
            }

            return sum % 10 === Number(value.charAt(8));
        }

        function isValidHungarianBankAccountNumber(value) {
            value = digits(value);

            if (!/^\d{16}(\d{8})?$/.test(value)) {
                return false;
            }

            var weights = [9, 7, 3, 1, 9, 7, 3, 1];

            for (var offset = 0; offset < value.length; offset += 8) {
                var block = value.substring(offset, offset + 8);
                var sum = 0;

                for (var i = 0; i < 8; i++) {
                    sum += Number(block.charAt(i)) * weights[i];
                }

                if (sum % 10 !== 0) {
                    return false;
                }
            }

            return true;
        }

        function formatInput(input) {
            var format = input.dataset.format || '';
            var value = digits(input.value);
            var maxDigits = Number(input.dataset.maxDigits || 0);

            if (format === 'digits') {
                input.value = maxDigits > 0 ? value.substring(0, maxDigits) : value;
            }

            if (format === 'taj') {
                input.value = value.substring(0, 9).replace(/(\d{3})(?=\d)/g, '$1 ').trim();
            }

            if (format === 'bank_account') {
                input.value = value.substring(0, 24).replace(/(\d{8})(?=\d)/g, '$1-').trim();
            }
        }

        function messageFor(input) {
            var rules = String(input.dataset.validate || '').split('|').filter(Boolean);
            var label = labelFor(input);
            var value = String(input.value || '').trim();
            var cleanDigits = digits(value);

            for (var i = 0; i < rules.length; i++) {
                var rule = rules[i];

                if (rule === 'required' && isEmpty(input)) {
                    return input.dataset.errorRequired || label + ' megadása kötelező.';
                }

                if (rule.indexOf('min:') === 0 && !isEmpty(input)) {
                    var min = Number(rule.split(':')[1] || 0);

                    if (value.length < min) {
                        return label + ' legalább ' + min + ' karakter legyen.';
                    }
                }

                if (rule === 'date' && !isEmpty(input) && Number.isNaN(Date.parse(value))) {
                    return label + ' formátuma hibás.';
                }

                if (rule === 'not_future' && !isEmpty(input)) {
                    var today = new Date();
                    today.setHours(0, 0, 0, 0);

                    if (new Date(value) > today) {
                        return label + ' nem lehet jövőbeli dátum.';
                    }
                }

                if (rule === 'phone' && !isEmpty(input) && cleanDigits.length < 6) {
                    return 'A telefonszám túl rövid.';
                }

                if (rule === 'tax_number' && !isEmpty(input)) {
                    if (cleanDigits.length !== 10) {
                        return 'Az adóazonosító jelnek pontosan 10 számjegyből kell állnia.';
                    }

                    if (!isValidTaxNumber(cleanDigits)) {
                        return 'Az adóazonosító jel ellenőrző száma hibás.';
                    }
                }

                if (rule === 'taj_number' && !isEmpty(input)) {
                    if (cleanDigits.length !== 9) {
                        return 'A TAJ számnak pontosan 9 számjegyből kell állnia.';
                    }

                    if (!isValidTajNumber(cleanDigits)) {
                        return 'A TAJ szám ellenőrző száma hibás.';
                    }
                }

                if (rule === 'bank_account' && !isEmpty(input)) {
                    if (cleanDigits.length !== 16 && cleanDigits.length !== 24) {
                        return 'A bankszámlaszámnak 16 vagy 24 számjegyből kell állnia.';
                    }

                    if (!isValidHungarianBankAccountNumber(cleanDigits)) {
                        return 'A bankszámlaszám ellenőrző száma hibás.';
                    }
                }
            }

            return '';
        }

        function fieldShell(input) {
            return input.closest('.form-group') || input.closest('.checkbox-group') || input.parentNode;
        }

        function setFieldState(input, message, show) {
            var shell = fieldShell(input);
            var error = shell.querySelector('.field-error');

            if (!error) {
                error = document.createElement('div');
                error.className = 'field-error';
                shell.appendChild(error);
            }

            input.classList.toggle('is-invalid', show && message !== '');
            input.classList.toggle('is-valid', show && message === '' && !isEmpty(input));
            error.textContent = show ? message : '';
            error.hidden = !show || message === '';
        }

        function validateInput(input, show) {
            var message = messageFor(input);
            setFieldState(input, message, show);

            return {
                input: input,
                message: message,
                visible: show && message !== ''
            };
        }

        function formFields(form) {
            return Array.prototype.slice.call(form.querySelectorAll('[data-validate]'));
        }

        function updateProgress(form, results) {
            var progress = form.querySelector('[data-form-progress]');

            if (!progress) {
                return;
            }

            var total = results.length;
            var valid = results.filter(function (result) {
                return result.message === '' && !isEmpty(result.input);
            }).length;
            var percent = total > 0 ? Math.round((valid / total) * 100) : 0;
            var fill = progress.querySelector('[data-progress-fill]');
            var label = progress.querySelector('[data-progress-label]');

            if (fill) {
                fill.style.width = percent + '%';
            }

            if (label) {
                label.textContent = valid + '/' + total + ' mező rendben';
            }
        }

        function updateSummary(form, results, showAll) {
            var summary = form.querySelector('.js-client-errors');

            if (!summary && form.dataset.errorSummary) {
                summary = document.querySelector(form.dataset.errorSummary);
            }

            if (!summary) {
                return;
            }

            var visibleErrors = results.filter(function (result) {
                return showAll ? result.message !== '' : result.visible;
            });

            if (visibleErrors.length === 0) {
                summary.hidden = true;
                summary.innerHTML = '';
                return;
            }

            summary.hidden = false;
            summary.innerHTML = '<strong>Kérjük, javítsa az alábbiakat:</strong><ul>'
                + visibleErrors.map(function (result) {
                    return '<li>' + escapeHtml(result.message) + '</li>';
                }).join('')
                + '</ul>';
        }

        function validateForm(form, showAll) {
            var results = formFields(form).map(function (input) {
                return validateInput(input, showAll || input.dataset.touched === '1');
            });

            updateProgress(form, results);
            updateSummary(form, results, showAll);

            return results.every(function (result) {
                return result.message === '';
            });
        }

        function initForm(form) {
            formFields(form).forEach(function (input) {
                ['input', 'change', 'blur'].forEach(function (eventName) {
                    input.addEventListener(eventName, function () {
                        input.dataset.touched = '1';
                        formatInput(input);
                        validateForm(form, false);
                    });
                });
            });

            form.addEventListener('submit', function (event) {
                formFields(form).forEach(function (input) {
                    input.dataset.touched = '1';
                });

                if (!validateForm(form, true)) {
                    event.preventDefault();

                    var summary = form.querySelector('.js-client-errors')
                        || (form.dataset.errorSummary ? document.querySelector(form.dataset.errorSummary) : null);

                    if (summary) {
                        summary.scrollIntoView({behavior: 'smooth', block: 'center'});
                    }
                }
            });

            validateForm(form, false);
        }

        window.DeclarationLiveValidation = {
            init: initForm,
            validate: validateForm
        };

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('form[data-live-validation]').forEach(initForm);
        });
    }());
</script>
<?= $this->renderSection('scripts') ?>
</body>
</html>