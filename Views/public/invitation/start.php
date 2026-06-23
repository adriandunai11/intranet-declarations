<?= $this->extend('App\Modules\Declarations\Views\public\layout') ?>

<?= $this->section('content') ?>

<div class="hero-card">
    <div class="hero-content">
        <div class="grid">
            <div>
                <div class="eyebrow">Kitöltendő dokumentumok</div>
                <h1>Belépéshez szükséges dokumentumok kitöltése</h1>

                <?php if ($person): ?>
                    <div class="person-box">
                        <div class="person-label">Beálló</div>
                        <div class="person-name"><?= esc($person->fullName()) ?></div>
                    </div>
                <?php endif; ?>

                <p class="lead">
                    Itt találja a belépéshez szükséges kitöltendő dokumentumokat.
                    Kérjük, haladjon sorban, és a hivatalos okmányokon szereplő adatokkal egyezően töltse ki az űrlapokat.
                </p>

                <div class="actions">
                    <a href="#required-declarations" class="btn btn-primary">Dokumentumok megnyitása</a>
                    <?php if (!empty($optionalTaxTemplates)): ?>
                        <a href="#optional-tax" class="btn btn-secondary">Adóügyi lehetőségek</a>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <div class="info-card">
                    <div class="info-title">Fontos tudnivaló</div>
                    <p class="info-text">
                        Kérjük, pontos adatokat adjon meg. A TAJ számot és az adóazonosító jelet Ön rögzíti, ezeket a toborzó nem adja meg Ön helyett.
                    </p>
                </div>

                <div class="info-card">
                    <div class="info-title">Beküldés után</div>
                    <p class="info-text">
                        A beküldött dokumentum ellenőrzésre kerül. Ha javítás szükséges, e-mailben értesítjük, és a dokumentum itt újra megnyitható lesz.
                    </p>
                </div>
            </div>
        </div>

        <div id="required-declarations" class="section-block">
            <h2 class="section-title">Kötelező dokumentumok</h2>

            <?php if (empty($items)): ?>
                <div class="notice notice-warning">Ehhez a csomaghoz jelenleg nincs kitöltendő dokumentum.</div>
            <?php else: ?>
                <ul class="declaration-list">
                    <?php foreach ($items as $item): ?>
                        <?php
                        $badgeClass = 'badge-default';
                        $statusLabel = 'Állapot ismeretlen';
                        $icon = '•';
                        $actionLabel = 'Megnyitás';

                        if ($item->status === 'pending') {
                            $badgeClass = 'badge-pending';
                            $statusLabel = 'Kitöltésre vár';
                            $icon = '→';
                            $actionLabel = 'Kitöltés indítása';
                        } elseif ($item->status === 'completed') {
                            $badgeClass = 'badge-review';
                            $statusLabel = 'Beküldve, ellenőrzés alatt';
                            $icon = '✓';
                            $actionLabel = 'Részletek';
                        } elseif ($item->status === 'accepted') {
                            $badgeClass = 'badge-completed';
                            $statusLabel = 'Elfogadva';
                            $icon = '✓';
                            $actionLabel = 'Megtekintés';
                        } elseif ($item->status === 'rejected') {
                            $badgeClass = 'badge-rejected';
                            $statusLabel = 'Javítás szükséges';
                            $icon = '!';
                            $actionLabel = 'Javítás megnyitása';
                        }

                        $itemUrl = $itemUrls[(int) $item->id] ?? '#';
                        ?>

                        <li class="declaration-item <?= $item->status === 'rejected' ? 'declaration-item-warning' : '' ?>">
                            <div class="declaration-icon"><?= esc($icon) ?></div>

                            <div class="declaration-main">
                                <div class="declaration-name">
                                    <a href="<?= esc($itemUrl) ?>">
                                        <?= esc($item->template_name ?: ('Dokumentum #' . $item->template_id)) ?>
                                    </a>
                                </div>

                                <div class="declaration-meta">
                                    <?= esc($item->template_category ?: 'Dokumentum') ?>
                                    <?php if (!empty($item->template_tax_year)): ?>
                                        · <?= esc($item->template_tax_year) ?>
                                    <?php endif; ?>
                                    <?php if (!empty($item->template_version)): ?>
                                        · <?= esc($item->template_version) ?>
                                    <?php endif; ?>
                                </div>

                                <?php if ($item->status === 'rejected' && !empty($item->review_note)): ?>
                                    <div class="item-note">
                                        <strong>Javítás oka:</strong> <?= esc($item->review_note) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="declaration-side">
                                <span class="badge <?= esc($badgeClass) ?>"><?= esc($statusLabel) ?></span>
                                <a href="<?= esc($itemUrl) ?>" class="small-link"><?= esc($actionLabel) ?></a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div id="optional-tax" class="section-block">
            <h2 class="section-title">Választható adóügyi nyilatkozatok</h2>

            <div class="notice notice-info">
                Ezeket csak akkor válassza ki, ha Önre vonatkoznak, vagy szeretne az adott kedvezményről nyilatkozni.
                A toborzó nem dönt Ön helyett adóügyi kérdésekben.
            </div>

            <?php if (empty($optionalTaxTemplates)): ?>
                <div class="empty-state">Jelenleg nincs további választható adóügyi nyilatkozat.</div>
            <?php else: ?>
                <ul class="optional-list">
                    <?php foreach ($optionalTaxTemplates as $template): ?>
                        <li class="optional-item">
                            <div>
                                <div class="optional-title"><?= esc($template->name) ?></div>
                                <div class="declaration-meta">
                                    <?php if (!empty($template->tax_year)): ?>
                                        Adóév: <?= esc($template->tax_year) ?> ·
                                    <?php endif; ?>
                                    <?= esc($template->description ?: 'Választható adóügyi nyilatkozat.') ?>
                                </div>
                            </div>

                            <?php $isSupported = (bool) ($optionalTaxTemplateSupport[(int) $template->id] ?? false); ?>
                            <?php if ($isSupported): ?>
                                <form method="post" action="<?= esc($startUrl . '/tax-template/' . (int) $template->id . '/select') ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-secondary btn-sm">Kitöltöm ezt</button>
                                </form>
                            <?php else: ?>
                                <span class="badge badge-default">Űrlap előkészítés alatt</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
