<!doctype html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <title><?= esc($title ?? 'Miell nyilatkozatok') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#50b848">
    <link rel="stylesheet" href="<?= base_url('assets/declarations/css/public.css') ?>">
    <?php $localPublicCss = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'declarations' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'public.css'; ?>
    <?php if (is_file($localPublicCss)): ?>
        <style>
            <?= file_get_contents($localPublicCss) ?>
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

<dialog class="confirm-dialog" id="declaration-confirm-dialog" aria-labelledby="declaration-confirm-title">
    <div class="confirm-dialog-card">
        <div class="confirm-dialog-icon" aria-hidden="true">!</div>
        <h2 id="declaration-confirm-title">Biztosan eltávolítja?</h2>
        <p id="declaration-confirm-message">
            A kiválasztott nyilatkozat törlődik a csomagból. Ha már volt hozzá mentett adat, az is törlésre kerül.
        </p>
        <div class="confirm-dialog-actions">
            <button type="button" class="btn btn-secondary" data-confirm-cancel>Mégsem</button>
            <button type="button" class="btn btn-danger" data-confirm-submit>Igen, eltávolítom</button>
        </div>
    </div>
</dialog>

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

        var hungarianBankPrefixes = {
            '101': 'Magyar Nemzeti Bank',
            '104': 'K&H Bank',
            '107': 'CIB Bank',
            '109': 'UniCredit Bank',
            '116': 'Erste Bank',
            '117': 'OTP Bank',
            '120': 'Raiffeisen Bank',
            '121': 'Gránit Bank',
            '162': 'MagNet Bank'
        };

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

        function isValidCvdBlock(value) {
            value = digits(value);

            if (!/^\d{8}$/.test(value)) {
                return false;
            }

            var weights = [9, 7, 3, 1, 9, 7, 3, 1];
            var sum = 0;

            for (var i = 0; i < 8; i++) {
                sum += Number(value.charAt(i)) * weights[i];
            }

            return sum % 10 === 0;
        }

        function isValidHungarianCompanyTaxNumber(value) {
            value = digits(value);

            if (value.length !== 8 && value.length !== 11) {
                return false;
            }

            if (!isValidCvdBlock(value.substring(0, 8))) {
                return false;
            }

            if (value.length === 8) {
                return true;
            }

            var vatCode = Number(value.charAt(8));

            return vatCode >= 1 && vatCode <= 5;
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

            for (var offset = 0; offset < value.length; offset += 8) {
                var block = value.substring(offset, offset + 8);

                if (!isValidCvdBlock(block)) {
                    return false;
                }
            }

            return true;
        }

        function bankNameForAccountNumber(value) {
            value = digits(value);

            if (value.length < 3) {
                return '';
            }

            return hungarianBankPrefixes[value.substring(0, 3)] || '';
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

            if (format === 'company_tax_number') {
                value = value.substring(0, 11);

                if (value.length > 9) {
                    input.value = value.substring(0, 8) + '-' + value.substring(8, 9) + '-' + value.substring(9);
                } else if (value.length > 8) {
                    input.value = value.substring(0, 8) + '-' + value.substring(8);
                } else {
                    input.value = value;
                }
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

                if (rule === 'company_tax_number' && !isEmpty(input)) {
                    if (cleanDigits.length !== 8 && cleanDigits.length !== 11) {
                        return 'Az adószámnak 8 számjegyű törzsszámnak vagy teljes, 11 számjegyű adószámnak kell lennie.';
                    }

                    if (!isValidHungarianCompanyTaxNumber(cleanDigits)) {
                        return 'Az adószám ellenőrző száma hibás.';
                    }
                }

                if (rule === 'tax_number_or_fetus' && !isEmpty(input)) {
                    if (value.toLowerCase() === 'magzat') {
                        continue;
                    }

                    if (cleanDigits.length !== 10) {
                        return 'Adjon meg 10 számjegyű adóazonosító jelet, vagy írja be: magzat.';
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

                if (rule === 'bank_name_required_when_unknown') {
                    var source = input.dataset.bankAccountSource ? document.querySelector(input.dataset.bankAccountSource) : null;
                    var sourceDigits = source ? digits(source.value) : '';
                    var detectedBankName = source ? bankNameForAccountNumber(source.value) : '';

                    if (sourceDigits.length >= 3 && detectedBankName === '' && isEmpty(input)) {
                        return 'A bank nevét add meg, ha nem ismerjük fel automatikusan a bankszámlaszám elejéből.';
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

        function setBankHint(input, text) {
            var hint = input.dataset.bankHint ? document.querySelector(input.dataset.bankHint) : null;

            if (hint) {
                hint.textContent = text;
            }
        }

        function bindBankAccountMetadata(input) {
            if (input.dataset.bankMetadataBound === '1') {
                return;
            }

            input.dataset.bankMetadataBound = '1';

            var target = input.dataset.bankNameTarget ? document.querySelector(input.dataset.bankNameTarget) : null;

            if (target && target.dataset.bankNameTargetBound !== '1') {
                target.dataset.bankNameTargetBound = '1';
                target.addEventListener('input', function () {
                    target.dataset.bankAutofilled = '0';
                });
            }
        }

        function updateBankAccountMetadata(input) {
            if (!input.dataset.bankNameTarget) {
                return;
            }

            var target = document.querySelector(input.dataset.bankNameTarget);
            var cleanDigits = digits(input.value);
            var bankName = bankNameForAccountNumber(input.value);

            if (cleanDigits.length < 3) {
                setBankHint(input, 'Az első 3 számjegyből megpróbáljuk felismerni a bankot.');

                if (target && target.dataset.bankAutofilled === '1') {
                    target.value = '';
                    target.dataset.bankAutofilled = '0';
                }

                return;
            }

            if (bankName !== '') {
                setBankHint(input, 'Felismert bank: ' + bankName + '.');

                if (target && (target.value.trim() === '' || target.dataset.bankAutofilled === '1')) {
                    target.value = bankName;
                    target.dataset.bankAutofilled = '1';
                }

                return;
            }

            setBankHint(input, 'A bankazonosító nincs a helyi listában; ettől a számlaszám még lehet érvényes.');

            if (target && target.dataset.bankAutofilled === '1') {
                target.value = '';
                target.dataset.bankAutofilled = '0';
            }
        }

        function initForm(form) {
            formFields(form).forEach(function (input) {
                if (input.dataset.validationBound === '1') {
                    return;
                }

                input.dataset.validationBound = '1';

                bindBankAccountMetadata(input);

                ['input', 'change', 'blur'].forEach(function (eventName) {
                    input.addEventListener(eventName, function () {
                        input.dataset.touched = '1';
                        formatInput(input);
                        updateBankAccountMetadata(input);
                        validateForm(form, false);
                    });
                });

                formatInput(input);
                updateBankAccountMetadata(input);
            });

            if (form.dataset.validationFormBound !== '1') {
                form.dataset.validationFormBound = '1';

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
            }

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
<script>
    (function () {
        var pendingForm = null;

        function isRemoveForm(form) {
            return form && form.action && /\/remove(?:\?|$)/.test(form.action);
        }

        function openDialog(form) {
            var dialog = document.getElementById('declaration-confirm-dialog');
            var cancelButton = dialog ? dialog.querySelector('[data-confirm-cancel]') : null;

            pendingForm = form;

            if (!dialog || typeof dialog.showModal !== 'function') {
                form.dataset.confirmed = '1';
                form.requestSubmit ? form.requestSubmit() : form.submit();
                return;
            }

            dialog.showModal();

            if (cancelButton) {
                cancelButton.focus();
            }
        }

        document.addEventListener('submit', function (event) {
            var form = event.target;

            if (!isRemoveForm(form) || form.dataset.confirmed === '1') {
                return;
            }

            event.preventDefault();
            openDialog(form);
        }, true);

        document.addEventListener('DOMContentLoaded', function () {
            var dialog = document.getElementById('declaration-confirm-dialog');

            if (!dialog) {
                return;
            }

            var cancelButton = dialog.querySelector('[data-confirm-cancel]');
            var submitButton = dialog.querySelector('[data-confirm-submit]');

            if (cancelButton) {
                cancelButton.addEventListener('click', function () {
                    pendingForm = null;
                    dialog.close();
                });
            }

            if (submitButton) {
                submitButton.addEventListener('click', function () {
                    if (!pendingForm) {
                        dialog.close();
                        return;
                    }

                    pendingForm.dataset.confirmed = '1';
                    dialog.close();
                    pendingForm.requestSubmit ? pendingForm.requestSubmit() : pendingForm.submit();
                });
            }

            dialog.addEventListener('cancel', function () {
                pendingForm = null;
            });
        });
    }());
</script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
