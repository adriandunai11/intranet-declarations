<?= $this->extend('App\Modules\Declarations\Views\public\layout') ?>

<?= $this->section('content') ?>

<div class="hero-card">
    <div class="hero-content">
        <div class="eyebrow">Dokumentum beküldve</div>
        <h1><?= esc($item->template_name ?? 'Nyilatkozat') ?></h1>

        <div class="notice notice-success">
            Ezt a dokumentumot már beküldte. Ha javításra lesz szükség, e-mailben értesítjük, és a dokumentum újra megnyitható lesz.
        </div>

        <?php if ($submission && !empty($submission->submitted_at)): ?>
            <p class="lead">Beküldés ideje: <strong><?= esc($submission->submitted_at) ?></strong></p>
        <?php endif; ?>

        <?php if (!empty($displayRows)): ?>
            <div class="info-card">
                <div class="info-title">Beküldött adatok</div>
                <dl class="summary-list">
                    <?php foreach ($displayRows as $label => $value): ?>
                        <dt><?= esc($label) ?></dt>
                        <dd><?= esc($value !== '' ? $value : '-') ?></dd>
                    <?php endforeach; ?>
                </dl>
            </div>
        <?php endif; ?>

        <div class="actions">
            <a href="<?= esc($startUrl) ?>" class="btn btn-secondary">Vissza a dokumentumokhoz</a>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
