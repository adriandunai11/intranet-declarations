<?= $this->extend('App\\Modules\\Declarations\\Views\\public\\layout') ?>

<?= $this->section('content') ?>

<div class="access-layout access-layout-narrow">
    <aside class="access-side access-side-danger">
        <div class="eyebrow">Hozzáférési hiba</div>
        <h1>Érvénytelen vagy lejárt meghívó</h1>
        <p><?= esc($message ?? 'A megnyitott meghívó link nem érvényes, lejárt vagy már nem használható.') ?></p>
    </aside>

    <main class="access-main">
        <section class="content-card">
            <div class="notice notice-danger">
                Kérjük, ellenőrizze, hogy a teljes linket nyitotta-e meg. Ha továbbra sem működik, kérjen új meghívó linket a kapcsolattartójától vagy a munkaügytől.
            </div>

            <div class="helper-card helper-card-flat">
                <div class="helper-title">Mit tehet most?</div>
                <ul>
                    <li>Nyissa meg újra az e-mailben kapott teljes linket.</li>
                    <li>Ellenőrizze, hogy nem egy régi linkre kattintott-e.</li>
                    <li>Kérjen új meghívót a kapcsolattartójától.</li>
                </ul>
            </div>
        </section>
    </main>
</div>

<?= $this->endSection() ?>
