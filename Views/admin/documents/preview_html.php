<?= $this->extend('admin/layout/default') ?>
<?= $this->section('content') ?>

<?php
$summary = is_array($documentSummary ?? null) ? $documentSummary : [];
$meta = is_array($summary['meta'] ?? null) ? $summary['meta'] : [];
$rows = is_array($summary['rows'] ?? null) ? $summary['rows'] : [];
?>

<style>
    .declaration-preview-shell {
        max-width: 1120px;
        margin: 0 auto 2rem;
    }

    .declaration-preview-hero,
    .declaration-preview-panel {
        border: 1px solid #dbe7df;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 14px 34px rgba(15, 23, 42, .05);
    }

    .declaration-preview-hero {
        padding: 1.1rem;
        margin-bottom: 1rem;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
    }

    .declaration-preview-kicker {
        display: inline-flex;
        border-radius: 999px;
        background: #eaf8e8;
        color: #2f8f31;
        padding: .32rem .55rem;
        font-size: .72rem;
        font-weight: 850;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .declaration-preview-title {
        margin: .45rem 0 .25rem;
        color: #0f172a;
        font-size: 1.45rem;
        font-weight: 850;
        line-height: 1.2;
    }

    .declaration-preview-subtitle {
        color: #64748b;
        font-size: .9rem;
        font-weight: 650;
    }

    .declaration-preview-panel {
        padding: 1rem;
        margin-bottom: 1rem;
    }

    .declaration-preview-panel h3 {
        margin: 0 0 .75rem;
        color: #0f172a;
        font-size: 1.05rem;
        font-weight: 850;
    }

    .declaration-preview-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .6rem;
    }

    .declaration-preview-field {
        border: 1px solid #edf2f7;
        border-radius: 8px;
        background: #f8fafc;
        padding: .65rem .72rem;
        min-width: 0;
    }

    .declaration-preview-field span {
        display: block;
        color: #64748b;
        font-size: .72rem;
        font-weight: 850;
        letter-spacing: .03em;
        text-transform: uppercase;
    }

    .declaration-preview-field strong {
        display: block;
        margin-top: .16rem;
        color: #111827;
        font-size: .9rem;
        font-weight: 780;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    @media (max-width: 768px) {
        .declaration-preview-hero {
            display: grid;
        }

        .declaration-preview-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>Dokumentum előnézet</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="<?= url('/') ?>"><?= lang('App.home') ?></a></li>
                    <li class="breadcrumb-item"><a href="<?= url('declarations/persons') ?>">Nyilatkozatok</a></li>
                    <li class="breadcrumb-item active">Előnézet</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="declaration-preview-shell">
        <div class="declaration-preview-hero">
            <div>
                <span class="declaration-preview-kicker"><?= esc($summary['subtitle'] ?? 'Online kitöltési összesítő') ?></span>
                <h2 class="declaration-preview-title"><?= esc($summary['title'] ?? ($item->template_name ?? 'Nyilatkozat')) ?></h2>
                <div class="declaration-preview-subtitle">
                    <?php if (!empty($item->template_version)): ?>
                        Verzió: <?= esc($item->template_version) ?>
                    <?php else: ?>
                        Online kitöltésből készült előnézet
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($backUrl)): ?>
                <a href="<?= esc($backUrl) ?>" class="btn btn-default btn-sm">
                    <i class="fas fa-arrow-left pr-1"></i> Vissza a csomaghoz
                </a>
            <?php endif; ?>
        </div>

        <?php if (!empty($warning)): ?>
            <div class="alert alert-warning">
                <?= esc($warning) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($summary['note'])): ?>
            <div class="alert alert-info">
                <?= esc($summary['note']) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($meta)): ?>
            <div class="declaration-preview-panel">
                <h3>Azonosító adatok</h3>
                <div class="declaration-preview-grid">
                    <?php foreach ($meta as $label => $value): ?>
                        <div class="declaration-preview-field">
                            <span><?= esc($label) ?></span>
                            <strong><?= esc($value !== '' ? $value : '-') ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="declaration-preview-panel">
            <h3>Kitöltött adatok</h3>

            <?php if (empty($rows)): ?>
                <p class="text-muted mb-0">Ehhez a dokumentumhoz nincs megjeleníthető kitöltött adat.</p>
            <?php else: ?>
                <div class="declaration-preview-grid">
                    <?php foreach ($rows as $label => $value): ?>
                        <div class="declaration-preview-field">
                            <span><?= esc($label) ?></span>
                            <strong><?= esc($value !== '' ? $value : '-') ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?= $this->endSection() ?>
