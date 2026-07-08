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

$templateFields = $templateFields ?? [];
$templateHasPlaceholders = (bool) ($templateHasPlaceholders ?? false);
$templateCanInspect = (bool) ($templateCanInspect ?? true);
$templateFileAvailable = (bool) ($templateFileAvailable ?? false);
$canSubmitTemplate = $templateFileAvailable && $templateCanInspect && $templateHasPlaceholders;
$templateFieldValues = is_array($data['template_fields'] ?? null) ? $data['template_fields'] : [];
$validationErrors = session()->getFlashdata('validationErrors') ?? [];
$templateName = $item->template_name ?? 'Nyilatkozat';
?>

<div class="form-layout">
    <aside class="form-context-panel">
        <div class="eyebrow">Nyilatkozat</div>
        <h1><?= esc($templateName) ?></h1>
        <p>
            A nyilatkozat adatai mentés után az összesítőben és a PDF előnézetben ellenőrizhetők.
        </p>

        <?php if ($person): ?>
            <div class="person-chip person-chip-sidebar">
                <span>Kitöltő</span>
                <strong><?= esc($person->fullName()) ?></strong>
            </div>
        <?php endif; ?>

        <div class="helper-card">
            <div class="helper-title">Sablon adatai</div>
            <ul>
                <?php if (!empty($item->template_tax_year)): ?>
                    <li>Adóév: <?= esc($item->template_tax_year) ?></li>
                <?php endif; ?>
                <?php if (!empty($item->template_version)): ?>
                    <li>Verzió: <?= esc($item->template_version) ?></li>
                <?php endif; ?>
                <li><?= !empty($templateFileAvailable) ? 'DOCX sablon elérhető' : 'DOCX sablon még nincs hozzárendelve' ?></li>
            </ul>
        </div>

        <a href="<?= esc($startUrl) ?>" class="btn btn-secondary btn-block">Vissza az áttekintéshez</a>
    </aside>

    <main class="form-main-panel">
        <section class="content-card">
            <div class="section-heading">
                <div>
                    <h2>Nyilatkozat kitöltése</h2>
                    <p class="section-note">
                        A sablonban szereplő egyedi helyőrzők itt jelennek meg mezőként. Az alap személyes adatokat a rendszer automatikusan tölti a személy adatlapjáról.
                    </p>
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

            <?php if (!$templateFileAvailable): ?>
                <div class="notice notice-danger">
                    Ehhez a nyilatkozathoz még nincs DOCX sablon hozzárendelve, ezért nem tölthető ki online.
                </div>
            <?php elseif (!$templateCanInspect): ?>
                <div class="notice notice-danger">
                    A DOCX sablon mezőinek kiolvasásához a szerveren ZipArchive vagy PharData támogatás szükséges.
                </div>
            <?php elseif (!$templateHasPlaceholders): ?>
                <div class="notice notice-danger">
                    Ez a DOCX sablon még nincs online kitöltésre előkészítve. A sablonba `${...}` formátumú helyőrzőket kell tenni, például `${név}`, `${adóazonosító}` vagy egyedi mezőkhöz `${kedvezmény_kezdete}`.
                </div>
            <?php elseif (empty($templateFields)): ?>
                <div class="notice notice-info">
                    A sablon csak automatikusan tölthető helyőrzőket tartalmaz. Mentés előtt ellenőrizze a nyilatkozatot, majd erősítse meg a beküldést.
                </div>
            <?php endif; ?>

            <?php if ($canSubmitTemplate): ?>
            <form method="post" action="<?= esc($itemUrl) ?>" class="public-form form-panel" data-live-validation novalidate>
                <?= csrf_field() ?>

                <div class="notice notice-danger js-client-errors" hidden aria-live="polite"></div>

                <?php if (!empty($templateFields)): ?>
                    <section class="form-section">
                        <div class="section-copy">
                            <h2 class="form-section-title">Sablon mezői</h2>
                            <p class="section-note">Ezek a DOCX sablonban talált, nem automatikusan tölthető helyőrzők.</p>
                        </div>

                        <div class="form-grid form-grid-2">
                            <?php foreach ($templateFields as $field): ?>
                                <?php
                                $fieldKey = (string) ($field['key'] ?? '');
                                $encodedKey = (string) ($field['encoded_key'] ?? rawurlencode($fieldKey));
                                $fieldLabel = (string) ($field['label'] ?? $fieldKey);
                                $fieldValue = old('placeholder_values.' . $encodedKey, $templateFieldValues[$fieldKey] ?? ($data[$fieldKey] ?? ''));
                                ?>
                                <div class="form-group">
                                    <label for="placeholder_<?= esc(md5($fieldKey)) ?>"><?= esc($fieldLabel) ?></label>
                                    <input type="text"
                                        id="placeholder_<?= esc(md5($fieldKey)) ?>"
                                        name="placeholder_values[<?= esc($encodedKey) ?>]"
                                        value="<?= esc($fieldValue) ?>"
                                        autocomplete="off"
                                        data-validate="required|min:1"
                                        data-label="<?= esc($fieldLabel) ?>"
                                        required>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

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
            <?php else: ?>
                <div class="form-actions-row">
                    <a href="<?= esc($startUrl) ?>" class="btn btn-secondary">Vissza az áttekintéshez</a>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<?= $this->endSection() ?>
