<?= $this->extend('App\\Modules\\Declarations\\Views\\public\\layout') ?>

<?= $this->section('content') ?>

<div class="access-layout">
    <aside class="access-side">
        <div class="eyebrow">Hozzáférés ellenőrzése</div>
        <h1>Antra azonosító</h1>
        <p>
            A dokumentumok megnyitásához adja meg a toborzó által rögzített Antra azonosítót.
        </p>

        <ul class="access-checklist">
            <li>Az azonosítót pontosan írja be.</li>
            <li>Sikeres ellenőrzés után megnyílik a kitöltés.</li>
            <li>A link továbbra is csak a lejárati időig használható.</li>
        </ul>
    </aside>

    <main class="access-main">
        <section class="content-card access-card">
            <div class="section-heading">
                <div>
                    <h2>Azonosítás</h2>
                    <p class="section-note">Ez egy plusz biztonsági lépés, hogy csak az érintett személy férjen hozzá a csomaghoz.</p>
                </div>
            </div>

            <form method="post" action="<?= esc($verifyUrl) ?>" class="public-form form-panel" data-live-validation novalidate>
                <?= csrf_field() ?>

                <div class="notice notice-danger js-client-errors" hidden aria-live="polite"></div>

                <section class="form-section">
                    <div class="form-group">
                        <label for="antra_id">Antra azonosító</label>
                        <input type="text" id="antra_id" name="antra_id"
                            value="<?= esc(old('antra_id', '')) ?>"
                            autocomplete="off" data-validate="required|min:2" data-label="Antra azonosító" required autofocus>
                    </div>

                    <div class="form-actions-row form-actions-row-end">
                        <button type="submit" class="btn btn-primary">Ellenőrzés</button>
                    </div>
                </section>
            </form>
        </section>
    </main>
</div>

<?= $this->endSection() ?>
