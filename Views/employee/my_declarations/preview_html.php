<?= $this->extend('admin/layout/default') ?>
<?= $this->section('content') ?>

<?php
$summary = is_array($documentSummary ?? null) ? $documentSummary : [];
$meta = is_array($summary['meta'] ?? null) ? $summary['meta'] : [];
$rows = is_array($summary['rows'] ?? null) ? $summary['rows'] : [];
$tables = is_array($summary['tables'] ?? null) ? $summary['tables'] : [];
?>

<style>
    .own-preview-shell {
        max-width: 1120px;
        margin: 0 auto 2rem;
    }

    .own-preview-panel {
        border: 1px solid #dbe7df;
        border-radius: 8px;
        background: #fff;
        padding: 1rem;
        margin-bottom: 1rem;
        box-shadow: 0 14px 34px rgba(15, 23, 42, .05);
    }

    .own-preview-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .65rem;
    }

    .own-preview-field {
        border: 1px solid #edf2f7;
        border-radius: 8px;
        background: #f8fafc;
        padding: .65rem .72rem;
        min-width: 0;
    }

    .own-preview-field span {
        display: block;
        color: #64748b;
        font-size: .72rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .own-preview-field strong {
        display: block;
        margin-top: .16rem;
        color: #111827;
        overflow-wrap: anywhere;
    }

    .own-preview-table-wrap {
        width: 100%;
        overflow-x: auto;
        border: 1px solid #dbe7df;
        border-radius: 8px;
    }

    .own-preview-table {
        width: 100%;
        min-width: 720px;
        border-collapse: collapse;
    }

    .own-preview-table th,
    .own-preview-table td {
        border-bottom: 1px solid #e5ece7;
        border-right: 1px solid #e5ece7;
        padding: .58rem .65rem;
        vertical-align: top;
    }

    .own-preview-table th {
        background: #f1f8ef;
        color: #315b31;
    }

    @media (max-width: 768px) {
        .own-preview-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-7">
                <h1>Nyilatkozat előnézete</h1>
            </div>
            <div class="col-sm-5">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="<?= url('/') ?>"><?= lang('App.home') ?></a></li>
                    <li class="breadcrumb-item"><a href="<?= url('declarations/my-declarations') ?>">Nyilatkozataim</a></li>
                    <li class="breadcrumb-item active">Előnézet</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="own-preview-shell">
        <div class="own-preview-panel">
            <div class="d-flex flex-wrap justify-content-between align-items-start">
                <div>
                    <div class="text-muted small text-uppercase font-weight-bold">
                        <?= esc($summary['subtitle'] ?? 'Online kitöltési összesítő') ?>
                    </div>
                    <h2 class="mb-1"><?= esc($summary['title'] ?? ($item->template_name ?? 'Nyilatkozat')) ?></h2>
                    <div class="text-muted">
                        Verzió: <?= esc($item->template_version ?? '-') ?>
                    </div>
                </div>

                <?php if (!empty($backUrl)): ?>
                    <a href="<?= esc($backUrl) ?>" class="btn btn-default btn-sm">
                        <i class="fas fa-arrow-left pr-1"></i> Vissza a csomaghoz
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($warning)): ?>
            <div class="alert alert-warning"><?= esc($warning) ?></div>
        <?php endif; ?>

        <?php if (!empty($meta)): ?>
            <div class="own-preview-panel">
                <h3>Azonosító adatok</h3>
                <div class="own-preview-grid">
                    <?php foreach ($meta as $label => $value): ?>
                        <div class="own-preview-field">
                            <span><?= esc($label) ?></span>
                            <strong><?= esc($value !== '' ? $value : '-') ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="own-preview-panel">
            <h3>Kitöltött adatok</h3>

            <?php if (empty($rows) && empty($tables)): ?>
                <p class="text-muted mb-0">Ehhez a nyilatkozathoz nincs megjeleníthető kitöltött adat.</p>
            <?php endif; ?>

            <?php if (!empty($rows)): ?>
                <div class="own-preview-grid">
                    <?php foreach ($rows as $label => $value): ?>
                        <div class="own-preview-field">
                            <span><?= esc($label) ?></span>
                            <strong><?= esc($value !== '' ? $value : '-') ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php foreach ($tables as $table): ?>
                <?php
                $tableColumns = is_array($table['columns'] ?? null) ? $table['columns'] : [];
                $tableRows = is_array($table['rows'] ?? null) ? $table['rows'] : [];
                ?>
                <?php if (!empty($tableColumns) && !empty($tableRows)): ?>
                    <h3 class="mt-3"><?= esc($table['title'] ?? 'Táblázat') ?></h3>
                    <div class="own-preview-table-wrap">
                        <table class="own-preview-table">
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
                                            <td><?= esc($tableRow[$columnIndex] ?? '-') ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?= $this->endSection() ?>
