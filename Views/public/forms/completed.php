<?= $this->extend('App\\Modules\\Declarations\\Views\\public\\layout') ?>

<?= $this->section('content') ?>

<?php
$itemStatus = (string) ($item->status ?? '');
$isAccepted = $itemStatus === 'accepted';

$pageEyebrow = $isAccepted ? 'Elfogadott dokumentum' : 'Mentett dokumentum';
$pageTitle = $isAccepted ? 'Elfogadott adatok' : 'Mentett adatok ellenőrzése';
$pageLead = $isAccepted
    ? 'Ezek az adatok kerültek elfogadásra ehhez a dokumentumhoz.'
    : 'Ezek az adatok jelenleg mentve vannak ehhez a dokumentumhoz. A végleges beküldés a csomag összesítő oldalán történik.';
$statusText = $isAccepted ? 'Elfogadva' : 'Mentve';
$statusSubText = $submission && !empty($submission->submitted_at)
    ? (string) $submission->submitted_at
    : 'A végleges beküldésig ellenőrizhető.';
$helperText = $isAccepted
    ? 'A dokumentum elfogadva. Ha mégis módosítás szükséges, azt a kapcsolattartó jelzi.'
    : 'Ha módosítani szeretné az adatokat, lépjen vissza az összesítőhöz, és nyissa meg újra a dokumentumot.';
?>

<div class="submitted-layout">
    <aside class="submitted-side">
        <a href="<?= esc($startUrl) ?>" class="back-link">← Vissza az összesítőhöz</a>

        <div class="eyebrow"><?= esc($pageEyebrow) ?></div>
        <h1><?= esc($item->template_name ?? 'Nyilatkozat') ?></h1>

        <div class="submitted-status-card">
            <span class="submitted-status-dot"></span>
            <div>
                <strong><?= esc($statusText) ?></strong>
                <span><?= esc($statusSubText) ?></span>
            </div>
        </div>

        <p class="submitted-help">
            <?= esc($helperText) ?>
        </p>
    </aside>

    <main class="submitted-main">
        <section class="submitted-card">
            <div class="submitted-head">
                <div>
                    <div class="eyebrow">Adatok ellenőrzése</div>
                    <h2><?= esc($pageTitle) ?></h2>
                    <p><?= esc($pageLead) ?></p>
                </div>
                <span class="badge <?= $isAccepted ? 'badge-completed' : 'badge-review' ?>"><?= esc($statusText) ?></span>
            </div>

            <?php if (!empty($displayRows)): ?>
                <div class="data-review-card">
                    <div class="data-review-title"><?= esc($item->template_name ?? 'Nyilatkozat') ?></div>
                    <dl class="data-review-list">
                        <?php foreach ($displayRows as $label => $value): ?>
                            <div>
                                <dt><?= esc($label) ?></dt>
                                <dd><?= esc($value !== '' ? $value : '-') ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                </div>
            <?php else: ?>
                <div class="empty-state">Ehhez a dokumentumhoz nincs megjeleníthető adat.</div>
            <?php endif; ?>
        </section>
    </main>
</div>

<?= $this->endSection() ?>
