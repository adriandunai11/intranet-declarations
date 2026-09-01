<?= $this->extend('App\\Modules\\Declarations\\Views\\public\\layout') ?>

<?= $this->section('content') ?>

<?php
$summary = is_array($documentSummary ?? null) ? $documentSummary : [];
$meta = is_array($summary['meta'] ?? null) ? $summary['meta'] : [];
$rows = is_array($summary['rows'] ?? null) ? $summary['rows'] : [];
$tables = is_array($summary['tables'] ?? null) ? $summary['tables'] : [];
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

        <?php if (!empty($item->template_version)): ?>
            <div class="helper-card">
                <div class="helper-title">Verzió</div>
                <p><?= esc($item->template_version) ?></p>
            </div>
        <?php endif; ?>
    </aside>

    <main class="submitted-main">
        <section class="submitted-card">
            <div class="submitted-head">
                <div>
                    <div class="eyebrow"><?= esc($summary['subtitle'] ?? 'Online kitöltési összesítő') ?></div>
                    <h2>Nyilatkozat előnézete</h2>
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

                <?php if (empty($rows) && empty($tables)): ?>
                    <div class="empty-state">Ehhez a nyilatkozathoz nincs megjeleníthető kitöltött adat.</div>
                <?php elseif (!empty($rows)): ?>
                    <dl class="data-review-list">
                        <?php foreach ($rows as $label => $value): ?>
                            <div>
                                <dt><?= esc($label) ?></dt>
                                <dd><?= esc($value !== '' ? $value : '-') ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                <?php endif; ?>

                <?php foreach ($tables as $table): ?>
                    <?php
                    $tableColumns = is_array($table['columns'] ?? null) ? $table['columns'] : [];
                    $tableRows = is_array($table['rows'] ?? null) ? $table['rows'] : [];
                    ?>
                    <?php if (!empty($tableColumns) && !empty($tableRows)): ?>
                        <div class="data-review-title mt-public"><?= esc($table['title'] ?? 'Táblázat') ?></div>
                        <div class="document-preview-table-wrap">
                            <table class="document-preview-table">
                                <thead>
                                    <tr>
                                        <?php foreach ($tableColumns as $column): ?>
                                            <th><?= esc($column) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tableRows as $tableRow): ?>
                                        <tr>
                                            <?php foreach ($tableColumns as $columnIndex => $column): ?>
                                                <td data-label="<?= esc($column) ?>"><?= esc($tableRow[$columnIndex] ?? '-') ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</div>

<?= $this->endSection() ?>
