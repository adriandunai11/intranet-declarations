<?php
$auditLogs = $auditLogs ?? [];
$tableId = $tableId ?? 'declarationAuditLogTable';
$emptyText = $emptyText ?? 'Még nincs naplózott esemény.';

$actionLabels = [
    'person_created' => 'Személy létrehozva',
    'person_updated' => 'Személy módosítva',
    'person_sensitive_data_updated_from_candidate' => 'Személyes adatok frissítve',
    'person_data_updated_from_submission' => 'Személyes adatok mentve',
    'employment_relation_created' => 'Jogviszony létrehozva',
    'employment_relation_closed' => 'Jogviszony lezárva',
    'employment_relation_reopened' => 'Jogviszony visszanyitva',
    'employment_relation_status_changed' => 'Jogviszony státusz módosult',
    'relation_status_changed' => 'Jogviszony státusz módosult',
    'packet_created' => 'Csomag létrehozva',
    'packet_status_changed' => 'Csomag státusz módosult',
    'packet_submitted' => 'Csomag beküldve',
    'packet_approved' => 'Csomag elfogadva',
    'packet_closed' => 'Csomag lezárva',
    'packet_completed' => 'Csomag elkészült',
    'packet_item_created' => 'Nyilatkozat létrehozva',
    'packet_item_added_by_admin' => 'Nyilatkozat hozzáadva',
    'optional_template_added' => 'Választható nyilatkozat hozzáadva',
    'item_submitted' => 'Nyilatkozat mentve',
    'item_resubmitted' => 'Nyilatkozat javítva',
    'item_accepted' => 'Nyilatkozat elfogadva',
    'item_rejected' => 'Nyilatkozat elutasítva',
    'item_reopened_for_correction' => 'Nyilatkozat újranyitva',
    'invitation_created' => 'Meghívó link létrehozva',
    'invitation_regenerated' => 'Új meghívó link létrehozva',
    'invitation_revoked' => 'Meghívó link visszavonva',
    'invitation_opened' => 'Meghívó link megnyitva',
    'invitation_completed' => 'Meghívó lezárva',
    'invitation_email_sent' => 'Meghívó e-mail kiküldve',
    'antra_verification_succeeded' => 'Antra azonosítás sikeres',
    'antra_verification_failed' => 'Antra azonosítás sikertelen',
    'rejection_email_sent' => 'Javítási e-mail kiküldve',
    'packet_review_email_sent' => 'Ellenőrzési e-mail kiküldve',
    'document_generated' => 'Dokumentum generálva',
];

$entityLabels = [
    'person' => 'Személy',
    'declaration_person' => 'Személy',
    'employment_relation' => 'Jogviszony',
    'declaration_employment_relation' => 'Jogviszony',
    'declaration_packet' => 'Csomag',
    'declaration_packet_item' => 'Nyilatkozat',
    'declaration_invitation' => 'Meghívó',
    'declaration_submission' => 'Beküldés',
];

$statusLabels = [
    'draft' => ['Piszkozat', 'secondary'],
    'sent' => ['Kiküldve', 'info'],
    'created' => ['Létrehozva', 'info'],
    'opened' => ['Megnyitva', 'primary'],
    'pending' => ['Kitöltésre vár', 'secondary'],
    'in_progress' => ['Folyamatban', 'warning'],
    'submitted' => ['Ellenőrzésre vár', 'primary'],
    'declarations_submitted' => ['Nyilatkozatok ellenőrzésen', 'primary'],
    'completed' => ['Elkészült', 'success'],
    'accepted' => ['Elfogadva', 'success'],
    'approved' => ['Elfogadva', 'success'],
    'active' => ['Aktív', 'success'],
    'rejected' => ['Elutasítva', 'danger'],
    'revoked' => ['Visszavonva', 'danger'],
    'closed' => ['Lezárva', 'dark'],
    'cancelled' => ['Törölve', 'danger'],
];

$payloadLabels = [
    'template_name' => 'Nyilatkozat',
    'template_code' => 'Kód',
    'template_id' => 'Sablon ID',
    'tax_year' => 'Adóév',
    'email' => 'E-mail',
    'recipients' => 'Címzettek',
    'rejected_count' => 'Elutasított tételek',
    'rejected_items' => 'Elutasított nyilatkozatok',
    'revoked_active_invitations' => 'Visszavont aktív linkek',
    'expires_at' => 'Lejárat',
    'company_id' => 'Cég ID',
    'updated_fields' => 'Módosított mezők',
    'end_date' => 'Lezárás dátuma',
    'invitation_id' => 'Meghívó ID',
    'submission_id' => 'Beküldés ID',
    'document_format' => 'Formátum',
    'submitted_length' => 'Megadott Antra hossza',
    'expected_present' => 'Rögzített Antra',
];

$decodePayload = static function ($raw): array {
    if ($raw instanceof stdClass) {
        return (array) $raw;
    }

    if (is_array($raw)) {
        return $raw;
    }

    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    if (is_string($decoded)) {
        $decoded = json_decode($decoded, true);
    }

    return is_array($decoded) ? $decoded : [];
};

$formatValue = static function ($value): string {
    if (is_bool($value)) {
        return $value ? 'igen' : 'nem';
    }

    if (is_array($value)) {
        $parts = [];

        foreach ($value as $item) {
            if (is_scalar($item)) {
                $parts[] = (string) $item;
            }
        }

        if ($parts === []) {
            return '-';
        }

        $suffix = count($parts) > 4 ? ' +' . (count($parts) - 4) : '';

        return implode(', ', array_slice($parts, 0, 4)) . $suffix;
    }

    if ($value === null || $value === '') {
        return '-';
    }

    $text = (string) $value;

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($text) > 90 ? mb_substr($text, 0, 87) . '...' : $text;
    }

    return strlen($text) > 90 ? substr($text, 0, 87) . '...' : $text;
};

$statusBadge = static function (?string $status) use ($statusLabels): string {
    if ($status === null || $status === '') {
        return '<span class="text-muted">-</span>';
    }

    [$label, $class] = $statusLabels[$status] ?? [$status, 'secondary'];

    return '<span class="badge badge-' . esc($class) . '">' . esc($label) . '</span>';
};
?>

<?php if (empty($auditLogs)): ?>
    <div class="p-3 text-muted"><?= esc($emptyText) ?></div>
<?php else: ?>
    <div class="table-responsive">
        <table id="<?= esc($tableId) ?>" class="table table-sm table-hover mb-0 declaration-audit-table">
            <thead>
                <tr>
                    <th style="width: 155px;">Időpont</th>
                    <th style="min-width: 220px;">Esemény</th>
                    <th style="width: 210px;">Állapot</th>
                    <th>Részletek</th>
                    <th style="width: 150px;">Szereplő</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($auditLogs as $auditLog): ?>
                    <?php
                    $payload = $decodePayload($auditLog->payload_json ?? null);
                    $action = (string) ($auditLog->action ?? '');
                    $entityType = (string) ($auditLog->entity_type ?? '');
                    $entityLabel = $entityLabels[$entityType] ?? ($entityType !== '' ? $entityType : 'Esemény');
                    $entityId = !empty($auditLog->entity_id) ? '#' . (int) $auditLog->entity_id : '';
                    $contextParts = [];

                    if (!empty($auditLog->person_id)) {
                        $contextParts[] = 'személy #' . (int) $auditLog->person_id;
                    }

                    if (!empty($auditLog->employment_relation_id)) {
                        $contextParts[] = 'jogviszony #' . (int) $auditLog->employment_relation_id;
                    }

                    if (!empty($auditLog->packet_id) && (int) $auditLog->packet_id !== (int) ($auditLog->entity_id ?? 0)) {
                        $contextParts[] = 'csomag #' . (int) $auditLog->packet_id;
                    }

                    if (!empty($auditLog->packet_item_id) && (int) $auditLog->packet_item_id !== (int) ($auditLog->entity_id ?? 0)) {
                        $contextParts[] = 'nyilatkozat #' . (int) $auditLog->packet_item_id;
                    }

                    if (!empty($auditLog->invitation_id) && (int) $auditLog->invitation_id !== (int) ($auditLog->entity_id ?? 0)) {
                        $contextParts[] = 'meghívó #' . (int) $auditLog->invitation_id;
                    }

                    $detailChips = [];

                    foreach ($payloadLabels as $key => $label) {
                        if (!array_key_exists($key, $payload)) {
                            continue;
                        }

                        $detailChips[] = '<span class="badge badge-light border mr-1 mb-1">'
                            . esc($label) . ': ' . esc($formatValue($payload[$key]))
                            . '</span>';
                    }

                    $actorLabel = $auditLog->actor_label
                        ?: ($auditLog->actor_user_id ? ('Felhasználó #' . $auditLog->actor_user_id) : ($auditLog->actor_type ?: '-'));
                    ?>
                    <tr>
                        <td class="text-nowrap"><?= esc($auditLog->created_at ?: '-') ?></td>
                        <td>
                            <strong><?= esc($actionLabels[$action] ?? ($action !== '' ? $action : '-')) ?></strong>
                            <div class="text-muted small">
                                <?= esc(trim($entityLabel . ' ' . $entityId)) ?>
                                <?php if (!empty($contextParts)): ?>
                                    <br><?= esc(implode(' · ', $contextParts)) ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php if (!empty($auditLog->old_status) || !empty($auditLog->new_status)): ?>
                                <?= $statusBadge($auditLog->old_status ?? null) ?>
                                <i class="fas fa-arrow-right mx-1 text-muted"></i>
                                <?= $statusBadge($auditLog->new_status ?? null) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($auditLog->note)): ?>
                                <div><?= esc($auditLog->note) ?></div>
                            <?php endif; ?>

                            <?php if (!empty($detailChips)): ?>
                                <div class="<?= !empty($auditLog->note) ? 'mt-1' : '' ?>">
                                    <?= implode('', $detailChips) ?>
                                </div>
                            <?php endif; ?>

                            <?php if (empty($auditLog->note) && empty($detailChips)): ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?= esc($actorLabel) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
