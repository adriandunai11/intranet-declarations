<?= $this->extend('App\\Modules\\Declarations\\Views\\public\\layout') ?>

<?= $this->section('content') ?>

<?php
$packetStatus = (string) ($packet->status ?? '');
$canModifyCompletedItems = in_array($packetStatus, ['draft', 'sent', 'in_progress'], true);
$isPacketClosedForCandidate = in_array($packetStatus, ['submitted', 'approved', 'closed', 'completed'], true);
$items = $items ?? [];

$hasSummaryRows = false;
$itemStats = [
    'total' => count($items),
    'done' => 0,
    'todo' => 0,
    'rejected' => 0,
];

foreach (($summaryRowsByItemId ?? []) as $summaryRows) {
    if (!empty($summaryRows)) {
        $hasSummaryRows = true;
        break;
    }
}

foreach ($items as $statsItem) {
    if (in_array((string) $statsItem->status, ['completed', 'accepted'], true)) {
        $itemStats['done']++;
        continue;
    }

    if ((string) $statsItem->status === 'rejected') {
        $itemStats['rejected']++;
        continue;
    }

    $itemStats['todo']++;
}

$completionPercent = $itemStats['total'] > 0
    ? (int) round(($itemStats['done'] / $itemStats['total']) * 100)
    : 0;

$nextItem = null;
foreach ($items as $candidateItem) {
    if ((string) $candidateItem->status === 'rejected') {
        $nextItem = $candidateItem;
        break;
    }
}

if (!$nextItem) {
    foreach ($items as $candidateItem) {
        if ((string) $candidateItem->status === 'pending') {
            $nextItem = $candidateItem;
            break;
        }
    }
}

$nextItemUrl = $nextItem ? ($itemUrls[(int) $nextItem->id] ?? '#') : null;
$nextItemLabel = $nextItem && (string) $nextItem->status === 'rejected'
    ? 'Javítás megnyitása'
    : 'Kitöltés folytatása';

$statusLabel = $isPacketClosedForCandidate
    ? 'Beküldve'
    : (!empty($canFinalize) ? 'Beküldhető' : 'Kitöltés alatt');
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
                    Minden kötelező dokumentum mentve van. Küldje be a csomagot ellenőrzésre.
                <?php elseif ($nextItem): ?>
                    Nyissa meg a következő szükséges dokumentumot.
                <?php elseif ($isPacketClosedForCandidate): ?>
                    A csomag beküldve, az adatok a link érvényességéig megtekinthetők.
                <?php else: ?>
                    Jelenleg nincs megnyitható teendő.
                <?php endif; ?>
            </p>

            <?php if (!empty($canFinalize)): ?>
                <form method="post" action="<?= esc($finalizeUrl) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary btn-block">Csomag beküldése</button>
                </form>
            <?php elseif ($nextItemUrl): ?>
                <a href="<?= esc($nextItemUrl) ?>" class="btn btn-primary btn-block"><?= esc($nextItemLabel) ?></a>
            <?php else: ?>
                <span class="badge badge-completed"><?= esc($statusLabel) ?></span>
            <?php endif; ?>
        </section>

        <section class="sidebar-card">
            <div class="sidebar-section-title">Folyamat</div>
            <ol class="vertical-steps">
                <li class="is-complete">
                    <span>1</span>
                    <strong>Azonosítás</strong>
                </li>
                <li class="<?= $itemStats['todo'] === 0 ? 'is-complete' : 'is-current' ?>">
                    <span>2</span>
                    <strong>Dokumentumok</strong>
                </li>
                <li class="<?= $hasSummaryRows ? (!empty($canFinalize) ? 'is-current' : 'is-complete') : '' ?>">
                    <span>3</span>
                    <strong>Összesítő</strong>
                </li>
                <li class="<?= $isPacketClosedForCandidate ? 'is-complete' : '' ?>">
                    <span>4</span>
                    <strong>Beküldés</strong>
                </li>
            </ol>
        </section>
    </aside>

    <main class="portal-main">
        <section class="page-title-panel">
            <div>
                <div class="eyebrow">Online kitöltés</div>
                <h1>Nyilatkozatok áttekintése</h1>
                <p class="lead">
                    A kötelező dokumentumok mentése után az összesítőben ellenőrizhető minden adat, majd innen indítható a végleges beküldés.
                </p>
            </div>
            <span class="status-pill"><?= esc($statusLabel) ?></span>
        </section>

        <?php if (!empty($canFinalize)): ?>
            <section class="primary-next-card">
                <div>
                    <div class="primary-next-title">A csomag beküldésre kész</div>
                    <p class="primary-next-text">Ellenőrizze az összesítőt, majd küldje be a csomagot a toborzó és a munkaügy részére.</p>
                </div>
                <form method="post" action="<?= esc($finalizeUrl) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary">Végleges beküldés</button>
                </form>
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

        <div class="portal-content-grid">
            <section class="content-card document-panel" id="required-declarations">
                <div class="section-heading">
                    <div>
                        <h2>Kötelező dokumentumok</h2>
                        <p class="section-note">Először ezekkel haladjon. A javítandó tételek mindig a lista tetején jelennek meg.</p>
                    </div>
                </div>

                <?php if (empty($items)): ?>
                    <div class="notice notice-warning">Ehhez a csomaghoz jelenleg nincs kitöltendő dokumentum.</div>
                <?php else: ?>
                    <ul class="task-list">
                        <?php foreach ($items as $item): ?>
                            <?php
                            $badgeClass = 'badge-default';
                            $statusLabelForItem = 'Állapot ismeretlen';
                            $stateClass = 'state-default';
                            $actionLabel = 'Megnyitás';
                            $actionButtonClass = 'btn btn-ghost btn-sm';

                            if ((string) $item->status === 'pending') {
                                $badgeClass = 'badge-pending';
                                $statusLabelForItem = 'Kitöltésre vár';
                                $stateClass = 'state-pending';
                                $actionLabel = 'Kitöltés';
                                $actionButtonClass = 'btn btn-primary btn-sm';
                            } elseif ((string) $item->status === 'completed') {
                                $badgeClass = $canModifyCompletedItems ? 'badge-completed' : 'badge-review';
                                $statusLabelForItem = $canModifyCompletedItems ? 'Mentve' : 'Beküldve, ellenőrzés alatt';
                                $stateClass = $canModifyCompletedItems ? 'state-completed' : 'state-submitted';
                                $actionLabel = $canModifyCompletedItems ? 'Módosítás' : 'Megtekintés';
                            } elseif ((string) $item->status === 'accepted') {
                                $badgeClass = 'badge-completed';
                                $statusLabelForItem = 'Elfogadva';
                                $stateClass = 'state-accepted';
                                $actionLabel = 'Megtekintés';
                            } elseif ((string) $item->status === 'rejected') {
                                $badgeClass = 'badge-rejected';
                                $statusLabelForItem = 'Javítás szükséges';
                                $stateClass = 'state-rejected';
                                $actionLabel = 'Javítás';
                                $actionButtonClass = 'btn btn-primary btn-sm';
                            }

                            $itemUrl = $itemUrls[(int) $item->id] ?? '#';
                            ?>
                            <li class="task-item <?= esc($stateClass) ?> <?= (string) $item->status === 'rejected' ? 'task-item-warning' : '' ?>">
                                <span class="state-dot" aria-hidden="true"></span>
                                <div class="task-main">
                                    <div class="task-title">
                                        <a href="<?= esc($itemUrl) ?>">
                                            <?= esc($item->template_name ?: ('Dokumentum #' . $item->template_id)) ?>
                                        </a>
                                    </div>
                                    <div class="task-meta">
                                        <?= esc($item->template_category ?: 'Dokumentum') ?>
                                        <?php if (!empty($item->template_tax_year)): ?>
                                            · Adóév: <?= esc($item->template_tax_year) ?>
                                        <?php endif; ?>
                                        <?php if (!empty($item->template_version)): ?>
                                            · Verzió: <?= esc($item->template_version) ?>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ((string) $item->status === 'rejected' && !empty($item->review_note)): ?>
                                        <div class="item-note">
                                            <strong>Javítás oka:</strong> <?= esc($item->review_note) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="task-side">
                                    <span class="badge <?= esc($badgeClass) ?>"><?= esc($statusLabelForItem) ?></span>
                                    <a href="<?= esc($itemUrl) ?>" class="<?= esc($actionButtonClass) ?>"><?= esc($actionLabel) ?></a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <aside class="content-card summary-panel">
                <div class="section-heading section-heading-compact">
                    <div>
                        <h2>Összesítő</h2>
                        <p class="section-note">A mentett adatok rövid ellenőrzése.</p>
                    </div>
                </div>

                <?php if (!$hasSummaryRows): ?>
                    <div class="empty-state">Az összesítő akkor jelenik meg, ha legalább egy dokumentum mentésre került.</div>
                <?php else: ?>
                    <?php if (empty($canFinalize) && !$isPacketClosedForCandidate): ?>
                        <div class="notice notice-info">A végleges beküldés akkor válik elérhetővé, ha minden kötelező dokumentum elkészült.</div>
                    <?php endif; ?>

                    <?php foreach ($items as $item): ?>
                        <?php $summaryRows = $summaryRowsByItemId[(int) $item->id] ?? []; ?>
                        <?php if (empty($summaryRows)): ?>
                            <?php continue; ?>
                        <?php endif; ?>

                        <div class="summary-card">
                            <div class="summary-title"><?= esc($item->template_name ?: ('Dokumentum #' . $item->template_id)) ?></div>
                            <dl class="summary-list summary-list-compact">
                                <?php foreach ($summaryRows as $label => $value): ?>
                                    <dt><?= esc($label) ?></dt>
                                    <dd><?= esc($value !== '' ? $value : '-') ?></dd>
                                <?php endforeach; ?>
                            </dl>

                            <?php if ($canModifyCompletedItems): ?>
                                <div class="actions">
                                    <a href="<?= esc($itemUrls[(int) $item->id] ?? '#') ?>" class="btn btn-ghost btn-sm">Módosítás</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <?php if (!empty($canFinalize)): ?>
                        <form method="post" action="<?= esc($finalizeUrl) ?>" class="actions">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-primary btn-block">Csomag végleges beküldése</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </aside>
        </div>

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
                                    <?php if (!empty($template->tax_year)): ?>
                                        Adóév: <?= esc($template->tax_year) ?> ·
                                    <?php endif; ?>
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
