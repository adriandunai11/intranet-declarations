<?= $this->extend('App\Modules\Declarations\Views\public\layout') ?>

<?= $this->section('content') ?>

<div class="hero-card">
    <div class="hero-content">
        <div class="grid">
            <div>
                <div class="eyebrow">Online nyilatkozatkitöltés</div>
                <h1>Üdvözlünk a Miell nyilatkozatkitöltő felületén</h1>

                <p class="lead">
                    Itt tudod biztonságosan kitölteni a belépéshez vagy munkaviszonyhoz kapcsolódó nyilatkozataidat.
                    A folytatáshoz az e-mailben kapott egyedi meghívó linkre lesz szükséged.
                </p>

                <div class="notice notice-warning">
                    Ha nem kaptál meghívó linket, vagy a link már lejárt, kérjük, vedd fel a kapcsolatot a munkaüggyel.
                </div>
            </div>

            <div>
                <div class="info-card">
                    <div class="info-title">Egyszerű kitöltés</div>
                    <p class="info-text">A rendszer végigvezet a szükséges nyilatkozatokon, és jelzi, melyik dokumentum van még hátra.</p>
                </div>

                <div class="info-card">
                    <div class="info-title">Mobilról is használható</div>
                    <p class="info-text">A felület telefonon, tableten és számítógépen is kényelmesen használható.</p>
                </div>

                <div class="info-card">
                    <div class="info-title">Biztonságos hozzáférés</div>
                    <p class="info-text">A nyilatkozatcsomag kizárólag az egyedi, időkorlátos meghívó linken keresztül érhető el.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
