<?= $this->extend('App\\Modules\\Declarations\\Views\\public\\layout') ?>

<?= $this->section('content') ?>

<?php
$data = [];
$submissionDataNormalizer = new \App\Modules\Declarations\Services\DeclarationSubmissionDataNormalizer();

if ($submission && !empty($submission->data_json)) {
    $data = $submissionDataNormalizer->normalize($submission->data_json);
}

$schema = is_array($statementFormSchema ?? null) ? $statementFormSchema : [];
$fieldData = is_array($data['statement_fields'] ?? null) ? $data['statement_fields'] : [];
$repeaterData = is_array($data['repeaters'] ?? null) ? $data['repeaters'] : [];
$oldFields = old('statement_fields');
$oldFields = is_array($oldFields) ? $oldFields : [];
$oldRepeaters = old('repeaters');
$oldRepeaters = is_array($oldRepeaters) ? $oldRepeaters : [];
$validationErrors = session()->getFlashdata('validationErrors') ?? [];

$fieldValue = static function (string $key) use ($oldFields, $fieldData) {
    return $oldFields[$key] ?? ($fieldData[$key] ?? '');
};

$safeId = static function (string $value): string {
    return preg_replace('/[^a-zA-Z0-9_\-]+/', '_', $value) ?: 'field';
};

$rulesForField = static function (array $field): string {
    $rules = [];

    if (!empty($field['required'])) {
        $rules[] = 'required';
    }

    if (($field['type'] ?? '') === 'date') {
        $rules[] = 'date';
    }

    if (($field['validation'] ?? '') === 'tax_number') {
        $rules[] = 'tax_number';
    }

    if (($field['validation'] ?? '') === 'taj_number') {
        $rules[] = 'taj_number';
    }

    return implode('|', $rules);
};

$conditionAttributes = static function (array $field, string $conditionKey): string {
    $condition = $field[$conditionKey] ?? null;

    if (!is_array($condition)) {
        return '';
    }

    $fieldKey = trim((string) ($condition['field'] ?? ''));

    if ($fieldKey === '') {
        return '';
    }

    $prefix = $conditionKey === 'required_when' ? 'required-when' : 'visible-when';
    $attributes = ' data-' . $prefix . '-field="' . esc($fieldKey) . '"';

    if (isset($condition['values']) && is_array($condition['values'])) {
        $attributes .= ' data-' . $prefix . '-values="' . esc(implode('|', array_map('strval', $condition['values']))) . '"';
    } else {
        $attributes .= ' data-' . $prefix . '-value="' . esc((string) ($condition['value'] ?? '')) . '"';
    }

    return $attributes;
};

$shellAttributes = static function (array $field) use ($conditionAttributes): string {
    $attributes = $conditionAttributes($field, 'visible_when');

    return $attributes !== '' ? ' data-conditional-field' . $attributes : '';
};

$inputConditionAttributes = static function (array $field) use ($conditionAttributes): string {
    return $conditionAttributes($field, 'visible_when')
        . $conditionAttributes($field, 'required_when');
};

$sectionAttributes = static function (array $section) use ($conditionAttributes): string {
    $attributes = $conditionAttributes($section, 'visible_when');

    return $attributes !== '' ? ' data-conditional-section' . $attributes : '';
};

$repeaterRequiredAttributes = static function (array $repeater) use ($conditionAttributes): string {
    $attributes = $conditionAttributes($repeater, 'required_when');

    if ($attributes === '') {
        return '';
    }

    $condition = is_array($repeater['required_when'] ?? null) ? $repeater['required_when'] : [];
    $minimum = max(1, (int) ($condition['min'] ?? 1));

    return ' data-repeater-required' . $attributes . ' data-required-min="' . esc((string) $minimum) . '"';
};

$renderField = static function (array $field, string $name, string $id, $value) use ($rulesForField, $shellAttributes, $inputConditionAttributes): string {
    $key = (string) ($field['key'] ?? '');
    $label = (string) ($field['label'] ?? $key);
    $type = (string) ($field['type'] ?? 'text');
    $required = !empty($field['required']);
    $rules = $rulesForField($field);
    $shellAttributesHtml = $shellAttributes($field);
    $inputConditionAttributesHtml = $inputConditionAttributes($field);
    $validationAttributes = $rules !== ''
        ? ' data-validate="' . esc($rules) . '" data-label="' . esc($label) . '"'
        : ' data-label="' . esc($label) . '"';
    $validationAttributes .= ' data-base-validate="' . esc($rules) . '"';
    $validationAttributes .= $required ? ' data-static-required="1"' : ' data-static-required="0"';
    $validationAttributes .= $inputConditionAttributesHtml;
    $requiredAttribute = $required ? ' required' : '';
    $help = trim((string) ($field['help'] ?? ''));

    if ($type === 'checkbox') {
        return '<div class="form-group checkbox-group"' . $shellAttributesHtml . '>'
            . '<label>'
            . '<input type="checkbox" name="' . esc($name) . '" value="1"' . $validationAttributes . $requiredAttribute . ((int) $value === 1 ? ' checked' : '') . '>'
            . '<span>' . esc($label) . '</span>'
            . '</label>'
            . '</div>';
    }

    $html = '<div class="form-group"' . $shellAttributesHtml . '>';
    $html .= '<label for="' . esc($id) . '">' . esc($label) . '</label>';

    if ($type === 'select') {
        $html .= '<select id="' . esc($id) . '" name="' . esc($name) . '"' . $validationAttributes . $requiredAttribute . '>';
        $html .= '<option value="">Válasszon...</option>';

        foreach (($field['options'] ?? []) as $optionValue => $optionLabel) {
            $selected = (string) $value === (string) $optionValue ? ' selected' : '';
            $html .= '<option value="' . esc((string) $optionValue) . '"' . $selected . '>' . esc((string) $optionLabel) . '</option>';
        }

        $html .= '</select>';
    } elseif ($type === 'textarea') {
        $html .= '<textarea id="' . esc($id) . '" name="' . esc($name) . '"' . $validationAttributes . $requiredAttribute . '>'
            . esc((string) $value)
            . '</textarea>';
    } else {
        $inputType = $type === 'date' ? 'date' : 'text';
        $formatAttributes = '';

        if (($field['validation'] ?? '') === 'tax_number') {
            $formatAttributes = ' inputmode="numeric" maxlength="10" data-format="digits" data-max-digits="10" placeholder="10 számjegy"';
        } elseif (($field['validation'] ?? '') === 'taj_number') {
            $formatAttributes = ' inputmode="numeric" maxlength="11" data-format="taj" placeholder="123 456 789"';
        } elseif ($type === 'number') {
            $formatAttributes = ' inputmode="numeric" data-format="digits"';
        }

        $html .= '<input type="' . esc($inputType) . '" id="' . esc($id) . '" name="' . esc($name) . '" value="' . esc((string) $value) . '"'
            . $formatAttributes
            . $validationAttributes
            . $requiredAttribute
            . ' autocomplete="off">';
    }

    if ($help !== '') {
        $html .= '<div class="form-help">' . esc($help) . '</div>';
    }

    $html .= '</div>';

    return $html;
};

$renderRepeaterRow = static function (array $repeater, $index, array $row) use ($renderField, $safeId): string {
    $repeaterKey = (string) ($repeater['key'] ?? 'rows');
    $rowIndex = (string) $index;
    $rowNumber = is_numeric($index) ? ((int) $index + 1) : '__ROW__';
    $html = '<div class="tax-repeater-row" data-repeater-row data-row-index="' . esc($rowIndex) . '">';
    $html .= '<div class="tax-repeater-row-head">';
    $html .= '<strong><span data-row-number>' . esc((string) $rowNumber) . '</span>. sor</strong>';
    $html .= '<button type="button" class="btn btn-secondary btn-sm" data-remove-repeater-row>Eltávolítás</button>';
    $html .= '</div>';
    $html .= '<div class="tax-repeater-grid structured-repeater-grid">';

    foreach (($repeater['columns'] ?? []) as $column) {
        if (!is_array($column)) {
            continue;
        }

        $columnKey = (string) ($column['key'] ?? '');
        $name = 'repeaters[' . $repeaterKey . '][' . $rowIndex . '][' . $columnKey . ']';
        $id = 'repeater_' . $safeId($repeaterKey . '_' . $rowIndex . '_' . $columnKey);
        $html .= $renderField($column, $name, $id, $row[$columnKey] ?? '');
    }

    $html .= '</div>';
    $html .= '</div>';

    return $html;
};
?>

<div class="form-layout tax-form-layout structured-form-layout">
    <aside class="form-context-panel">
        <div class="eyebrow"><?= esc($schema['eyebrow'] ?? 'Nyilatkozat') ?></div>
        <h1><?= esc($item->template_name ?? ($schema['title'] ?? 'Nyilatkozat')) ?></h1>
        <p><?= esc($schema['intro'] ?? 'A nyilatkozat online kitöltése és mentése.') ?></p>

        <?php if ($person): ?>
            <div class="person-chip person-chip-sidebar">
                <span>Kitöltő</span>
                <strong><?= esc($person->fullName()) ?></strong>
            </div>
        <?php endif; ?>

        <div class="helper-card">
            <div class="helper-title"><?= esc($schema['helper_title'] ?? 'Szükséges adatok') ?></div>
            <?php if (!empty($schema['helper_items']) && is_array($schema['helper_items'])): ?>
                <ul>
                    <?php foreach ($schema['helper_items'] as $helperItem): ?>
                        <li><?= esc((string) $helperItem) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>A nyilatkozathoz csak az adott döntéshez szükséges adatokat kérjük be.</p>
            <?php endif; ?>
        </div>

        <a href="<?= esc($startUrl) ?>" class="btn btn-secondary btn-block">Vissza az összesítőhöz</a>
    </aside>

    <main class="form-main-panel">
        <section class="content-card">
            <div class="section-heading">
                <div>
                    <h2><?= esc($schema['title'] ?? 'Nyilatkozat kitöltése') ?></h2>
                    <p class="section-note">Mentés után az adat az összesítőben ellenőrizhető, és a végleges beküldésig módosítható.</p>
                </div>
            </div>

            <?php if (!empty($validationErrors)): ?>
                <div class="notice notice-danger">
                    <strong>Hiányzó vagy hibás adatok:</strong>
                    <ul class="error-list">
                        <?php foreach ($validationErrors as $error): ?>
                            <li><?= esc($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= esc($itemUrl) ?>" class="public-form form-panel structured-form" data-live-validation novalidate>
                <?= csrf_field() ?>

                <div class="form-progress" data-form-progress>
                    <div class="form-progress-head">
                        <span>Mezők ellenőrzése</span>
                        <span data-progress-label>0/0 mező rendben</span>
                    </div>
                    <div class="progress-rail">
                        <span class="progress-fill" data-progress-fill></span>
                    </div>
                </div>

                <div class="notice notice-danger js-client-errors" hidden aria-live="polite"></div>

                <?php foreach (($schema['sections'] ?? []) as $sectionIndex => $section): ?>
                    <?php if (!is_array($section)) {
                        continue;
                    } ?>
                    <section class="form-section"<?= $sectionAttributes($section) ?>>
                        <div class="section-copy">
                            <h2 class="form-section-title"><?= esc($section['title'] ?? 'Adatok') ?></h2>
                            <?php if (!empty($section['note'])): ?>
                                <p class="section-note"><?= esc((string) $section['note']) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="form-grid form-grid-2">
                            <?php foreach (($section['fields'] ?? []) as $field): ?>
                                <?php
                                if (!is_array($field)) {
                                    continue;
                                }

                                $key = (string) ($field['key'] ?? '');
                                $id = 'statement_' . $safeId((string) $sectionIndex . '_' . $key);
                                echo $renderField($field, 'statement_fields[' . $key . ']', $id, $fieldValue($key));
                                ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>

                <?php foreach (($schema['repeaters'] ?? []) as $repeater): ?>
                    <?php
                    if (!is_array($repeater)) {
                        continue;
                    }

                    $repeaterKey = (string) ($repeater['key'] ?? '');
                    $rows = is_array($oldRepeaters[$repeaterKey] ?? null)
                        ? array_values($oldRepeaters[$repeaterKey])
                        : (is_array($repeaterData[$repeaterKey] ?? null) ? array_values($repeaterData[$repeaterKey]) : []);
                    $minRows = max(0, (int) ($repeater['min'] ?? 0));
                    $maxRows = max($minRows, (int) ($repeater['max'] ?? 20));

                    while (count($rows) < $minRows) {
                        $rows[] = [];
                    }
                    ?>
                    <section class="form-section tax-repeater" data-repeater="<?= esc($repeaterKey) ?>" data-min="<?= esc((string) $minRows) ?>" data-max="<?= esc((string) $maxRows) ?>"<?= $sectionAttributes($repeater) ?><?= $repeaterRequiredAttributes($repeater) ?>>
                        <div class="tax-repeater-title-row">
                            <div class="section-copy">
                                <h2 class="form-section-title"><?= esc($repeater['title'] ?? 'Sorok') ?></h2>
                                <?php if (!empty($repeater['note'])): ?>
                                    <p class="section-note"><?= esc((string) $repeater['note']) ?></p>
                                <?php endif; ?>
                            </div>

                            <button type="button" class="btn btn-secondary btn-sm" data-add-repeater-row>
                                <?= esc($repeater['add_label'] ?? 'Sor hozzáadása') ?>
                            </button>
                        </div>

                        <div class="tax-repeater-rows" data-repeater-rows>
                            <?php foreach ($rows as $index => $row): ?>
                                <?= $renderRepeaterRow($repeater, $index, is_array($row) ? $row : []) ?>
                            <?php endforeach; ?>
                        </div>

                        <template data-repeater-template>
                            <?= $renderRepeaterRow($repeater, '__INDEX__', []) ?>
                        </template>
                    </section>
                <?php endforeach; ?>

                <section class="form-section form-submit-section">
                    <div class="form-group checkbox-group">
                        <label>
                            <input type="checkbox" name="confirm_truth" value="1"
                                data-validate="required"
                                data-label="Valóságtartalomról szóló nyilatkozat"
                                data-error-required="A beküldéshez el kell fogadni a valóságtartalomról szóló nyilatkozatot."
                                required <?= old('confirm_truth', $data['confirm_truth'] ?? '') ? 'checked' : '' ?>>
                            <span>Kijelentem, hogy a nyilatkozatban megadott adatok a valóságnak megfelelnek.</span>
                        </label>
                    </div>

                    <div class="form-actions-row">
                        <a href="<?= esc($startUrl) ?>" class="btn btn-secondary">Mégsem</a>
                        <button type="submit" class="btn btn-primary">Nyilatkozat mentése</button>
                    </div>
                </section>
            </form>
        </section>
    </main>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    (function () {
        function rowsFor(section) {
            return Array.prototype.slice.call(section.querySelectorAll('[data-repeater-row]'));
        }

        function nextIndex(section) {
            return rowsFor(section).reduce(function (max, row) {
                return Math.max(max, Number(row.dataset.rowIndex || 0));
            }, -1) + 1;
        }

        function updateRepeater(section) {
            var rows = rowsFor(section);
            var min = Number(section.dataset.min || 0);
            var max = Number(section.dataset.max || 20);
            var addButton = section.querySelector('[data-add-repeater-row]');

            rows.forEach(function (row, index) {
                var number = row.querySelector('[data-row-number]');
                var removeButton = row.querySelector('[data-remove-repeater-row]');

                if (number) {
                    number.textContent = String(index + 1);
                }

                if (removeButton) {
                    removeButton.disabled = rows.length <= min;
                }
            });

            if (addButton) {
                addButton.disabled = rows.length >= max;
            }
        }

        function refreshValidation(form) {
            if (window.DeclarationLiveValidation) {
                window.DeclarationLiveValidation.init(form);
                window.DeclarationLiveValidation.validate(form, false);
            }
        }

        function escapeHtml(value) {
            return String(value).replace(/[&<>"']/g, function (char) {
                return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char];
            });
        }

        function escapeSelectorValue(value) {
            return String(value || '').replace(/\\/g, '\\\\').replace(/"/g, '\\"');
        }

        function inputByStatementField(form, fieldKey) {
            return form.querySelector('[name="statement_fields[' + escapeSelectorValue(fieldKey) + ']"]');
        }

        function inputValue(input) {
            if (!input) {
                return '';
            }

            if (input.type === 'checkbox' || input.type === 'radio') {
                return input.checked ? String(input.value || '1') : '';
            }

            return String(input.value || '');
        }

        function conditionValues(element, kind) {
            var values = kind === 'required'
                ? element.dataset.requiredWhenValues
                : element.dataset.visibleWhenValues;
            var value = kind === 'required'
                ? element.dataset.requiredWhenValue
                : element.dataset.visibleWhenValue;

            if (values) {
                return String(values).split('|');
            }

            return [String(value || '')];
        }

        function conditionMatches(form, element, kind) {
            var fieldKey = kind === 'required'
                ? element.dataset.requiredWhenField
                : element.dataset.visibleWhenField;

            if (!fieldKey) {
                return true;
            }

            return conditionValues(element, kind).indexOf(inputValue(inputByStatementField(form, fieldKey))) !== -1;
        }

        function setInputRules(input, rules) {
            if (rules.length > 0) {
                input.dataset.validate = rules.join('|');
                return;
            }

            input.removeAttribute('data-validate');
        }

        function clearValidationState(input) {
            var shell = input.closest('.form-group') || input.closest('.checkbox-group') || input.parentNode;
            var error = shell ? shell.querySelector('.field-error') : null;

            input.classList.remove('is-invalid', 'is-valid');

            if (error) {
                error.textContent = '';
                error.hidden = true;
            }
        }

        function parentConditionalSectionHidden(input) {
            var section = input.closest('[data-conditional-section]');

            return !!(section && section.hidden);
        }

        function applyConditionalInput(form, input) {
            var shell = input.closest('[data-conditional-field]');
            var visible = !parentConditionalSectionHidden(input) && conditionMatches(form, input, 'visible');

            if (shell) {
                visible = visible && conditionMatches(form, shell, 'visible');
                shell.hidden = !visible;
            }

            input.disabled = !visible;

            if (!visible) {
                input.required = false;
                input.removeAttribute('required');
                input.removeAttribute('data-validate');
                clearValidationState(input);
                return;
            }

            var baseRules = String(input.dataset.baseValidate || '').split('|').filter(Boolean);
            var required = input.dataset.staticRequired === '1'
                || !!(input.dataset.requiredWhenField && conditionMatches(form, input, 'required'));

            if (required && baseRules.indexOf('required') === -1) {
                baseRules.unshift('required');
            }

            input.required = required;

            if (required) {
                input.setAttribute('required', 'required');
            } else {
                input.removeAttribute('required');
            }

            setInputRules(input, baseRules);
        }

        function refreshConditionalFields(form) {
            Array.prototype.slice.call(form.querySelectorAll('[data-conditional-section]')).forEach(function (section) {
                section.hidden = !conditionMatches(form, section, 'visible');
            });

            Array.prototype.slice.call(form.querySelectorAll('[data-base-validate]')).forEach(function (input) {
                applyConditionalInput(form, input);
            });
        }

        function bindConditionalFields(form) {
            if (form.dataset.conditionalFieldsBound === '1') {
                return;
            }

            form.dataset.conditionalFieldsBound = '1';

            ['input', 'change'].forEach(function (eventName) {
                form.addEventListener(eventName, function (event) {
                    if (!event.target || !/^statement_fields\[/.test(String(event.target.name || ''))) {
                        return;
                    }

                    refreshConditionalFields(form);
                    refreshValidation(form);
                });
            });

            form.addEventListener('submit', function (event) {
                var errors = [];

                Array.prototype.slice.call(form.querySelectorAll('[data-repeater-required]')).forEach(function (section) {
                    if (section.hidden || !conditionMatches(form, section, 'required')) {
                        return;
                    }

                    var minimum = Number(section.dataset.requiredMin || 1);

                    if (rowsFor(section).length < minimum) {
                        var title = section.querySelector('.form-section-title');
                        errors.push((title ? title.textContent.trim() : 'A soros rész') + ': legalább ' + minimum + ' sort meg kell adni.');
                    }
                });

                if (errors.length === 0) {
                    return;
                }

                event.preventDefault();

                var summary = form.querySelector('.js-client-errors');

                if (summary) {
                    summary.hidden = false;
                    summary.innerHTML = '<strong>Kérjük, javítsa az alábbiakat:</strong><ul>'
                        + errors.map(function (error) {
                            return '<li>' + escapeHtml(error) + '</li>';
                        }).join('')
                        + '</ul>';
                    summary.scrollIntoView({behavior: 'smooth', block: 'center'});
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('form.structured-form').forEach(function (form) {
                bindConditionalFields(form);
                refreshConditionalFields(form);
                refreshValidation(form);
            });

            document.querySelectorAll('[data-repeater]').forEach(function (section) {
                updateRepeater(section);

                var addButton = section.querySelector('[data-add-repeater-row]');
                var rowsContainer = section.querySelector('[data-repeater-rows]');
                var template = section.querySelector('template[data-repeater-template]');

                if (addButton && rowsContainer && template) {
                    addButton.addEventListener('click', function () {
                        var max = Number(section.dataset.max || 20);

                        if (rowsFor(section).length >= max) {
                            return;
                        }

                        var index = nextIndex(section);
                        var html = template.innerHTML
                            .replace(/__INDEX__/g, String(index))
                            .replace(/__ROW__/g, String(rowsFor(section).length + 1));

                        rowsContainer.insertAdjacentHTML('beforeend', html);
                        updateRepeater(section);

                        var form = section.closest('form');
                        if (form) {
                            refreshConditionalFields(form);
                            refreshValidation(form);
                        }
                    });
                }
            });

            document.addEventListener('click', function (event) {
                var button = event.target.closest('[data-remove-repeater-row]');

                if (!button) {
                    return;
                }

                var section = button.closest('[data-repeater]');
                var row = button.closest('[data-repeater-row]');

                if (!section || !row || rowsFor(section).length <= Number(section.dataset.min || 0)) {
                    return;
                }

                row.remove();
                updateRepeater(section);

                var form = section.closest('form');
                if (form) {
                    refreshValidation(form);
                }
            });
        });
    }());
</script>
<?= $this->endSection() ?>
