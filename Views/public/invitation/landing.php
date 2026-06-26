<?= $this->extend('App\\Modules\\Declarations\\Views\\public\\layout') ?>

<?= $this->section('content') ?>

<div class="access-layout access-layout-narrow">
    <aside class="access-side">
        <div class="eyebrow">Online nyilatkozatkitöltés</div>
        <h1>Miell Group nyilatkozatok</h1>
        <p>A kitöltés az e-mailben kapott egyedi meghívó linkkel indítható.</p>
    </aside>

    <main class="access-main">
        <section class="content-card">
            <div class="overview-grid overview-grid-stack">
                <div class="overview-card">
                    <div class="overview-label">1. lépés</div>
                    <div class="overview-text">Nyissa meg az e-mailben kapott linket.</div>
                </div>
                <div class="overview-card">
                    <div class="overview-label">2. lépés</div>
                    <div class="overview-text">Adja meg az Antra azonosítót.</div>
                </div>
                <div class="overview-card">
                    <div class="overview-label">3. lépés</div>
                    <div class="overview-text">Töltse ki és ellenőrizze a nyilatkozatokat.</div>
                </div>
            </div>

            <div class="notice notice-warning">
                Ha nem kapott meghívó linket, vagy a link már lejárt, kérjen új meghívót a kapcsolattartójától vagy a munkaügytől.
            </div>
        </section>
    </main>
</div>

<?= $this->endSection() ?>
