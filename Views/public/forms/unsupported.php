<?= $this->extend('App\Modules\Declarations\Views\public\layout') ?>

<?= $this->section('content') ?>

<div class="hero-card">
    <div class="hero-content">
        <div class="eyebrow">Nyilatkozat</div>
        <h1><?= esc($item->template_name ?? 'Nyilatkozat') ?></h1>

        <div class="notice notice-warning">Ez a nyilatkozat még nincs bekötve online kitöltésre.</div>

        <div class="actions">
            <a href="<?= esc($startUrl) ?>" class="btn btn-secondary">Vissza a nyilatkozatokhoz</a>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
