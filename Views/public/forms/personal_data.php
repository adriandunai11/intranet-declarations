<?= $this->extend('App\Modules\Declarations\Views\public\layout') ?>

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

<div class="hero-card">
    <div class="hero-content">
        <div class="eyebrow">Személyes adatok</div>
        <h1><?= esc($item->template_name ?? 'Személyes adatok nyilatkozata') ?></h1>

        <?php if ($person): ?>
            <div class="person-box">
                <div class="person-label">Beálló</div>
                <div class="person-name"><?= esc($person->fullName()) ?></div>
            </div>
        <?php endif; ?>

        <p class="lead">
            Kérjük, add meg a belépéshez szükséges személyes adatokat. Ezeket az adatokat a toborzó nem tölti ki helyetted.
        </p>

        <div class="notice notice-info">
            Az adóazonosító jelet és a TAJ számot pontosan, számjegyekkel add meg. Kötőjelet vagy szóközt nem szükséges írnod.
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

        <form method="post" action="<?= esc($itemUrl) ?>" class="public-form" novalidate>
            <?= csrf_field() ?>

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label for="birth_name">Születési név</label>
                    <input type="text" id="birth_name" name="birth_name"
                        value="<?= esc(old('birth_name', $data['birth_name'] ?? ($person->birth_name ?? ''))) ?>"
                        autocomplete="name" required>
                </div>

                <div class="form-group">
                    <label for="mother_name">Anyja neve</label>
                    <input type="text" id="mother_name" name="mother_name"
                        value="<?= esc(old('mother_name', $data['mother_name'] ?? ($person->mother_name ?? ''))) ?>"
                        autocomplete="off" required>
                </div>
            </div>

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label for="birth_place">Születési hely</label>
                    <input type="text" id="birth_place" name="birth_place"
                        value="<?= esc(old('birth_place', $data['birth_place'] ?? ($person->birth_place ?? ''))) ?>"
                        autocomplete="off" required>
                </div>

                <div class="form-group">
                    <label for="birth_date">Születési dátum</label>
                    <input type="date" id="birth_date" name="birth_date"
                        value="<?= esc(old('birth_date', $data['birth_date'] ?? ($person->birth_date ?? ''))) ?>"
                        required>
                </div>
            </div>

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label for="tax_number">Adóazonosító jel</label>
                    <input type="text" id="tax_number" name="tax_number"
                        value="<?= esc(old('tax_number', $data['tax_number'] ?? ($person->tax_number ?? ''))) ?>"
                        maxlength="10" inputmode="numeric" autocomplete="off" placeholder="10 számjegy" required>
                    <div class="form-help">10 számjegyből álló adóazonosító jel.</div>
                </div>

                <div class="form-group">
                    <label for="taj_number">TAJ szám</label>
                    <input type="text" id="taj_number" name="taj_number"
                        value="<?= esc(old('taj_number', $data['taj_number'] ?? ($person->taj_number ?? ''))) ?>"
                        maxlength="9" inputmode="numeric" autocomplete="off" placeholder="9 számjegy" required>
                    <div class="form-help">9 számjegyből álló TAJ szám.</div>
                </div>
            </div>

            <div class="form-group">
                <label for="phone">Telefonszám</label>
                <input type="text" id="phone" name="phone"
                    value="<?= esc(old('phone', $data['phone'] ?? ($person->phone ?? ''))) ?>"
                    autocomplete="tel" required>
            </div>

            <div class="form-group checkbox-group">
                <label>
                    <input type="checkbox" name="confirm_truth" value="1" required <?= old('confirm_truth', $data['confirm_truth'] ?? '') ? 'checked' : '' ?>>
                    <span>Kijelentem, hogy a megadott adatok a valóságnak megfelelnek.</span>
                </label>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Adatok beküldése</button>
                <a href="<?= esc($startUrl) ?>" class="btn btn-secondary">Vissza a dokumentumokhoz</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
