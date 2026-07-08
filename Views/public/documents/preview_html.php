<?= $this->extend('App\\Modules\\Declarations\\Views\\public\\layout') ?>

<?= $this->section('content') ?>

<?php
$summary = is_array($documentSummary ?? null) ? $documentSummary : [];
$meta = is_array($summary['meta'] ?? null) ? $summary['meta'] : [];
$rows = is_array($summary['rows'] ?? null) ? $summary['rows'] : [];
?>

<div class="submitted-layout document-preview-layout">
    <aside class="submitted-side">
        <a href="<?= esc($backUrl ?? '#') ?>" class="back-link">← Vissza az összesítőhöz</a>

        <div class="eyebrow">Előnézet</div>
        <h1><?= esc($summary['title'] ?? ($item->template_name ?? 'Nyilatkozat')) ?></h1>

        <?php if (!empty($warning)): ?>
            <div class="notice notice-warning">
                <?= esc($warning) ?>
            </div>
        <?php endif; ?>

        <div class="helper-card">
            <div class="helper-title">Sablon</div>
            <p>
                <?= esc($templateFile ?? '-') ?><br>
                <?php if (!empty($item->template_version)): ?>
                    Verzió: <?= esc($item->template_version) ?>
                <?php endif; ?>
            </p>
        </div>
    </aside>

    <main class="submitted-main">
        <section class="submitted-card">
            <div class="submitted-head">
                <div>
                    <div class="eyebrow"><?= esc($summary['subtitle'] ?? 'Online kitöltési összesítő') ?></div>
                    <h2>Generált dokumentum adatai</h2>
                    <p><?= esc($summary['note'] ?? 'Az előnézet a mentett online kitöltés alapján készült.') ?></p>
                </div>
            </div>

            <?php if (!empty($meta)): ?>
                <div class="data-review-card">
                    <div class="data-review-title">Azonosító adatok</div>
                    <dl class="data-review-list">
                        <?php foreach ($meta as $label => $value): ?>
                            <div>
                                <dt><?= esc($label) ?></dt>
                                <dd><?= esc($value !== '' ? $value : '-') ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                </div>
            <?php endif; ?>

            <div class="data-review-card mt-public">
                <div class="data-review-title">Kitöltött adatok</div>

                <?php if (empty($rows)): ?>
                    <div class="empty-state">Ehhez a dokumentumhoz nincs megjeleníthető kitöltött adat.</div>
                <?php else: ?>
                    <dl class="data-review-list">
                        <?php foreach ($rows as $label => $value): ?>
                            <div>
                                <dt><?= esc($label) ?></dt>
                                <dd><?= esc($value !== '' ? $value : '-') ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>

<?= $this->endSection() ?>
