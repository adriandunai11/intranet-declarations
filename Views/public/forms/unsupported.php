<?= $this->extend('App\\Modules\\Declarations\\Views\\public\\layout') ?>

<?= $this->section('content') ?>

<div class="access-layout access-layout-narrow">
    <aside class="access-side">
        <div class="eyebrow">Előkészítés alatt</div>
        <h1>Ez a nyilatkozat még nem tölthető ki online</h1>
        <p>A dokumentum online kitöltése még előkészítés alatt áll.</p>
    </aside>

    <main class="access-main">
        <section class="content-card">
            <div class="notice notice-info">
                Térjen vissza az összesítőhöz, és folytassa a többi elérhető dokumentummal.
            </div>

            <div class="form-actions-row form-actions-row-end">
                <a href="<?= esc($startUrl) ?>" class="btn btn-secondary">Vissza az összesítőhöz</a>
            </div>
        </section>
    </main>
</div>

<?= $this->endSection() ?>
