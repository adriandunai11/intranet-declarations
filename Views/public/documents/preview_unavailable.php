<?= $this->extend('App\\Modules\\Declarations\\Views\\public\\layout') ?>

<?= $this->section('content') ?>

<div class="access-layout access-layout-narrow">
    <aside class="access-side access-side-danger">
        <div class="eyebrow">Dokumentum előnézet</div>
        <h1>Az előnézet most nem érhető el</h1>
        <p>
            A nyilatkozat adatai ettől még mentve lehetnek. Lépjen vissza az összesítőhöz, és ellenőrizze az ott megjelenő adatokat.
        </p>
    </aside>

    <main class="access-main">
        <section class="content-card access-card">
            <div class="notice notice-warning">
                <?= esc($message ?? 'A dokumentum előnézet generálása nem sikerült.') ?>
            </div>

            <div class="actions">
                <a href="<?= esc($backUrl ?? '/') ?>" class="btn btn-primary">Vissza az összesítőhöz</a>
            </div>
        </section>
    </main>
</div>

<?= $this->endSection() ?>
