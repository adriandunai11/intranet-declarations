<?= $this->extend('App\Modules\Declarations\Views\public\layout') ?>

<?= $this->section('content') ?>

<div class="hero-card">
    <div class="hero-content">
        <div class="eyebrow">Hozzáférés ellenőrzése</div>
        <h1>Antra azonosító megadása</h1>

        <p class="lead">
            A dokumentumok megnyitásához add meg az Antra azonosítódat.
        </p>

        <form method="post" action="<?= esc($verifyUrl) ?>" class="public-form" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="antra_id">Antra azonosító</label>
                <input type="text" id="antra_id" name="antra_id"
                    value="<?= esc(old('antra_id', '')) ?>"
                    autocomplete="off" required autofocus>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Tovább</button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
