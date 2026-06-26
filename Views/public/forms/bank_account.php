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
        <div class="eyebrow">Bankszámla</div>
        <h1><?= esc($item->template_name ?? 'Bankszámlaszám nyilatkozat') ?></h1>
        <p>
            A bérutaláshoz használt bankszámlát adja meg. A bankszámlaszám formátumát kitöltés közben ellenőrizzük.
        </p>

        <?php if ($person): ?>
            <div class="person-chip person-chip-sidebar">
                <span>Kitöltő</span>
                <strong><?= esc($person->fullName()) ?></strong>
            </div>
        <?php endif; ?>

        <div class="helper-card">
            <div class="helper-title">Szükséges adatok</div>
            <ul>
                <li>Számlatulajdonos neve</li>
                <li>Bank neve</li>
                <li>16 vagy 24 számjegyű bankszámlaszám</li>
            </ul>
        </div>

        <a href="<?= esc($startUrl) ?>" class="btn btn-secondary btn-block">Vissza az összesítőhöz</a>
    </aside>

    <main class="form-main-panel">
        <section class="content-card">
            <div class="section-heading">
                <div>
                    <h2>Bérutalási bankszámla</h2>
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

            <form method="post" action="<?= esc($itemUrl) ?>" class="public-form form-panel" id="bankAccountForm" data-live-validation novalidate>
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
                        <h2 class="form-section-title">Számlaadatok</h2>
                        <p class="section-note">Olyan bankszámlaszámot adjon meg, amelyre a munkabér utalható.</p>
                    </div>

                    <div class="form-grid form-grid-2">
                        <div class="form-group">
                            <label for="account_holder">Számlatulajdonos neve</label>
                            <input type="text" id="account_holder" name="account_holder"
                                value="<?= esc(old('account_holder', $data['account_holder'] ?? ($person ? $person->fullName() : ''))) ?>"
                                autocomplete="name" data-validate="required|min:3" data-label="Számlatulajdonos neve" required>
                        </div>

                        <div class="form-group">
                            <label for="bank_name">Bank neve</label>
                            <input type="text" id="bank_name" name="bank_name"
                                value="<?= esc(old('bank_name', $data['bank_name'] ?? '')) ?>"
                                autocomplete="organization" data-validate="required|min:2" data-label="Bank neve" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="bank_account_number">Bankszámlaszám</label>
                        <input type="text" id="bank_account_number" name="bank_account_number"
                            value="<?= esc(old('bank_account_number', $data['bank_account_number'] ?? '')) ?>"
                            placeholder="12345678-12345678-12345678" inputmode="numeric" autocomplete="off" maxlength="26"
                            data-format="bank_account" data-validate="required|bank_account" data-label="Bankszámlaszám" required>
                        <div class="form-help">16 vagy 24 számjegy. A mező automatikusan tagolja a számot.</div>
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
