<?= $this->extend('App\\Modules\\Declarations\\Views\\public\\layout') ?>

<?= $this->section('content') ?>

<?php
$packetStatus = (string) ($packet->status ?? '');
$canModifyCompletedItems = in_array($packetStatus, ['draft', 'sent', 'in_progress'], true);
$isPacketClosedForCandidate = in_array($packetStatus, ['submitted', 'approved', 'closed', 'completed'], true);
$items = $items ?? [];
$summaryRowsByItemId = $summaryRowsByItemId ?? [];

$itemStats = [
    'total' => count($items),
    'done' => 0,
    'todo' => 0,
    'rejected' => 0,
    'previewable' => 0,
];

$hasSummaryRows = false;
$nextItem = null;

foreach ($items as $statsItem) {
    $status = (string) ($statsItem->status ?? '');
    $summaryRows = $summaryRowsByItemId[(int) $statsItem->id] ?? [];
    $isPreviewable = !empty($summaryRows) && (string) ($statsItem->template_code ?? '') !== 'personal_data_statement';

    if (!empty($summaryRows)) {
        $hasSummaryRows = true;
    }

    if ($isPreviewable) {
        $itemStats['previewable']++;
    }

    if (in_array($status, ['completed', 'accepted'], true)) {
        $itemStats['done']++;
        continue;
    }

    if ($status === 'rejected') {
        $itemStats['rejected']++;
        $nextItem ??= $statsItem;
        continue;
    }

    $itemStats['todo']++;
    $nextItem ??= $statsItem;
}

$completionPercent = $itemStats['total'] > 0
    ? (int) round(($itemStats['done'] / $itemStats['total']) * 100)
    : 0;

$nextItemUrl = $nextItem ? ($itemUrls[(int) $nextItem->id] ?? '#') : null;
$nextItemLabel = $nextItem && (string) $nextItem->status === 'rejected'
    ? 'Javítás megnyitása'
    : 'Kitöltés folytatása';

$statusLabel = $isPacketClosedForCandidate
    ? 'Beküldve'
    : (!empty($canFinalize) ? 'Beküldhető' : 'Kitöltés alatt');

$previewUrlFor = static function (object $item) use ($startUrl): string {
    return rtrim((string) $startUrl, '/') . '/item/' . (int) $item->id . '/preview';
};
?>

<div class="portal-layout">
    <aside class="portal-sidebar">
        <section class="sidebar-card sidebar-card-primary">
            <div class="eyebrow">Nyilatkozatcsomag</div>
            <h1>Kitöltési állapot</h1>

            <?php if ($person): ?>
                <div class="person-chip person-chip-sidebar">
                    <span>Kitöltő</span>
                    <strong><?= esc($person->fullName()) ?></strong>
                </div>
            <?php endif; ?>

            <div class="sidebar-progress">
                <div class="sidebar-progress-head">
                    <span>Készültség</span>
                    <strong><?= (int) $completionPercent ?>%</strong>
                </div>
                <div class="wizard-progress-rail">
                    <span class="wizard-progress-fill" style="width: <?= (int) $completionPercent ?>%"></span>
                </div>
            </div>

            <div class="sidebar-stat-list">
                <div>
                    <span>Mentett</span>
                    <strong><?= (int) $itemStats['done'] ?></strong>
                </div>
                <div>
                    <span>Hátralévő</span>
                    <strong><?= (int) $itemStats['todo'] ?></strong>
                </div>
                <div>
                    <span>Javítandó</span>
                    <strong><?= (int) $itemStats['rejected'] ?></strong>
                </div>
            </div>
        </section>

        <section class="sidebar-card">
            <div class="sidebar-section-title">Következő lépés</div>
            <p class="sidebar-copy">
                <?php if (!empty($canFinalize)): ?>
                    Minden kötelező dokumentum mentve van. Nyissa meg az ellenőrző oldalt, nézze át az adatokat, majd ott tudja véglegesen beküldeni.
                <?php elseif ($nextItem): ?>
                    Haladjon tovább a következő kitöltendő vagy javítandó nyilatkozattal.
                <?php elseif ($isPacketClosedForCandidate): ?>
                    A csomag beküldve, az adatok a link érvényességéig megtekinthetők.
                <?php else: ?>
                    Jelenleg nincs megnyitható teendő.
                <?php endif; ?>
            </p>

            <?php if (!empty($canFinalize)): ?>
                <a href="<?= esc($reviewUrl) ?>" class="btn btn-primary btn-block">Ellenőrzés és beküldés</a>
            <?php elseif ($nextItemUrl): ?>
                <a href="<?= esc($nextItemUrl) ?>" class="btn btn-primary btn-block"><?= esc($nextItemLabel) ?></a>
            <?php else: ?>
                <span class="badge badge-completed"><?= esc($statusLabel) ?></span>
            <?php endif; ?>
        </section>

        <section class="sidebar-card">
            <div class="sidebar-section-title">Folyamat</div>
            <ol class="vertical-steps">
                <li class="is-complete"><span>1</span><strong>Azonosítás</strong></li>
                <li class="<?= $itemStats['todo'] === 0 ? 'is-complete' : 'is-current' ?>"><span>2</span><strong>Kitöltés</strong></li>
                <li class="<?= $hasSummaryRows ? (!empty($canFinalize) ? 'is-current' : 'is-complete') : '' ?>"><span>3</span><strong>Ellenőrzés</strong></li>
                <li class="<?= $isPacketClosedForCandidate ? 'is-complete' : '' ?>"><span>4</span><strong>Beküldés</strong></li>
            </ol>
        </section>
    </aside>

    <main class="portal-main">
        <section class="page-title-panel">
            <div>
                <div class="eyebrow">Online kitöltés</div>
                <h1>Nyilatkozatok áttekintése</h1>
                <p class="lead">
                    Itt látja a kitöltendő és már mentett nyilatkozatokat. A végleges beküldés előtt külön ellenőrző oldalon tudja egyben átnézni az összes adatot.
                </p>
            </div>
            <span class="status-pill"><?= esc($statusLabel) ?></span>
        </section>

        <?php if (!empty($canFinalize)): ?>
            <section class="primary-next-card" id="document-preview-check">
                <div>
                    <div class="primary-next-title">A csomag ellenőrzésre kész</div>
                    <p class="primary-next-text">
                        Minden kötelező dokumentum mentve van. Az összes adat egyben a következő oldalon ellenőrizhető.
                    </p>
                </div>
                <a href="<?= esc($reviewUrl) ?>" class="btn btn-primary">Ellenőrzés és beküldés</a>
            </section>
        <?php elseif ($itemStats['rejected'] > 0): ?>
            <section class="primary-next-card primary-next-card-danger">
                <div>
                    <div class="primary-next-title">Javítás szükséges</div>
                    <p class="primary-next-text">A pirossal jelölt dokumentumokat javítani kell, mielőtt a csomag újra beküldhető lenne.</p>
                </div>
                <?php if ($nextItemUrl): ?>
                    <a href="<?= esc($nextItemUrl) ?>" class="btn btn-primary">Javítás megnyitása</a>
                <?php endif; ?>
            </section>
        <?php elseif ($isPacketClosedForCandidate): ?>
            <section class="notice notice-success">
                A nyilatkozatcsomag véglegesen beküldve. A beküldött adatok a link érvényességéig itt megtekinthetők.
            </section>
        <?php endif; ?>

        <section class="content-card document-panel document-panel-wide" id="required-declarations">
            <div class="section-heading">
                <div>
                    <h2>Dokumentumok</h2>
                    <p class="section-note">A megtekintés gombbal az adott nyilatkozat részletes adatai nyílnak meg. A PDF előnézet csak ott jelenik meg, ahol a sablon alapján generálható dokumentum.</p>
                </div>
            </div>

            <?php if (empty($items)): ?>
                <div class="notice notice-warning">Ehhez a csomaghoz jelenleg nincs kitöltendő dokumentum.</div>
            <?php else: ?>
                <ul class="task-list task-list-wide">
                    <?php foreach ($items as $item): ?>
                        <?php
                        $status = (string) ($item->status ?? '');
                        $summaryRows = $summaryRowsByItemId[(int) $item->id] ?? [];
                        $canPreview = !empty($summaryRows) && (string) ($item->template_code ?? '') !== 'personal_data_statement';
                        $badgeClass = 'badge-default';
                        $statusLabelForItem = 'Állapot ismeretlen';
                        $stateClass = 'state-default';
                        $actionLabel = 'Megnyitás';
                        $actionButtonClass = 'btn btn-ghost btn-sm';

                        if ($status === 'pending') {
                            $badgeClass = 'badge-pending';
                            $statusLabelForItem = 'Kitöltésre vár';
                            $stateClass = 'state-pending';
                            $actionLabel = 'Kitöltés';
                            $actionButtonClass = 'btn btn-primary btn-sm';
                        } elseif ($status === 'completed') {
                            $badgeClass = $canModifyCompletedItems ? 'badge-completed' : 'badge-review';
                            $statusLabelForItem = $canModifyCompletedItems ? 'Mentve' : 'Beküldve, ellenőrzés alatt';
                            $stateClass = $canModifyCompletedItems ? 'state-completed' : 'state-submitted';
                            $actionLabel = $canModifyCompletedItems ? 'Módosítás' : 'Megtekintés';
                        } elseif ($status === 'accepted') {
                            $badgeClass = 'badge-completed';
                            $statusLabelForItem = 'Elfogadva';
                            $stateClass = 'state-accepted';
                            $actionLabel = 'Megtekintés';
                        } elseif ($status === 'rejected') {
                            $badgeClass = 'badge-rejected';
                            $statusLabelForItem = 'Javítás szükséges';
                            $stateClass = 'state-rejected';
                            $actionLabel = 'Javítás';
                            $actionButtonClass = 'btn btn-primary btn-sm';
                        }

                        $itemUrl = $itemUrls[(int) $item->id] ?? '#';
                        ?>
                        <li class="task-item <?= esc($stateClass) ?> <?= $status === 'rejected' ? 'task-item-warning' : '' ?>">
                            <span class="state-dot" aria-hidden="true"></span>
                            <div class="task-main">
                                <div class="task-title">
                                    <a href="<?= esc($itemUrl) ?>"><?= esc($item->template_name ?: ('Dokumentum #' . $item->template_id)) ?></a>
                                </div>
                                <div class="task-meta">
                                    <?= esc($item->template_category ?: 'Dokumentum') ?>
                                    <?php if (!empty($item->template_tax_year)): ?> · Adóév: <?= esc($item->template_tax_year) ?><?php endif; ?>
                                    <?php if (!empty($item->template_version)): ?> · Verzió: <?= esc($item->template_version) ?><?php endif; ?>
                                </div>

                                <?php if ($status === 'rejected' && !empty($item->review_note)): ?>
                                    <div class="item-note"><strong>Javítás oka:</strong> <?= esc($item->review_note) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="task-side task-side-horizontal">
                                <span class="badge <?= esc($badgeClass) ?>"><?= esc($statusLabelForItem) ?></span>
                                <a href="<?= esc($itemUrl) ?>" class="<?= esc($actionButtonClass) ?>"><?= esc($actionLabel) ?></a>
                                <?php if ($canPreview): ?>
                                    <a href="<?= esc($previewUrlFor($item)) ?>" class="btn btn-secondary btn-sm" target="_blank" rel="noopener">PDF előnézet</a>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="content-card optional-tax-panel" id="optional-tax">
            <div class="section-heading">
                <div>
                    <h2>Választható adóügyi nyilatkozatok</h2>
                    <p class="section-note">Csak azt válassza ki, amely Önre vonatkozik, vagy amelyről nyilatkozni szeretne.</p>
                </div>
            </div>

            <?php if (empty($optionalTaxTemplates)): ?>
                <div class="empty-state">Jelenleg nincs további választható adóügyi nyilatkozat.</div>
            <?php else: ?>
                <ul class="optional-list">
                    <?php foreach ($optionalTaxTemplates as $template): ?>
                        <li class="task-item">
                            <span class="state-dot" aria-hidden="true"></span>
                            <div>
                                <div class="task-title"><?= esc($template->name) ?></div>
                                <div class="task-meta">
                                    <?php if (!empty($template->tax_year)): ?>Adóév: <?= esc($template->tax_year) ?> · <?php endif; ?>
                                    <?= esc($template->description ?: 'Választható adóügyi nyilatkozat.') ?>
                                </div>
                            </div>
                            <div class="task-side">
                                <?php $isSupported = (bool) ($optionalTaxTemplateSupport[(int) $template->id] ?? false); ?>
                                <?php if ($isSupported): ?>
                                    <form method="post" action="<?= esc($startUrl . '/tax-template/' . (int) $template->id . '/select') ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-secondary btn-sm">Hozzáadás</button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge badge-default">Előkészítés alatt</span>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </main>
</div>

<?= $this->endSection() ?>
