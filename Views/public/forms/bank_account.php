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
        <div class="eyebrow">Nyilatkozat kitöltése</div>
        <h1><?= esc($item->template_name ?? 'Bankszámlaszám nyilatkozat') ?></h1>

        <?php if ($person): ?>
            <div class="person-box">
                <div class="person-label">Beálló</div>
                <div class="person-name"><?= esc($person->fullName()) ?></div>
            </div>
        <?php endif; ?>

        <p class="lead">Kérjük, add meg azt a bankszámlaszámot, amelyre a munkabéred utalását kéred.</p>

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

            <div class="form-group">
                <label for="account_holder">Számlatulajdonos neve</label>
                <input type="text" id="account_holder" name="account_holder"
                    value="<?= esc(old('account_holder', $data['account_holder'] ?? ($person ? $person->fullName() : ''))) ?>"
                    autocomplete="name" required>
            </div>

            <div class="form-group">
                <label for="bank_name">Bank neve</label>
                <input type="text" id="bank_name" name="bank_name"
                    value="<?= esc(old('bank_name', $data['bank_name'] ?? '')) ?>" autocomplete="organization" required>
            </div>

            <div class="form-group">
                <label for="bank_account_number">Bankszámlaszám</label>
                <input type="text" id="bank_account_number" name="bank_account_number"
                    value="<?= esc(old('bank_account_number', $data['bank_account_number'] ?? '')) ?>"
                    placeholder="12345678-12345678-12345678" inputmode="numeric" autocomplete="off" required>
                <div class="form-help">Magyar bankszámlaszám esetén 2×8 vagy 3×8 számjegy.</div>
            </div>

            <div class="form-group checkbox-group">
                <label>
                    <input type="checkbox" name="confirm_truth" value="1" required <?= old('confirm_truth', $data['confirm_truth'] ?? '') ? 'checked' : '' ?>>
                    <span>Kijelentem, hogy a megadott adatok a valóságnak megfelelnek.</span>
                </label>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Dokumentum beküldése</button>
                <a href="<?= esc($startUrl) ?>" class="btn btn-secondary">Vissza a dokumentumokhoz</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>