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

$validationErrors = session()->getFlashdata('validationErrors') ?? [];
?>

<div class="form-layout">
    <aside class="form-context-panel">
        <div class="eyebrow">Személyes adatok</div>
        <h1><?= esc($item->template_name ?? 'Személyes adatok nyilatkozata') ?></h1>
        <p>
            A hivatalos okmányokon szereplő adatokat adja meg. A TAJ számot és az adóazonosító jelet kitöltés közben ellenőrizzük.
        </p>

        <?php if ($person): ?>
            <div class="person-chip person-chip-sidebar">
                <span>Kitöltő</span>
                <strong><?= esc($person->fullName()) ?></strong>
            </div>
        <?php endif; ?>

        <div class="helper-card">
            <div class="helper-title">Előkészítendő adatok</div>
            <ul>
                <li>Születési név, hely és idő</li>
                <li>Anyja születési neve</li>
                <li>TAJ szám és adóazonosító jel</li>
                <li>Elérhető telefonszám</li>
            </ul>
        </div>

        <a href="<?= esc($startUrl) ?>" class="btn btn-secondary btn-block">Vissza az összesítőhöz</a>
    </aside>

    <main class="form-main-panel">
        <section class="content-card">
            <div class="section-heading">
                <div>
                    <h2>Személyes adatok kitöltése</h2>
                    <p class="section-note">Mentés után az adatok az összesítőben ellenőrizhetők, és a végleges beküldésig módosíthatók.</p>
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

            <form method="post" action="<?= esc($itemUrl) ?>" class="public-form form-panel" id="personalDataForm" data-live-validation novalidate>
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

                <section class="form-section">
                    <div class="section-copy">
                        <h2 class="form-section-title">Születési adatok</h2>
                        <p class="section-note">Az okmányokon szereplő adatokat használja.</p>
                    </div>

                    <div class="form-grid form-grid-2">
                        <div class="form-group">
                            <label for="birth_name">Születési név</label>
                            <input type="text" id="birth_name" name="birth_name"
                                value="<?= esc(old('birth_name', $data['birth_name'] ?? ($person->birth_name ?? ''))) ?>"
                                autocomplete="name" data-validate="required|min:3" data-label="Születési név" required>
                        </div>

                        <div class="form-group">
                            <label for="mother_name">Anyja neve</label>
                            <input type="text" id="mother_name" name="mother_name"
                                value="<?= esc(old('mother_name', $data['mother_name'] ?? ($person->mother_name ?? ''))) ?>"
                                autocomplete="off" data-validate="required|min:3" data-label="Anyja neve" required>
                        </div>
                    </div>

                    <div class="form-grid form-grid-2">
                        <div class="form-group">
                            <label for="birth_place">Születési hely</label>
                            <input type="text" id="birth_place" name="birth_place"
                                value="<?= esc(old('birth_place', $data['birth_place'] ?? ($person->birth_place ?? ''))) ?>"
                                autocomplete="off" data-validate="required|min:2" data-label="Születési hely" required>
                        </div>

                        <div class="form-group">
                            <label for="birth_date">Születési dátum</label>
                            <input type="date" id="birth_date" name="birth_date"
                                value="<?= esc(old('birth_date', $data['birth_date'] ?? ($person->birth_date ?? ''))) ?>"
                                data-validate="required|date|not_future" data-label="Születési dátum" required>
                        </div>
                    </div>
                </section>

                <section class="form-section">
                    <div class="section-copy">
                        <h2 class="form-section-title">Azonosítók és elérhetőség</h2>
                        <p class="section-note">A hibás azonosítókat azonnal jelezzük a mező alatt.</p>
                    </div>

                    <div class="form-grid form-grid-2">
                        <div class="form-group">
                            <label for="tax_number">Adóazonosító jel</label>
                            <input type="text" id="tax_number" name="tax_number"
                                value="<?= esc(old('tax_number', $data['tax_number'] ?? ($person->tax_number ?? ''))) ?>"
                                maxlength="10" inputmode="numeric" autocomplete="off" placeholder="10 számjegy"
                                data-format="digits" data-max-digits="10"
                                data-validate="required|tax_number" data-label="Adóazonosító jel" required>
                            <div class="form-help">10 számjegy, kötőjel és szóköz nélkül.</div>
                        </div>

                        <div class="form-group">
                            <label for="taj_number">TAJ szám</label>
                            <input type="text" id="taj_number" name="taj_number"
                                value="<?= esc(old('taj_number', $data['taj_number'] ?? ($person->taj_number ?? ''))) ?>"
                                maxlength="11" inputmode="numeric" autocomplete="off" placeholder="123 456 789"
                                data-format="taj" data-validate="required|taj_number" data-label="TAJ szám" required>
                            <div class="form-help">9 számjegy, a mező automatikusan tagolja.</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="phone">Telefonszám</label>
                        <input type="text" id="phone" name="phone"
                            value="<?= esc(old('phone', $data['phone'] ?? ($person->phone ?? ''))) ?>"
                            autocomplete="tel" data-validate="required|phone" data-label="Telefonszám" required>
                    </div>
                </section>

                <section class="form-section form-submit-section">
                    <div class="form-group checkbox-group">
                        <label>
                            <input type="checkbox" name="confirm_truth" value="1"
                                data-validate="required"
                                data-label="Valóságtartalomról szóló nyilatkozat"
                                data-error-required="A beküldéshez el kell fogadni a valóságtartalomról szóló nyilatkozatot."
                                required <?= old('confirm_truth', $data['confirm_truth'] ?? '') ? 'checked' : '' ?>>
                            <span>Kijelentem, hogy a megadott adatok a valóságnak megfelelnek.</span>
                        </label>
                    </div>

                    <div class="form-actions-row">
                        <a href="<?= esc($startUrl) ?>" class="btn btn-secondary">Mégsem</a>
                        <button type="submit" class="btn btn-primary">Adatok mentése</button>
                    </div>
                </section>
            </form>
        </section>
    </main>
</div>

<?= $this->endSection() ?>
