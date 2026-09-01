<?= $this->extend('App\\Modules\\Declarations\\Views\\public\\layout') ?>

<?= $this->section('content') ?>

<?php
$packetStatus = (string) ($packet->status ?? '');
$canModifyCompletedItems = in_array($packetStatus, ['draft', 'sent', 'in_progress'], true);
$isPacketClosedForCandidate = in_array($packetStatus, ['submitted', 'approved', 'closed', 'completed'], true);
$items = $items ?? [];
$optionalTaxTemplates = $optionalTaxTemplates ?? [];
$summaryRowsByItemId = $summaryRowsByItemId ?? [];
$summaryTablesByItemId = $summaryTablesByItemId ?? [];
$removableItemIds = $removableItemIds ?? [];
$packetFlowType = (string) ($packet->flow_type ?? '');
$showOptionalTaxPanel = $packetFlowType !== 'onboarding' || !empty($optionalTaxTemplates);
$pendingItems = array_values(array_filter($items, static function (object $item): bool {
    return !in_array((string) ($item->status ?? ''), ['completed', 'accepted'], true);
}));
$completedItems = array_values(array_filter($items, static function (object $item): bool {
    return in_array((string) ($item->status ?? ''), ['completed', 'accepted'], true);
}));
$itemGroups = [
    [
        'title' => 'Még kitöltendő',
        'description' => 'Ezeket a nyilatkozatokat még ki kell tölteni vagy javítani kell.',
        'items' => $pendingItems,
        'empty' => 'Nincs további kitöltendő nyilatkozat.',
    ],
    [
        'title' => 'Kitöltött',
        'description' => $canModifyCompletedItems
            ? 'A mentett nyilatkozatokat a végleges beküldésig még módosíthatja.'
            : 'Ezeket a nyilatkozatokat már beküldte. Az adatok itt megtekinthetők.',
        'items' => $completedItems,
        'empty' => 'Még nincs kitöltött nyilatkozat.',
    ],
];

$templateDetails = static function (object $template): array {
    if (method_exists($template, 'details')) {
        return $template->details();
    }

    $raw = (string) ($template->details_json ?? $template->template_details_json ?? '');

    if ($raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
};

$templateShortDescription = static function (object $template) use ($templateDetails): string {
    $details = $templateDetails($template);

    return trim((string) ($details['short_description'] ?? $template->description ?? $template->template_description ?? ''));
};

$templateLongDescription = static function (object $template) use ($templateDetails, $templateShortDescription): string {
    $details = $templateDetails($template);

    return trim((string) ($details['long_description'] ?? $templateShortDescription($template)));
};

$templateValue = static function (object $template, array $keys): string {
    foreach ($keys as $key) {
        $value = trim((string) ($template->{$key} ?? ''));

        if ($value !== '') {
            return $value;
        }
    }

    return '';
};

$categoryLabel = static function (string $category): string {
    return [
        'tax_advance' => 'Adóügy',
        'payroll' => 'Bérszámfejtés',
        'employment' => 'Munkaügy',
        'onboarding' => 'Beléptetés',
        'personal_data' => 'Személyes adatok',
        'gdpr' => 'Adatvédelem',
        'work_safety' => 'Munkavédelem',
        'company_policy' => 'Szabályzat',
        'travel_cost' => 'Utazási költség',
    ][$category] ?? 'Nyilatkozat';
};

$isTaxTemplate = static function (object $template) use ($templateValue): bool {
    return $templateValue($template, ['template_declaration_group', 'declaration_group']) === 'tax'
        || $templateValue($template, ['template_category', 'category']) === 'tax_advance';
};

$templateAreaLabel = static function (object $template) use ($templateValue, $categoryLabel): string {
    return $categoryLabel($templateValue($template, ['template_category', 'category']));
};

$templateTaxYear = static function (object $template) use ($templateValue, $packet): string {
    return $templateValue($template, ['template_tax_year', 'tax_year'])
        ?: (string) ($packet->tax_year ?? date('Y'));
};

$templateMetaText = static function (object $template) use ($templateAreaLabel, $templateTaxYear, $isTaxTemplate): string {
    $parts = [$templateAreaLabel($template)];

    if ($isTaxTemplate($template)) {
        $parts[] = 'Adóév: ' . $templateTaxYear($template);
    }

    return implode(' · ', array_filter($parts));
};

$itemStats = [
    'total' => count($items),
    'done' => 0,
    'todo' => 0,
    'rejected' => 0,
];

$nextItem = null;

foreach ($items as $statsItem) {
    $status = (string) ($statsItem->status ?? '');

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
$isReadyForReview = !$isPacketClosedForCandidate && !empty($canFinalize);

$nextItemUrl = $nextItem ? ($itemUrls[(int) $nextItem->id] ?? '#') : null;
$nextItemLabel = $nextItem && (string) $nextItem->status === 'rejected'
    ? 'Javítás megnyitása'
    : 'Kitöltés folytatása';

$readOnlyPacketLabels = [
    'submitted' => 'Beküldve, ellenőrzés alatt',
    'approved' => 'Elfogadva, lezárásra vár',
    'completed' => 'Elfogadva, lezárásra vár',
    'closed' => 'Lezárva',
];
$statusLabel = $isPacketClosedForCandidate
    ? ($readOnlyPacketLabels[$packetStatus] ?? 'Beküldve')
    : (!empty($canFinalize) ? 'Beküldhető' : 'Kitöltés alatt');
$readOnlyPacketNotice = in_array($packetStatus, ['approved', 'completed'], true)
    ? 'A nyilatkozatcsomagot a munkaügy elfogadta. A beküldött adatok a munkaügyi lezárásig itt megtekinthetők.'
    : 'A nyilatkozatcsomag véglegesen beküldve. A beküldött adatok a munkaügyi lezárásig itt megtekinthetők.';

$removeUrlFor = static function (object $item) use ($startUrl): string {
    return rtrim((string) $startUrl, '/') . '/item/' . (int) $item->id . '/remove';
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
                <div><span>Mentett</span><strong><?= (int) $itemStats['done'] ?></strong></div>
                <div><span>Hátralévő</span><strong><?= (int) $itemStats['todo'] ?></strong></div>
                <div><span>Javítandó</span><strong><?= (int) $itemStats['rejected'] ?></strong></div>
            </div>
        </section>

        <section class="sidebar-card">
            <div class="sidebar-section-title">Következő lépés</div>
            <p class="sidebar-copy">
                <?php if (!empty($canFinalize)): ?>
                    Minden csomagban lévő nyilatkozat mentve van. Nyissa meg az ellenőrző oldalt, nézze át az adatokat, majd ott tudja véglegesen beküldeni.
                <?php elseif ($nextItem): ?>
                    Haladjon tovább a következő kitöltendő vagy javítandó nyilatkozattal. Ha egy választható nyilatkozat nem szükséges, a Nem kérem gombbal eltávolítható.
                <?php elseif ($isPacketClosedForCandidate): ?>
                    <?= esc($readOnlyPacketNotice) ?>
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
                <li class="<?= ($isReadyForReview || $isPacketClosedForCandidate) ? 'is-complete' : 'is-current' ?>"><span>2</span><strong>Kitöltés</strong></li>
                <li class="<?= $isPacketClosedForCandidate ? 'is-complete' : ($isReadyForReview ? 'is-current' : '') ?>"><span>3</span><strong>Ellenőrzés</strong></li>
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
                    <p class="primary-next-text">Minden csomagban lévő nyilatkozat mentve van. Az összes adat egyben a következő oldalon ellenőrizhető.</p>
                </div>
                <a href="<?= esc($reviewUrl) ?>" class="btn btn-primary">Ellenőrzés és beküldés</a>
            </section>
        <?php elseif ($itemStats['rejected'] > 0): ?>
            <section class="primary-next-card primary-next-card-danger">
                <div>
                    <div class="primary-next-title">Javítás szükséges</div>
                    <p class="primary-next-text">A pirossal jelölt nyilatkozatokat javítani kell, mielőtt a csomag újra beküldhető lenne.</p>
                </div>
                <?php if ($nextItemUrl): ?>
                    <a href="<?= esc($nextItemUrl) ?>" class="btn btn-primary">Javítás megnyitása</a>
                <?php endif; ?>
            </section>
        <?php elseif ($isPacketClosedForCandidate): ?>
            <section class="notice notice-success">
                <?= esc($readOnlyPacketNotice) ?>
            </section>
        <?php endif; ?>

        <?php foreach ($itemGroups as $groupIndex => $itemGroup): ?>
            <section class="content-card document-panel document-panel-wide"<?= $groupIndex === 0 ? ' id="required-declarations"' : '' ?>>
                <div class="section-heading">
                    <div>
                        <h2><?= esc($itemGroup['title']) ?></h2>
                        <p class="section-note"><?= esc($itemGroup['description']) ?></p>
                    </div>
                    <span class="status-pill"><?= count($itemGroup['items']) ?> db</span>
                </div>

                <?php if (empty($itemGroup['items'])): ?>
                    <div class="empty-state"><?= esc($itemGroup['empty']) ?></div>
                <?php else: ?>
                <ul class="task-list task-list-wide">
                    <?php foreach ($itemGroup['items'] as $item): ?>
                        <?php
                        $status = (string) ($item->status ?? '');
                        $summaryRows = $summaryRowsByItemId[(int) $item->id] ?? [];
                        $summaryTables = $summaryTablesByItemId[(int) $item->id] ?? [];
                        $canRemoveCandidateSelected = !empty($removableItemIds[(int) $item->id]);
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
                        } elseif ($status === 'in_progress') {
                            $badgeClass = 'badge-pending';
                            $statusLabelForItem = 'Kitöltés megkezdve';
                            $stateClass = 'state-pending';
                            $actionLabel = 'Kitöltés folytatása';
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
                        $itemDialogId = 'template-detail-item-' . (int) $item->id;
                        ?>
                        <li class="task-item <?= esc($stateClass) ?> <?= $status === 'rejected' ? 'task-item-warning' : '' ?>">
                            <span class="state-dot" aria-hidden="true"></span>
                            <div class="task-main">
                                <div class="task-title"><a href="<?= esc($itemUrl) ?>"><?= esc($item->template_name ?: ('Nyilatkozat #' . $item->template_id)) ?></a></div>
                                <div class="task-meta">
                                    <?= esc($templateMetaText($item)) ?>
                                </div>

                                <?php if ($status === 'rejected' && !empty($item->review_note)): ?>
                                    <div class="item-note"><strong>Javítás oka:</strong> <?= esc($item->review_note) ?></div>
                                <?php endif; ?>

                                <?php if (!empty($summaryRows)): ?>
                                    <details class="task-summary-details">
                                        <summary>Mentett adatok megtekintése</summary>
                                        <dl class="review-data-list task-summary-list">
                                            <?php foreach (array_slice($summaryRows, 0, 6, true) as $label => $value): ?>
                                                <div><dt><?= esc($label) ?></dt><dd><?= esc($value !== '' ? $value : '-') ?></dd></div>
                                            <?php endforeach; ?>
                                        </dl>
                                        <?php if (count($summaryRows) > 6): ?>
                                            <div class="task-meta">A további adatok az ellenőrző oldalon láthatók.</div>
                                        <?php endif; ?>
                                    </details>
                                <?php endif; ?>
                                <?php if (!empty($summaryTables)): ?>
                                    <div class="task-meta">A táblázatos adatok az ellenőrző oldalon láthatók.</div>
                                <?php endif; ?>
                            </div>
                            <div class="task-side task-side-horizontal">
                                <span class="badge <?= esc($badgeClass) ?>"><?= esc($statusLabelForItem) ?></span>
                                <button type="button" class="btn btn-ghost btn-sm" data-template-details-target="<?= esc($itemDialogId) ?>">Leírás</button>
                                <a href="<?= esc($itemUrl) ?>" class="<?= esc($actionButtonClass) ?>"><?= esc($actionLabel) ?></a>
                                <?php if ($canRemoveCandidateSelected): ?>
                                    <form method="post" action="<?= esc($removeUrlFor($item)) ?>" class="inline-action-form">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-ghost btn-sm">Nem kérem</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>

        <?php if ($showOptionalTaxPanel): ?>
            <section class="content-card optional-tax-panel" id="optional-tax">
                <div class="section-heading">
                    <div>
                        <h2>Választható</h2>
                        <p class="section-note">Csak azt válassza ki, amely Önre vonatkozik, vagy amelyről nyilatkozni szeretne.</p>
                    </div>
                </div>

                <?php if (empty($optionalTaxTemplates)): ?>
                    <div class="empty-state">Jelenleg nincs további választható adóügyi nyilatkozat.</div>
                <?php else: ?>
                    <ul class="optional-list">
                        <?php foreach ($optionalTaxTemplates as $template): ?>
                            <?php $dialogId = 'template-detail-' . (int) $template->id; ?>
                            <li class="task-item">
                                <span class="state-dot" aria-hidden="true"></span>
                                <div>
                                    <div class="task-title"><?= esc($template->name) ?></div>
                                    <div class="task-meta">
                                        <?= esc($templateMetaText($template)) ?>
                                    </div>
                                    <div class="task-meta"><?= esc($templateShortDescription($template) ?: 'Választható adóügyi nyilatkozat.') ?></div>
                                </div>
                                <div class="task-side task-side-horizontal">
                                    <?php $isSupported = (bool) ($optionalTaxTemplateSupport[(int) $template->id] ?? false); ?>
                                    <?php if ($isSupported): ?>
                                        <form method="post" action="<?= esc($startUrl . '/tax-template/' . (int) $template->id . '/select') ?>">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-secondary btn-sm">Kiválaszt</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge badge-default">Előkészítés alatt</span>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-ghost btn-sm" data-template-details-target="<?= esc($dialogId) ?>">Leírás</button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</div>

<?php foreach ($items as $item): ?>
    <?php
    $details = $templateDetails($item);
    $dialogId = 'template-detail-item-' . (int) $item->id;
    $isTaxItem = $isTaxTemplate($item);
    ?>
    <dialog class="confirm-dialog" id="<?= esc($dialogId) ?>">
        <div class="confirm-dialog-card">
            <h2><?= esc($item->template_name ?: 'Nyilatkozat részletei') ?></h2>
            <p><?= nl2br(esc($templateLongDescription($item) ?: 'Ehhez a nyilatkozathoz nincs külön részletes leírás rögzítve.')) ?></p>
            <dl class="review-data-list">
                <div><dt>Terület</dt><dd><?= esc($templateAreaLabel($item)) ?></dd></div>
                <?php if ($isTaxItem): ?>
                    <div><dt>Adóév</dt><dd><?= esc($templateTaxYear($item)) ?></dd></div>
                    <div><dt>Kinek szól</dt><dd><?= esc($details['taxpayer_scope'] ?? '-') ?></dd></div>
                <?php elseif (!empty($details['taxpayer_scope']) && (string) $details['taxpayer_scope'] !== '-'): ?>
                    <div><dt>Kinek szól</dt><dd><?= esc($details['taxpayer_scope']) ?></dd></div>
                <?php endif; ?>
            </dl>
            <div class="confirm-dialog-actions">
                <button type="button" class="btn btn-secondary" data-template-details-close>Vissza</button>
            </div>
        </div>
    </dialog>
<?php endforeach; ?>

<?php foreach ($optionalTaxTemplates as $template): ?>
    <?php
    $details = $templateDetails($template);
    $dialogId = 'template-detail-' . (int) $template->id;
    ?>
    <dialog class="confirm-dialog" id="<?= esc($dialogId) ?>">
        <div class="confirm-dialog-card">
            <h2><?= esc($template->name ?: 'Nyilatkozat részletei') ?></h2>
            <p><?= nl2br(esc($templateLongDescription($template) ?: 'Ehhez a nyilatkozathoz nincs külön részletes leírás rögzítve.')) ?></p>
            <dl class="review-data-list">
                <div><dt>Terület</dt><dd><?= esc($templateAreaLabel($template)) ?></dd></div>
                <div><dt>Adóév</dt><dd><?= esc($templateTaxYear($template)) ?></dd></div>
                <div><dt>Kinek szól</dt><dd><?= esc($details['taxpayer_scope'] ?? '-') ?></dd></div>
            </dl>
            <div class="confirm-dialog-actions">
                <button type="button" class="btn btn-secondary" data-template-details-close>Vissza</button>
            </div>
        </div>
    </dialog>
<?php endforeach; ?>

<script>
    (function () {
        document.querySelectorAll('[data-template-details-target]').forEach(function (button) {
            button.addEventListener('click', function () {
                var dialog = document.getElementById(button.getAttribute('data-template-details-target'));

                if (!dialog) return;

                if (typeof dialog.showModal === 'function') {
                    dialog.showModal();
                } else {
                    dialog.setAttribute('open', 'open');
                }
            });
        });

        document.querySelectorAll('[data-template-details-close]').forEach(function (button) {
            button.addEventListener('click', function () {
                var dialog = button.closest('dialog');

                if (!dialog) return;

                if (typeof dialog.close === 'function') {
                    dialog.close();
                } else {
                    dialog.removeAttribute('open');
                }
            });
        });
    })();
</script>

<?= $this->endSection() ?>
