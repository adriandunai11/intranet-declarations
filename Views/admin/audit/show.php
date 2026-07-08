<?= $this->extend('admin/layout/default') ?>
<?= $this->section('content') ?>

<style>
    .audit-page-shell {
        max-width: 1180px;
        margin: 0 auto 2rem;
        padding: 0 1rem;
    }

    .audit-page-hero {
        border: 1px solid #dbe7df;
        border-radius: 8px;
        background: #fff;
        padding: 1.15rem;
        box-shadow: 0 16px 38px rgba(15, 23, 42, .05);
    }

    .audit-page-hero-main {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        align-items: flex-start;
    }

    .audit-page-kicker {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        margin-bottom: .35rem;
        color: #2f8f3a;
        font-size: .74rem;
        font-weight: 850;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .audit-page-title {
        margin: 0;
        color: #0f172a;
        font-size: 1.35rem;
        font-weight: 850;
        line-height: 1.2;
    }

    .audit-page-subtitle {
        margin-top: .25rem;
        color: #64748b;
        font-size: .92rem;
        font-weight: 650;
    }

    .audit-page-meta {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        margin-top: 1rem;
    }

    .audit-page-meta-item {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #f8fafc;
        padding: .52rem .68rem;
        min-width: 132px;
    }

    .audit-page-meta-item span {
        display: block;
        color: #64748b;
        font-size: .68rem;
        font-weight: 850;
        letter-spacing: .03em;
        text-transform: uppercase;
    }

    .audit-page-meta-item strong {
        display: block;
        margin-top: .1rem;
        color: #0f172a;
        font-size: .86rem;
        font-weight: 800;
        overflow-wrap: anywhere;
    }

    .audit-page-action {
        white-space: nowrap;
    }

    @media (max-width: 768px) {
        .audit-page-shell {
            padding: 0 .75rem;
        }

        .audit-page-hero-main {
            display: grid;
        }

        .audit-page-action {
            width: 100%;
        }
    }
</style>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><?= esc($heading ?? 'Előzmények') ?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="<?= url('/') ?>"><?= lang('App.home') ?></a></li>
                    <li class="breadcrumb-item"><a href="<?= url('declarations/persons') ?>">Nyilatkozatok</a></li>
                    <li class="breadcrumb-item active">Előzmények</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="audit-page-shell">
        <div class="audit-page-hero">
            <div class="audit-page-hero-main">
                <div>
                    <div class="audit-page-kicker">
                        <i class="fas fa-history"></i>
                        Napló
                    </div>

                    <h2 class="audit-page-title"><?= esc($subjectTitle ?? '-') ?></h2>
                    <div class="audit-page-subtitle"><?= esc($heading ?? 'Előzmények') ?></div>
                </div>

                <?php if (!empty($backUrl)): ?>
                    <a href="<?= esc($backUrl) ?>" class="btn btn-default btn-sm audit-page-action">
                        <i class="fas fa-arrow-left pr-1"></i> <?= esc($backLabel ?? 'Vissza') ?>
                    </a>
                <?php endif; ?>
            </div>

            <?php if (!empty($subjectMeta) && is_array($subjectMeta)): ?>
                <div class="audit-page-meta">
                    <?php foreach ($subjectMeta as $label => $value): ?>
                        <div class="audit-page-meta-item">
                            <span><?= esc($label) ?></span>
                            <strong><?= esc($value !== '' ? $value : '-') ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?= view('App\Modules\Declarations\Views\admin\partials\audit_table', [
            'auditLogs' => $auditLogs ?? [],
            'tableId' => $tableId ?? 'declarationAuditLogFeed',
            'emptyText' => $emptyText ?? 'Még nincs naplózott esemény.',
        ]) ?>
    </div>
</section>

<?= $this->endSection() ?>
