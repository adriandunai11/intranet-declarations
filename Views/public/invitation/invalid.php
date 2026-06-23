<?= $this->extend('App\Modules\Declarations\Views\public\layout') ?>

<?= $this->section('content') ?>

<div class="hero-card">
    <div class="hero-content">
        <div class="eyebrow">Hozzáférési hiba</div>
        <h1>Érvénytelen vagy lejárt meghívó</h1>

        <p class="lead">
            <?= esc($message ?? 'A megnyitott meghívó link nem érvényes, lejárt vagy már nem használható.') ?>
        </p>

        <div class="notice notice-danger">
            Kérjük, ellenőrizze, hogy a teljes linket nyitotta-e meg. Ha továbbra sem működik,
            kérjen új meghívó linket a kapcsolattartójától vagy a munkaügytől.
        </div>
    </div>
</div>

<?= $this->endSection() ?>