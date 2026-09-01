<?= $this->extend('App\\Modules\\Declarations\\Views\\public\\layout') ?>

<?= $this->section('content') ?>

<?php
$data = [];
$submissionDataNormalizer = new \App\Modules\Declarations\Services\DeclarationSubmissionDataNormalizer();

if ($submission && !empty($submission->data_json)) {
    $rawData = $submission->data_json;
    $data = $submissionDataNormalizer->normalize($rawData);
}

$templateCode = (string) ($item->template_code ?? '');
$storedFields = is_array($data['tax_fields'] ?? null) ? $data['tax_fields'] : [];
$storedRepeaters = is_array($data['repeaters'] ?? null) ? $data['repeaters'] : [];

if ($templateCode === 'under_30_mothers_discount' && empty($storedFields['eligibility_basis'])) {
    $legacyChild = is_array($storedRepeaters['children'][0] ?? null) ? $storedRepeaters['children'][0] : [];

    if (!empty($legacyChild)) {
        $storedFields['eligibility_basis'] = 'child';
        $storedFields['child_name'] = (string) ($legacyChild['name'] ?? '');
        $storedFields['child_tax_number'] = (string) ($legacyChild['tax_number'] ?? '');
        $storedFields['declaration_action'] = 'claim';
    }
}

if (in_array($templateCode, ['family_tax_discount', 'combined_family_mothers_discount'], true)) {
    foreach (($storedRepeaters['dependents'] ?? []) as $index => $row) {
        if (!is_array($row) || !empty($row['dependent_type'])) {
            continue;
        }

        $isFetus = strtolower(trim((string) ($row['tax_number'] ?? $row['name'] ?? ''))) === 'magzat';
        $storedRepeaters['dependents'][$index]['dependent_type'] = $isFetus ? 'fetus' : 'person';
    }
}

if ($templateCode === 'combined_family_mothers_discount') {
    foreach (($storedRepeaters['dependents'] ?? []) as $index => $row) {
        if (!is_array($row) || ($row['dependent_type'] ?? '') !== 'person' || !empty($row['identification_method'])) {
            continue;
        }

        $storedRepeaters['dependents'][$index]['identification_method'] = !empty($row['tax_number'])
            ? 'tax_number'
            : 'birth_data';
    }
}

if ($templateCode === 'mothers_of_four_discount') {
    $storedFields['declaration_action'] = (string) ($storedFields['declaration_action'] ?? 'claim');
    $storedChildren = is_array($storedRepeaters['children'] ?? null) ? $storedRepeaters['children'] : [];

    if (empty($storedFields['mother_discount_type']) && count($storedChildren) >= 2) {
        $storedFields['mother_discount_type'] = count($storedChildren) >= 4
            ? 'four_plus'
            : (count($storedChildren) === 3 ? 'three' : 'two');
    }

    foreach ($storedChildren as $index => $row) {
        if (!is_array($row) || !empty($row['identification_method'])) {
            continue;
        }

        $storedRepeaters['children'][$index]['identification_method'] = !empty($row['tax_number'])
            ? 'tax_number'
            : 'birth_data';
    }
}

if ($templateCode === 'combined_family_mothers_discount' && empty($storedFields['mother_discount_type'])) {
    $legacyDependentCount = count(is_array($storedRepeaters['dependents'] ?? null) ? $storedRepeaters['dependents'] : []);

    if ($legacyDependentCount >= 2) {
        $storedFields['mother_discount_type'] = $legacyDependentCount >= 4
            ? 'four_plus'
            : ($legacyDependentCount === 3 ? 'three' : 'two');
    }
}

if ($templateCode === 'first_marriage_discount' && empty($storedFields['declaration_action'])) {
    $storedFields['declaration_action'] = 'claim';
}

if ($templateCode === 'personal_discount' && empty($storedFields['declaration_action'])) {
    $storedFields['declaration_action'] = 'claim';
    $storedFields['eligibility_basis'] = (string) ($storedFields['eligibility_basis'] ?? 'medical_certificate');
}

if ($templateCode === 'personal_discount'
    && ($storedFields['eligibility_basis'] ?? '') === 'medical_certificate'
    && empty($storedFields['condition_duration'])
) {
    if (!empty($storedFields['permanent_condition'])) {
        $storedFields['condition_duration'] = 'permanent';
    } elseif (!empty($storedFields['eligibility_end'])) {
        $storedFields['condition_duration'] = 'until_date';
    }
}

$data['tax_fields'] = $storedFields;
$data['repeaters'] = $storedRepeaters;

$schema = is_array($taxFormSchema ?? null) ? $taxFormSchema : [];
$fieldData = is_array($data['tax_fields'] ?? null) ? $data['tax_fields'] : [];
$repeaterData = is_array($data['repeaters'] ?? null) ? $data['repeaters'] : [];
$oldFields = old('tax_fields');
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

    if (!empty($field['not_future'])) {
        $rules[] = 'not_future';
    }

    if (($field['type'] ?? '') === 'month') {
        $rules[] = 'month';
    }

    if (($field['type'] ?? '') === 'number' && isset($field['min_value'])) {
        $rules[] = 'min_value:' . (string) $field['min_value'];
    }

    if (($field['validation'] ?? '') === 'tax_number') {
        $rules[] = 'tax_number';
    }

    if (($field['validation'] ?? '') === 'company_tax_number') {
        $rules[] = 'company_tax_number';
    }

    if (($field['validation'] ?? '') === 'tax_number_or_fetus') {
        $rules[] = 'tax_number_or_fetus';
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
        $values = implode('|', array_map('strval', $condition['values']));
        $attributes .= ' data-' . $prefix . '-values="' . esc($values) . '"';
    } else {
        $attributes .= ' data-' . $prefix . '-value="' . esc((string) ($condition['value'] ?? '')) . '"';
    }

    return $attributes;
};

$rowConditionAttributes = static function (array $field, string $conditionKey): string {
    $condition = $field[$conditionKey] ?? null;

    if (!is_array($condition)) {
        return '';
    }

    $fieldKey = trim((string) ($condition['field'] ?? ''));

    if ($fieldKey === '') {
        return '';
    }

    $prefix = $conditionKey === 'required_when_row' ? 'required-row' : 'visible-row';
    $attributes = ' data-' . $prefix . '-field="' . esc($fieldKey) . '"';

    if (isset($condition['values']) && is_array($condition['values'])) {
        $attributes .= ' data-' . $prefix . '-values="' . esc(implode('|', array_map('strval', $condition['values']))) . '"';
    } else {
        $attributes .= ' data-' . $prefix . '-value="' . esc((string) ($condition['value'] ?? '')) . '"';
    }

    return $attributes;
};

$shellAttributes = static function (array $field) use ($conditionAttributes, $rowConditionAttributes): string {
    $attributes = $conditionAttributes($field, 'visible_when');

    if ($attributes !== '') {
        $attributes = ' data-conditional-field' . $attributes;
    }

    $rowAttributes = $rowConditionAttributes($field, 'visible_when_row');

    if ($rowAttributes !== '') {
        $attributes .= ' data-conditional-row-field' . $rowAttributes;
    }

    return $attributes;
};

$inputConditionAttributes = static function (array $field) use ($conditionAttributes, $rowConditionAttributes): string {
    return $conditionAttributes($field, 'visible_when')
        . $conditionAttributes($field, 'required_when')
        . $rowConditionAttributes($field, 'visible_when_row')
        . $rowConditionAttributes($field, 'required_when_row');
};

$sectionAttributes = static function (array $section) use ($conditionAttributes): string {
    $attributes = $conditionAttributes($section, 'visible_when');

    if ($attributes !== '') {
        $attributes = ' data-conditional-section' . $attributes;
    }

    return $attributes;
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

    if (isset($field['required_unless_row_values']) && is_array($field['required_unless_row_values'])) {
        $rowCondition = $field['required_unless_row_values'];
        $rowField = trim((string) ($rowCondition['field'] ?? ''));
        $rowValues = isset($rowCondition['values']) && is_array($rowCondition['values'])
            ? implode('|', array_map('strval', $rowCondition['values']))
            : '';

        if ($rowField !== '') {
            $validationAttributes .= ' data-required-unless-row-field="' . esc($rowField) . '"';
            $validationAttributes .= ' data-required-unless-row-values="' . esc($rowValues) . '"';
        }
    }

    $requiredAttribute = $required ? ' required' : '';
    $help = trim((string) ($field['help'] ?? ''));

    if ($type === 'checkbox') {
        return '<div class="form-group checkbox-group tax-checkbox"' . $shellAttributesHtml . '>'
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
        $inputType = in_array($type, ['date', 'month'], true) ? $type : 'text';
        $formatAttributes = '';

        if ($type === 'date' && !empty($field['not_future'])) {
            $formatAttributes .= ' max="' . esc(date('Y-m-d')) . '"';
        }

        if (($field['validation'] ?? '') === 'tax_number') {
            $formatAttributes .= ' inputmode="numeric" maxlength="10" data-format="digits" data-max-digits="10" placeholder="10 számjegy"';
        } elseif (($field['validation'] ?? '') === 'company_tax_number') {
            $formatAttributes .= ' inputmode="numeric" maxlength="13" data-format="company_tax_number" data-max-digits="11" placeholder="12345676-1-42"';
        } elseif (($field['validation'] ?? '') === 'tax_number_or_fetus') {
            $formatAttributes .= ' maxlength="10" placeholder="10 számjegy vagy magzat"';
        } elseif ($type === 'number') {
            $formatAttributes .= ' inputmode="numeric" data-format="digits"';
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
    $rowLabel = (string) ($repeater['row_label'] ?? 'adatlap');
    $html = '<div class="tax-repeater-row" data-repeater-row data-row-index="' . esc($rowIndex) . '">';
    $html .= '<div class="tax-repeater-row-head">';
    $html .= '<strong><span data-row-number>' . esc((string) $rowNumber) . '</span>. ' . esc($rowLabel) . '</strong>';
    $html .= '<button type="button" class="btn btn-secondary btn-sm" data-remove-repeater-row>Eltávolítás</button>';
    $html .= '</div>';
    $html .= '<div class="tax-repeater-grid">';

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

<div class="form-layout tax-form-layout">
    <aside class="form-context-panel">
        <div class="eyebrow"><?= esc($schema['eyebrow'] ?? 'Adóügy') ?></div>
        <h1><?= esc($schema['title'] ?? ($item->template_name ?? 'Adóügyi nyilatkozat')) ?></h1>
        <p><?= esc($schema['intro'] ?? 'Az adóügyi nyilatkozat online kitöltése.') ?></p>

        <?php if ($person): ?>
            <div class="person-chip person-chip-sidebar">
                <span>Kitöltő</span>
                <strong><?= esc($person->fullName()) ?></strong>
            </div>
        <?php endif; ?>

        <div class="helper-card">
            <div class="helper-title">Automatikusan töltött adatok</div>
            <dl class="tax-auto-list">
                <div>
                    <dt>Név</dt>
                    <dd><?= esc($person ? $person->fullName() : '-') ?></dd>
                </div>
                <div>
                    <dt>Adóazonosító jel</dt>
                    <dd><?= esc($person->tax_number ?? '-') ?></dd>
                </div>
                <div>
                    <dt>Adóév</dt>
                    <dd><?= esc($packet->tax_year ?? ($item->template_tax_year ?? '-')) ?></dd>
                </div>
            </dl>
        </div>

        <div class="helper-card">
            <div class="helper-title">Miért nem kérjük újra?</div>
            <p>Az alap személyes adatok a korábbi személyes adatok nyilatkozatából kerülnek a nyilatkozatba. Itt csak az adóügyi döntéseket és kapcsolódó személyeket kell rögzíteni.</p>
        </div>

        <a href="<?= esc($startUrl) ?>" class="btn btn-secondary btn-block">Vissza az összesítőhöz</a>
    </aside>

    <main class="form-main-panel">
        <section class="content-card">
            <div class="section-heading">
                <div>
                    <h2><?= esc($schema['title'] ?? 'Adóügyi nyilatkozat') ?></h2>
                    <p class="section-note">Mentés után az adatok az összesítőben ellenőrizhetők, a végleges beküldésig pedig módosíthatók.</p>
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

            <form method="post" action="<?= esc($itemUrl) ?>" class="public-form form-panel tax-form" data-live-validation novalidate>
                <?= csrf_field() ?>

                <div class="form-progress" data-form-progress>
                    <div class="form-progress-head">
                        <span>Mezők ellenőrzése</span>
                        <span data-progress-label>0/0 kötelező mező kész</span>
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
                                $id = 'tax_' . $safeId((string) $sectionIndex . '_' . $key);
                                echo $renderField($field, 'tax_fields[' . $key . ']', $id, $fieldValue($key));
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
                    $repeaterCondition = $conditionAttributes($repeater, 'visible_when');
                    $repeaterCondition = $repeaterCondition !== '' ? ' data-conditional-repeater' . $repeaterCondition : '';
                    $minByField = is_array($repeater['min_by_field'] ?? null)
                        ? json_encode($repeater['min_by_field'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                        : '';
                    $qualifiedMinByField = is_array($repeater['qualified_min_by_field'] ?? null)
                        ? json_encode($repeater['qualified_min_by_field'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                        : '';

                    while (count($rows) < $minRows) {
                        $rows[] = [];
                    }
                    ?>
                    <section class="form-section tax-repeater"
                        data-repeater="<?= esc($repeaterKey) ?>"
                        data-min="<?= esc((string) $minRows) ?>"
                        data-max="<?= esc((string) $maxRows) ?>"
                        <?= $minByField !== '' ? 'data-min-by-field="' . esc($minByField, 'attr') . '"' : '' ?>
                        <?= $qualifiedMinByField !== '' ? 'data-qualified-min-by-field="' . esc($qualifiedMinByField, 'attr') . '"' : '' ?>
                        <?= !empty($repeater['qualified_min_message']) ? 'data-qualified-min-message="' . esc((string) $repeater['qualified_min_message'], 'attr') . '"' : '' ?>
                        <?= $repeaterCondition ?>>
                        <div class="tax-repeater-title-row">
                            <div class="section-copy">
                                <h2 class="form-section-title"><?= esc($repeater['title'] ?? 'Adatok') ?></h2>
                                <?php if (!empty($repeater['note'])): ?>
                                    <p class="section-note"><?= esc((string) $repeater['note']) ?></p>
                                <?php endif; ?>
                            </div>

                            <button type="button" class="btn btn-secondary btn-sm" data-add-repeater-row>
                                <?= esc($repeater['add_label'] ?? 'Új adatlap') ?>
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
            var min = Number(section.dataset.currentMin || section.dataset.min || 0);
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

        function appendRepeaterRow(section) {
            var rowsContainer = section.querySelector('[data-repeater-rows]');
            var template = section.querySelector('template[data-repeater-template]');
            var max = Number(section.dataset.max || 20);

            if (!rowsContainer || !template || rowsFor(section).length >= max) {
                return false;
            }

            var index = nextIndex(section);
            var html = template.innerHTML
                .replace(/__INDEX__/g, String(index))
                .replace(/__ROW__/g, String(rowsFor(section).length + 1));

            rowsContainer.insertAdjacentHTML('beforeend', html);

            return true;
        }

        function refreshValidation(form) {
            if (window.DeclarationLiveValidation) {
                window.DeclarationLiveValidation.init(form);
                window.DeclarationLiveValidation.validate(form, false);
            }
        }

        function inputByTaxField(form, fieldKey) {
            var key = String(fieldKey || '').replace(/\\/g, '\\\\').replace(/"/g, '\\"');

            return form.querySelector('[name="tax_fields[' + key + ']"]');
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

            return conditionValues(element, kind).indexOf(inputValue(inputByTaxField(form, fieldKey))) !== -1;
        }

        function rowConditionValues(element, kind) {
            var values = kind === 'required'
                ? element.dataset.requiredRowValues
                : element.dataset.visibleRowValues;
            var value = kind === 'required'
                ? element.dataset.requiredRowValue
                : element.dataset.visibleRowValue;

            return values ? String(values).split('|') : [String(value || '')];
        }

        function conditionMatchesRow(element, kind) {
            var fieldKey = kind === 'required'
                ? element.dataset.requiredRowField
                : element.dataset.visibleRowField;

            if (!fieldKey) {
                return true;
            }

            var row = element.closest('[data-repeater-row]');

            if (!row) {
                return false;
            }

            return rowConditionValues(element, kind).indexOf(inputValue(inputByRowField(row, fieldKey))) !== -1;
        }

        function inputByRowField(row, fieldKey) {
            var targetNameSuffix = '[' + String(fieldKey || '') + ']';
            var controls = Array.prototype.slice.call(row.querySelectorAll('input, select, textarea'));

            return controls.find(function (control) {
                return String(control.name || '').slice(-targetNameSuffix.length) === targetNameSuffix;
            }) || null;
        }

        function requiredByRowCondition(input) {
            var fieldKey = input.dataset.requiredUnlessRowField;

            if (!fieldKey) {
                return false;
            }

            var row = input.closest('[data-repeater-row]');

            if (!row) {
                return false;
            }

            var ignoredValues = String(input.dataset.requiredUnlessRowValues || '')
                .split('|')
                .filter(function (value) { return value !== ''; });
            var sourceValue = inputValue(inputByRowField(row, fieldKey));

            return ignoredValues.indexOf(sourceValue) === -1;
        }

        function setInputRules(input, rules) {
            if (rules.length > 0) {
                input.dataset.validate = rules.join('|');
                return;
            }

            input.removeAttribute('data-validate');
        }

        function escapeHtml(value) {
            return String(value).replace(/[&<>"']/g, function (character) {
                return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[character];
            });
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

        function applyConditionalInput(form, input) {
            var shell = input.closest('[data-conditional-field]');
            var rowShell = input.closest('[data-conditional-row-field]');
            var visible = conditionMatches(form, input, 'visible');

            if (shell) {
                visible = visible && conditionMatches(form, shell, 'visible');
                shell.hidden = !visible;
            }

            if (rowShell) {
                visible = visible && conditionMatchesRow(rowShell, 'visible');
                rowShell.hidden = !visible;
            }

            if (input.closest('[hidden]')) {
                visible = false;
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
                || !!(input.dataset.requiredWhenField && conditionMatches(form, input, 'required'))
                || !!(input.dataset.requiredRowField && conditionMatchesRow(input, 'required'))
                || requiredByRowCondition(input);

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

        function minimumForRepeater(form, section) {
            var minimum = Number(section.dataset.min || 0);

            if (!section.dataset.minByField) {
                return minimum;
            }

            try {
                var rule = JSON.parse(section.dataset.minByField);
                var source = inputByTaxField(form, rule.field);
                var value = inputValue(source);

                return Math.max(minimum, Number((rule.values || {})[value] || 0));
            } catch (error) {
                return minimum;
            }
        }

        function refreshConditionalRepeaters(form) {
            Array.prototype.slice.call(form.querySelectorAll('[data-repeater]')).forEach(function (section) {
                var visible = !section.dataset.visibleWhenField || conditionMatches(form, section, 'visible');
                section.hidden = !visible;
                section.dataset.currentMin = visible ? String(minimumForRepeater(form, section)) : '0';

                Array.prototype.slice.call(section.querySelectorAll('input, select, textarea, button')).forEach(function (control) {
                    if (!control.matches('[data-add-repeater-row], [data-remove-repeater-row]')) {
                        control.disabled = !visible;
                    }
                });

                if (visible) {
                    while (rowsFor(section).length < Number(section.dataset.currentMin || 0)) {
                        if (!appendRepeaterRow(section)) {
                            break;
                        }
                    }
                }

                updateRepeater(section);
            });
        }

        function refreshConditionalFields(form) {
            Array.prototype.slice.call(form.querySelectorAll('[data-conditional-section]')).forEach(function (section) {
                section.hidden = !conditionMatches(form, section, 'visible');
            });

            refreshConditionalRepeaters(form);

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
                    if (!event.target || !event.target.matches('input, select, textarea')) {
                        return;
                    }

                    refreshConditionalFields(form);
                    refreshValidation(form);
                });
            });

            form.addEventListener('submit', function (event) {
                var errors = [];

                Array.prototype.slice.call(form.querySelectorAll('[data-qualified-min-by-field]')).forEach(function (section) {
                    if (section.hidden) {
                        return;
                    }

                    try {
                        var rule = JSON.parse(section.dataset.qualifiedMinByField);
                        var sourceValue = inputValue(inputByTaxField(form, rule.field));
                        var required = Number((rule.values || {})[sourceValue] || 0);
                        var acceptedValues = Array.isArray(rule.accepted_values) ? rule.accepted_values.map(String) : [];
                        var qualified = rowsFor(section).filter(function (row) {
                            return acceptedValues.indexOf(inputValue(inputByRowField(row, rule.row_field))) !== -1;
                        }).length;

                        if (required > 0 && qualified < required) {
                            errors.push(String(section.dataset.qualifiedMinMessage || 'Legalább %d jogosító gyermek adata szükséges.')
                                .replace('%d', String(required)));
                        }
                    } catch (error) {
                        return;
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
            document.querySelectorAll('form.tax-form').forEach(function (form) {
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
                        appendRepeaterRow(section);
                        updateRepeater(section);
                        refreshConditionalFields(section.closest('form'));
                        refreshValidation(section.closest('form'));
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

                if (!section || !row || rowsFor(section).length <= Number(section.dataset.currentMin || section.dataset.min || 0)) {
                    return;
                }

                row.remove();
                updateRepeater(section);
                refreshValidation(section.closest('form'));
            });
        });
    }());
</script>
<?= $this->endSection() ?>
