<?php
$auditLogs = $auditLogs ?? [];
$tableId = $tableId ?? 'declarationAuditLogTable';
$emptyText = $emptyText ?? 'Még nincs naplózott esemény.';
$presenter = new \App\Modules\Declarations\Presenters\AuditLogPresenter();
$rows = $presenter->rows($auditLogs);
?>

<?php if (empty($rows)): ?>
    <div class="p-3 text-muted"><?= esc($emptyText) ?></div>
<?php else: ?>
    <div class="table-responsive">
        <table id="<?= esc($tableId) ?>" class="table table-sm table-hover mb-0 declaration-audit-table">
            <thead>
                <tr>
                    <th style="width: 155px;">Időpont</th>
                    <th style="min-width: 230px;">Esemény</th>
                    <th>Részletek</th>
                    <th style="width: 190px;">Állapot</th>
                    <th style="width: 150px;">Szereplő</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="text-nowrap"><?= esc($row['created_at']) ?></td>
                        <td>
                            <strong><?= esc($row['title']) ?></strong>
                            <div class="text-muted small mt-1">
                                <span class="badge badge-light border"><?= esc($row['scope'] ?: 'Esemény') ?></span>
                                <?php if (!empty($row['context'])): ?>
                                    <span class="d-block mt-1"><?= esc(implode(' · ', $row['context'])) ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php if (empty($row['details'])): ?>
                                <span class="text-muted">-</span>
                            <?php else: ?>
                                <div class="audit-detail-list">
                                    <?php foreach ($row['details'] as $detail): ?>
                                        <div><?= esc($detail) ?></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['old_status'] || $row['new_status']): ?>
                                <?php if ($row['old_status']): ?>
                                    <span class="badge badge-<?= esc($row['old_status']['class']) ?>"><?= esc($row['old_status']['label']) ?></span>
                                <?php endif; ?>
                                <i class="fas fa-arrow-right mx-1 text-muted"></i>
                                <?php if ($row['new_status']): ?>
                                    <span class="badge badge-<?= esc($row['new_status']['class']) ?>"><?= esc($row['new_status']['label']) ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?= esc($row['actor']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
