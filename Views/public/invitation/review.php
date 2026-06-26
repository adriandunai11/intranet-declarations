<?= $this->extend('App\\Modules\\Declarations\\Views\\public\\layout') ?>

<?= $this->section('content') ?>

<?php
$items = $items ?? [];
$summaryRowsByItemId = $summaryRowsByItemId ?? [];

$previewUrlFor = static function (object $item) use ($startUrl): string {
    return rtrim((string) $startUrl, '/') . '/item/' . (int) $item->id . '/preview';
};
?>

<div class="review-layout">
    <aside class="review-side">
        <a href="<?= esc($startUrl) ?>" class="back-link">← Vissza az áttekintéshez</a>

        <div class="eyebrow">Végső ellenőrzés</div>
        <h1>Ellenőrzés és beküldés</h1>

        <?php if ($person): ?>
            <div class="person-chip person-chip-sidebar">
                <span>Kitöltő</span>
                <strong><?= esc($person->fullName()) ?></strong>
            </div>
        <?php endif; ?>

        <div class="helper-card">
            <div class="helper-title">Mit ellenőrizzen?</div>
            <ul>
                <li>Személyes adatok helyessége</li>
                <li>TAJ és adóazonosító</li>
                <li>Bankszámla és egyéb nyilatkozatadatok</li>
                <li>PDF előnézet, ahol elérhető</li>
            </ul>
        </div>
    </aside>

    <main class="review-main">
        <section class="page-title-panel review-title-panel">
            <div>
                <div class="eyebrow">Beküldés előtt</div>
                <h1>Összes kitöltött adat</h1>
                <p class="lead">
                    Itt egyben látható minden mentett nyilatkozat. A végleges beküldés után a kitöltő már nem tudja módosítani az adatokat, csak admini visszanyitással.
                </p>
            </div>
            <span class="status-pill">Ellenőrzés</span>
        </section>

        <section class="content-card review-documents-card">
            <?php if (empty($items)): ?>
                <div class="notice notice-warning">Ehhez a csomaghoz jelenleg nincs ellenőrizhető dokumentum.</div>
            <?php else: ?>
                <div class="review-document-list">
                    <?php foreach ($items as $item): ?>
                        <?php
                        $summaryRows = $summaryRowsByItemId[(int) $item->id] ?? [];
                        $canPreview = !empty($summaryRows) && (string) ($item->template_code ?? '') !== 'personal_data_statement';
                        $itemUrl = $itemUrls[(int) $item->id] ?? '#';
                        ?>

                        <article class="review-document-card">
                            <header class="review-document-head">
                                <div>
                                    <h2><?= esc($item->template_name ?: ('Dokumentum #' . $item->template_id)) ?></h2>
                                    <p>
                                        <?= esc($item->template_category ?: 'Dokumentum') ?>
                                        <?php if (!empty($item->template_tax_year)): ?> · Adóév: <?= esc($item->template_tax_year) ?><?php endif; ?>
                                        <?php if (!empty($item->template_version)): ?> · Verzió: <?= esc($item->template_version) ?><?php endif; ?>
                                    </p>
                                </div>
                                <div class="review-document-actions">
                                    <a href="<?= esc($itemUrl) ?>" class="btn btn-ghost btn-sm">Módosítás</a>
                                    <?php if ($canPreview): ?>
                                        <a href="<?= esc($previewUrlFor($item)) ?>" class="btn btn-secondary btn-sm" target="_blank" rel="noopener">PDF előnézet</a>
                                    <?php endif; ?>
                                </div>
                            </header>

                            <?php if (empty($summaryRows)): ?>
                                <div class="empty-state">Ehhez a dokumentumhoz nincs megjeleníthető mentett adat.</div>
                            <?php else: ?>
                                <dl class="review-data-list">
                                    <?php foreach ($summaryRows as $label => $value): ?>
                                        <div>
                                            <dt><?= esc($label) ?></dt>
                                            <dd><?= esc($value !== '' ? $value : '-') ?></dd>
                                        </div>
                                    <?php endforeach; ?>
                                </dl>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="content-card final-submit-card">
            <div>
                <div class="eyebrow">Végleges beküldés</div>
                <h2>Adatok lezárása</h2>
                <p class="section-note">
                    A beküldés után a nyilatkozatcsomag ellenőrzésre kerül. A kitöltő ezután már nem tudja önállóan módosítani az adatokat.
                </p>
            </div>

            <form method="post" action="<?= esc($finalizeUrl) ?>" class="public-form review-final-form" data-live-validation novalidate>
                <?= csrf_field() ?>

                <div class="form-group checkbox-group">
                    <label>
                        <input type="checkbox" name="review_confirm" value="1"
                            data-validate="required"
                            data-label="Adatok ellenőrzése"
                            data-error-required="A végleges beküldéshez jelölje be, hogy ellenőrizte a megadott adatokat."
                            required>
                        <span>Ellenőriztem a megadott adatokat, és véglegesen be szeretném küldeni a nyilatkozatcsomagot.</span>
                    </label>
                </div>

                <div class="notice notice-danger js-client-errors" hidden aria-live="polite"></div>

                <div class="form-actions-row">
                    <a href="<?= esc($startUrl) ?>" class="btn btn-secondary">Vissza</a>
                    <button type="submit" class="btn btn-primary">Nyilatkozatcsomag végleges beküldése</button>
                </div>
            </form>
        </section>
    </main>
</div>

<?= $this->endSection() ?>
