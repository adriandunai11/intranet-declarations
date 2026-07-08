<?= $this->extend('App\\Modules\\Declarations\\Views\\public\\layout') ?>

<?= $this->section('content') ?>

<?php
$data = [];

if ($submission && !empty($submission->data_json)) {
    $rawData = $submission->data_json;

    if ($rawData instanceof \stdClass) {
        $rawData = (array) $rawData;
    }

    if (is_string($rawData)) {
        $decoded = json_decode($rawData, true);

        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        $rawData = is_array($decoded) ? $decoded : [];
    }

    $data = is_array($rawData) ? $rawData : [];
}

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

    if (($field['validation'] ?? '') === 'tax_number') {
        $rules[] = 'tax_number';
    }

    if (($field['validation'] ?? '') === 'tax_number_or_fetus') {
        $rules[] = 'tax_number_or_fetus';
    }

    return implode('|', $rules);
};

$renderField = static function (array $field, string $name, string $id, $value) use ($rulesForField): string {
    $key = (string) ($field['key'] ?? '');
    $label = (string) ($field['label'] ?? $key);
    $type = (string) ($field['type'] ?? 'text');
    $required = !empty($field['required']);
    $rules = $rulesForField($field);
    $validationAttributes = $rules !== ''
        ? ' data-validate="' . esc($rules) . '" data-label="' . esc($label) . '"'
        : ' data-label="' . esc($label) . '"';
    $requiredAttribute = $required ? ' required' : '';
    $help = trim((string) ($field['help'] ?? ''));

    if ($type === 'checkbox') {
        return '<div class="form-group checkbox-group tax-checkbox">'
            . '<label>'
            . '<input type="checkbox" name="' . esc($name) . '" value="1"' . $validationAttributes . $requiredAttribute . ((int) $value === 1 ? ' checked' : '') . '>'
            . '<span>' . esc($label) . '</span>'
            . '</label>'
            . '</div>';
    }

    $html = '<div class="form-group">';
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
        } elseif (($field['validation'] ?? '') === 'tax_number_or_fetus') {
            $formatAttributes = ' maxlength="10" placeholder="10 számjegy vagy magzat"';
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
        <h1><?= esc($item->template_name ?? ($schema['title'] ?? 'Adóügyi nyilatkozat')) ?></h1>
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
                <?php if (!empty($item->template_version)): ?>
                    <div>
                        <dt>Sablonverzió</dt>
                        <dd><?= esc($item->template_version) ?></dd>
                    </div>
                <?php endif; ?>
            </dl>
        </div>

        <div class="helper-card">
            <div class="helper-title">Miért nem kérjük újra?</div>
            <p>Az alap személyes adatok a korábbi személyes adat nyilatkozatból kerülnek a dokumentumba. Itt csak az adóügyi döntéseket és kapcsolódó személyeket kell rögzíteni.</p>
        </div>

        <a href="<?= esc($startUrl) ?>" class="btn btn-secondary btn-block">Vissza az összesítőhöz</a>
    </aside>

    <main class="form-main-panel">
        <section class="content-card">
            <div class="section-heading">
                <div>
                    <h2><?= esc($schema['title'] ?? 'Adóügyi nyilatkozat') ?></h2>
                    <p class="section-note">Mentés után az adatok az összesítőben és a PDF előnézetben ellenőrizhetők, a végleges beküldésig pedig módosíthatók.</p>
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
                    <section class="form-section">
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

                    while (count($rows) < $minRows) {
                        $rows[] = [];
                    }
                    ?>
                    <section class="form-section tax-repeater" data-repeater="<?= esc($repeaterKey) ?>" data-min="<?= esc((string) $minRows) ?>" data-max="<?= esc((string) $maxRows) ?>">
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

        document.addEventListener('DOMContentLoaded', function () {
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

                if (!section || !row || rowsFor(section).length <= Number(section.dataset.min || 0)) {
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
