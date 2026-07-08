<?php
$auditLogs = $auditLogs ?? [];
$tableId = $tableId ?? 'declarationAuditLogFeed';
$emptyText = $emptyText ?? 'Még nincs naplózott esemény.';
$presenter = new \App\Modules\Declarations\Presenters\AuditLogPresenter();
$rows = $presenter->rows($auditLogs);
?>

<style>
    .audit-feed-shell {
        max-width: 980px;
        margin: 0 auto;
        padding: 1rem 0 2.25rem;
    }

    .audit-empty-state {
        border: 1px solid #dbe7df;
        border-radius: 8px;
        background: #fff;
        color: #64748b;
        padding: 2rem;
        text-align: center;
        box-shadow: 0 10px 28px rgba(15, 23, 42, .04);
    }

    .audit-empty-state i {
        display: block;
        margin-bottom: .55rem;
        color: #50b848;
        font-size: 1.2rem;
    }

    .audit-feed {
        display: grid;
        gap: .9rem;
    }

    .audit-entry {
        display: grid;
        grid-template-columns: 132px minmax(0, 1fr);
        gap: 1rem;
        position: relative;
    }

    .audit-entry::before {
        content: "";
        position: absolute;
        top: 2.2rem;
        bottom: -1.1rem;
        left: 118px;
        width: 1px;
        background: #d8e4dc;
    }

    .audit-entry:last-child::before {
        display: none;
    }

    .audit-entry-time {
        color: #0f172a;
        font-weight: 800;
        line-height: 1.2;
        padding-top: .9rem;
        position: relative;
        text-align: right;
    }

    .audit-entry-time span {
        display: block;
        margin-top: .2rem;
        color: #64748b;
        font-size: .82rem;
        font-weight: 700;
    }

    .audit-entry-dot {
        position: absolute;
        top: 1.12rem;
        right: -1.45rem;
        width: 11px;
        height: 11px;
        border: 2px solid #fff;
        border-radius: 50%;
        background: #50b848;
        box-shadow: 0 0 0 3px #e8f6ea;
        z-index: 1;
    }

    .audit-entry-box {
        border: 1px solid #dbe7df;
        border-left: 4px solid #50b848;
        border-radius: 8px;
        background: #fff;
        padding: 1rem;
        box-shadow: 0 12px 30px rgba(15, 23, 42, .05);
    }

    .audit-entry-box.audit-tone-success { border-left-color: #16a34a; }
    .audit-entry-box.audit-tone-danger { border-left-color: #dc2626; }
    .audit-entry-box.audit-tone-warning { border-left-color: #d97706; }
    .audit-entry-box.audit-tone-info { border-left-color: #0284c7; }
    .audit-entry-box.audit-tone-primary { border-left-color: #2563eb; }

    .audit-entry-head {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        align-items: flex-start;
    }

    .audit-title-group {
        display: grid;
        grid-template-columns: 36px minmax(0, 1fr);
        gap: .75rem;
        min-width: 0;
    }

    .audit-icon {
        width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        color: #2563eb;
        background: #eff6ff;
        font-size: .95rem;
    }

    .audit-tone-success .audit-icon { color: #047857; background: #ecfdf5; }
    .audit-tone-danger .audit-icon { color: #b42318; background: #fff1f0; }
    .audit-tone-warning .audit-icon { color: #a15c07; background: #fffbeb; }
    .audit-tone-info .audit-icon { color: #0369a1; background: #e0f2fe; }

    .audit-title {
        margin: 0;
        color: #0f172a;
        font-size: 1rem;
        font-weight: 850;
        line-height: 1.25;
    }

    .audit-code {
        margin-top: .2rem;
        color: #94a3b8;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
        font-size: .73rem;
        overflow-wrap: anywhere;
    }

    .audit-actor-panel {
        min-width: 176px;
        border: 1px solid #edf2f7;
        border-radius: 8px;
        background: #f8fafc;
        padding: .55rem .65rem;
        text-align: right;
    }

    .audit-actor-panel small {
        display: block;
        color: #64748b;
        font-size: .68rem;
        font-weight: 800;
        letter-spacing: .03em;
        text-transform: uppercase;
    }

    .audit-actor-panel strong {
        display: block;
        margin-top: .12rem;
        color: #0f172a;
        font-size: .84rem;
        font-weight: 800;
        line-height: 1.25;
        overflow-wrap: anywhere;
    }

    .audit-chip-list {
        display: flex;
        flex-wrap: wrap;
        gap: .4rem;
        margin-top: .85rem;
    }

    .audit-chip {
        display: inline-flex;
        align-items: center;
        gap: .32rem;
        border: 1px solid #dfe7e2;
        border-radius: 999px;
        background: #fff;
        color: #334155;
        padding: .24rem .58rem;
        font-size: .76rem;
        font-weight: 750;
        line-height: 1.2;
    }

    .audit-chip strong {
        color: #64748b;
        font-weight: 850;
    }

    .audit-body {
        display: grid;
        gap: .75rem;
        margin-top: .9rem;
    }

    .audit-note {
        border: 1px solid #ccebd2;
        border-left: 4px solid #50b848;
        border-radius: 8px;
        background: #f6fff7;
        color: #14532d;
        padding: .65rem .75rem;
        font-size: .86rem;
        font-weight: 700;
        line-height: 1.45;
    }

    .audit-status-flow {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: .4rem;
    }

    .audit-status-flow .fas {
        color: #94a3b8;
        font-size: .72rem;
    }

    .audit-detail-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .55rem;
        margin: 0;
    }

    .audit-detail {
        border: 1px solid #edf2f7;
        border-radius: 8px;
        background: #fbfcfd;
        padding: .62rem .68rem;
        min-width: 0;
    }

    .audit-detail dt,
    .audit-detail dd {
        margin: 0;
    }

    .audit-detail dt {
        color: #64748b;
        font-size: .68rem;
        font-weight: 850;
        letter-spacing: .03em;
        text-transform: uppercase;
    }

    .audit-detail dd {
        margin-top: .16rem;
        color: #1f2937;
        font-size: .86rem;
        font-weight: 730;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .audit-technical {
        color: #94a3b8;
        font-size: .76rem;
        font-weight: 700;
    }

    @media (max-width: 768px) {
        .audit-feed-shell {
            padding-top: .5rem;
        }

        .audit-entry {
            grid-template-columns: 1fr;
            gap: .45rem;
        }

        .audit-entry::before,
        .audit-entry-dot {
            display: none;
        }

        .audit-entry-time {
            padding-top: 0;
            text-align: left;
        }

        .audit-entry-head {
            display: grid;
        }

        .audit-actor-panel {
            min-width: 0;
            text-align: left;
        }

        .audit-detail-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="audit-feed-shell">
    <?php if (empty($rows)): ?>
        <div class="audit-empty-state">
            <i class="fas fa-history"></i>
            <?= esc($emptyText) ?>
        </div>
    <?php else: ?>
        <div id="<?= esc($tableId) ?>" class="audit-feed">
            <?php foreach ($rows as $row): ?>
                <?php $hasStatus = $row['status']['old'] || $row['status']['new']; ?>
                <article class="audit-entry">
                    <div class="audit-entry-time">
                        <span class="audit-entry-dot"></span>
                        <?= esc($row['created_date']) ?>
                        <span><?= esc($row['created_time']) ?></span>
                    </div>

                    <div class="audit-entry-box audit-tone-<?= esc($row['tone']) ?>">
                        <div class="audit-entry-head">
                            <div class="audit-title-group">
                                <span class="audit-icon">
                                    <i class="fas <?= esc($row['icon']) ?>"></i>
                                </span>

                                <div>
                                    <h3 class="audit-title"><?= esc($row['title']) ?></h3>
                                    <div class="audit-code"><?= esc($row['action']) ?></div>
                                </div>
                            </div>

                            <div class="audit-actor-panel">
                                <small><?= esc($row['actor']['type']) ?></small>
                                <strong><?= esc($row['actor']['label']) ?></strong>
                            </div>
                        </div>

                        <div class="audit-chip-list">
                            <span class="audit-chip">
                                <strong><?= esc($row['scope']['label']) ?></strong>
                                <?= $row['scope']['id'] ? '#' . (int) $row['scope']['id'] : '' ?>
                            </span>

                            <?php foreach ($row['context'] as $context): ?>
                                <span class="audit-chip">
                                    <strong><?= esc($context['label']) ?></strong>
                                    <?= esc($context['value']) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($row['note'] !== '' || !empty($row['details']) || $hasStatus || !empty($row['technical'])): ?>
                            <div class="audit-body">
                                <?php if ($hasStatus): ?>
                                    <div class="audit-status-flow">
                                        <?php if ($row['status']['old']): ?>
                                            <span class="badge badge-<?= esc($row['status']['old']['class']) ?>">
                                                <?= esc($row['status']['old']['label']) ?>
                                            </span>
                                        <?php endif; ?>

                                        <i class="fas fa-arrow-right"></i>

                                        <?php if ($row['status']['new']): ?>
                                            <span class="badge badge-<?= esc($row['status']['new']['class']) ?>">
                                                <?= esc($row['status']['new']['label']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($row['note'] !== ''): ?>
                                    <div class="audit-note"><?= esc($row['note']) ?></div>
                                <?php endif; ?>

                                <?php if (!empty($row['details'])): ?>
                                    <dl class="audit-detail-grid">
                                        <?php foreach ($row['details'] as $detail): ?>
                                            <div class="audit-detail">
                                                <dt><?= esc($detail['label']) ?></dt>
                                                <dd><?= esc($detail['value']) ?></dd>
                                            </div>
                                        <?php endforeach; ?>
                                    </dl>
                                <?php endif; ?>

                                <?php if (!empty($row['technical'])): ?>
                                    <div class="audit-technical"><?= esc(implode(' · ', $row['technical'])) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
