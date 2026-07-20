<?php

namespace App\Modules\Declarations\Presenters;

class AuditLogPresenter
{
    private array $actionLabels = [
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
        'optional_template_removed' => 'Választható nyilatkozat eltávolítva',
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
        'employee_self_service_packet_created' => 'Saját indítású csomag létrehozva',
        'employee_self_service_invitation_email_sent' => 'Saját indítású link kiküldve',
    ];

    private array $entityLabels = [
        'person' => 'Személy',
        'declaration_person' => 'Személy',
        'employment_relation' => 'Jogviszony',
        'declaration_employment_relation' => 'Jogviszony',
        'declaration_packet' => 'Csomag',
        'declaration_packet_item' => 'Nyilatkozat',
        'declaration_invitation' => 'Meghívó',
        'declaration_submission' => 'Beküldés',
    ];

    private array $statusLabels = [
        'draft' => ['Piszkozat', 'secondary'],
        'sent' => ['Kiküldve', 'info'],
        'created' => ['Létrehozva', 'info'],
        'opened' => ['Megnyitva', 'primary'],
        'pending' => ['Kitöltésre vár', 'secondary'],
        'in_progress' => ['Kitöltés alatt', 'warning'],
        'submitted' => ['Ellenőrzésre vár', 'primary'],
        'declarations_submitted' => ['Nyilatkozatok ellenőrzésen', 'primary'],
        'completed' => ['Elkészült', 'success'],
        'accepted' => ['Elfogadva', 'success'],
        'approved' => ['Elfogadva', 'success'],
        'active' => ['Aktív', 'success'],
        'onboarding' => ['Beléptetés alatt', 'warning'],
        'invited' => ['Meghívó kiküldve', 'info'],
        'rejected' => ['Elutasítva', 'danger'],
        'revoked' => ['Visszavonva', 'danger'],
        'closed' => ['Lezárva', 'dark'],
        'cancelled' => ['Törölve', 'danger'],
    ];

    private array $payloadLabels = [
        'antra_id' => 'Antra azonosító',
        'lastname' => 'Vezetéknév',
        'firstname' => 'Keresztnév',
        'birth_name' => 'Születési név',
        'mother_name' => 'Anyja neve',
        'birth_place' => 'Születési hely',
        'birth_date' => 'Születési dátum',
        'tax_number' => 'Adóazonosító jel',
        'taj_number' => 'TAJ szám',
        'phone' => 'Telefonszám',
        'email' => 'E-mail',
        'status' => 'Státusz',
        'template_name' => 'Nyilatkozat',
        'template_code' => 'Kód',
        'template_id' => 'Nyilatkozat ID',
        'template_version' => 'Nyilatkozat verzió',
        'output_path' => 'Kimeneti fájl',
        'format' => 'Formátum',
        'tax_year' => 'Adóév',
        'recipients' => 'Címzettek',
        'rejected_count' => 'Elutasított tételek',
        'rejected_items' => 'Elutasított nyilatkozatok',
        'revoked_active_invitations' => 'Visszavont aktív linkek',
        'expires_at' => 'Lejárat',
        'company_id' => 'Cég ID',
        'location_id' => 'Telephely ID',
        'primary_recruiter_user_id' => 'Elsődleges toborzó',
        'reviewed_by_user_id' => 'Ellenőrizte',
        'created_by_user_id' => 'Létrehozta',
        'updated_by_user_id' => 'Módosította',
        'closed_by_user_id' => 'Lezárta',
        'reopened_by_user_id' => 'Visszanyitotta',
        'updated_fields' => 'Módosított mezők',
        'tax_number_changed' => 'Adóazonosító változott',
        'taj_number_changed' => 'TAJ változott',
        'phone_changed' => 'Telefonszám változott',
        'start_date' => 'Kezdés dátuma',
        'end_date' => 'Lezárás dátuma',
        'old_end_date' => 'Korábbi lezárás dátuma',
        'invitation_id' => 'Meghívó ID',
        'submission_id' => 'Beküldés ID',
        'submitted_length' => 'Megadott Antra hossza',
        'expected_present' => 'Rögzített Antra volt',
        'old_submission_status' => 'Korábbi beküldési státusz',
        'new_submission_status' => 'Új beküldési státusz',
        'review_note' => 'Megjegyzés',
        'selection_source' => 'Nyilatkozat eredete',
        'submitter_email' => 'Kitöltő e-mail címe',
        'submitter_user_id' => 'Kitöltő intranet felhasználó',
        'submitter_type' => 'Kitöltő típusa',
        'submission_hash' => 'Beküldött adatok SHA-256 hash',
        'hash_algorithm' => 'Hash algoritmus',
    ];

    private array $contextLabels = [
        'person_id' => 'Személy',
        'employment_relation_id' => 'Jogviszony',
        'packet_id' => 'Csomag',
        'packet_item_id' => 'Nyilatkozat',
        'submission_id' => 'Beküldés',
        'invitation_id' => 'Meghívó',
    ];

    public function rows(array $auditLogs): array
    {
        return array_map(fn($auditLog): array => $this->row($auditLog), $auditLogs);
    }

    public function row(object $auditLog): array
    {
        $payload = $this->decodePayload($auditLog->payload_json ?? null);
        $action = (string) ($auditLog->action ?? '');
        $entityType = (string) ($auditLog->entity_type ?? '');
        $entityLabel = $this->entityLabels[$entityType] ?? ($entityType !== '' ? $entityType : 'Esemény');
        $entityId = !empty($auditLog->entity_id) ? (int) $auditLog->entity_id : null;
        $tone = $this->toneForAction($action);

        return [
            'created_at' => (string) ($auditLog->created_at ?: '-'),
            'created_date' => $this->datePart((string) ($auditLog->created_at ?? '')),
            'created_time' => $this->timePart((string) ($auditLog->created_at ?? '')),
            'action' => $action,
            'title' => $this->actionLabels[$action] ?? ($action !== '' ? $this->humanizeKey($action) : '-'),
            'tone' => $tone,
            'icon' => $this->iconForAction($action),
            'scope' => [
                'label' => $entityLabel,
                'id' => $entityId,
                'text' => $entityLabel . ($entityId ? ' #' . $entityId : ''),
            ],
            'context' => $this->contextParts($auditLog),
            'note' => trim((string) ($auditLog->note ?? '')),
            'details' => $this->details($payload),
            'status' => [
                'old' => $this->status((string) ($auditLog->old_status ?? '')),
                'new' => $this->status((string) ($auditLog->new_status ?? '')),
            ],
            'actor' => $this->actor($auditLog),
            'technical' => $this->technicalDetails($auditLog),
        ];
    }

    private function contextParts(object $auditLog): array
    {
        $parts = [];

        foreach ($this->contextLabels as $field => $label) {
            if (empty($auditLog->{$field})) {
                continue;
            }

            if (
                in_array($field, ['person_id', 'packet_id', 'packet_item_id', 'submission_id', 'invitation_id'], true)
                && (int) $auditLog->{$field} === (int) ($auditLog->entity_id ?? 0)
            ) {
                continue;
            }

            $parts[] = [
                'label' => $label,
                'value' => '#' . (int) $auditLog->{$field},
            ];
        }

        return $parts;
    }

    private function details(array $payload): array
    {
        $details = [];

        foreach ($this->changedDetails($payload) as $detail) {
            $details[] = $detail;
        }

        foreach ($payload as $key => $value) {
            if (in_array((string) $key, ['actor_type', 'actor_label', 'actor_user_id', 'person_id', 'employment_relation_id', 'packet_id', 'packet_item_id', 'submission_id', 'invitation_id', 'old', 'new'], true)) {
                continue;
            }

            if (is_array($value) && $this->isAssociative($value) && !isset($this->payloadLabels[$key])) {
                continue;
            }

            $details[] = [
                'label' => $this->payloadLabels[$key] ?? $this->humanizeKey((string) $key),
                'value' => $this->formatValue($value),
            ];
        }

        return $this->deduplicateDetails($details);
    }

    private function changedDetails(array $payload): array
    {
        $old = is_array($payload['old'] ?? null) ? $payload['old'] : null;
        $new = is_array($payload['new'] ?? null) ? $payload['new'] : null;

        if ($old === null && $new === null) {
            return [];
        }

        $keys = array_unique(array_merge(array_keys($old ?? []), array_keys($new ?? [])));
        $details = [];

        foreach ($keys as $key) {
            $oldValue = $old[$key] ?? null;
            $newValue = $new[$key] ?? null;

            if ($this->formatValue($oldValue) === $this->formatValue($newValue)) {
                continue;
            }

            $details[] = [
                'label' => $this->payloadLabels[$key] ?? $this->humanizeKey((string) $key),
                'value' => $this->formatValue($oldValue) . ' -> ' . $this->formatValue($newValue),
            ];
        }

        return $details;
    }

    private function status(string $status): ?array
    {
        if ($status === '') {
            return null;
        }

        [$label, $class] = $this->statusLabels[$status] ?? [$this->humanizeKey($status), 'secondary'];

        return [
            'label' => $label,
            'class' => $class,
        ];
    }

    private function actor(object $auditLog): array
    {
        $actorType = (string) ($auditLog->actor_type ?? '');
        $label = trim((string) ($auditLog->actor_label ?? ''));

        if ($label === '' && !empty($auditLog->actor_user_id)) {
            $label = 'Felhasználó #' . (int) $auditLog->actor_user_id;
        }

        if ($label === '') {
            $label = $this->actorTypeLabel($actorType);
        }

        return [
            'label' => $label !== '' ? $label : '-',
            'type' => $this->actorTypeLabel($actorType),
            'class' => $this->actorClass($actorType),
        ];
    }

    private function actorTypeLabel(string $actorType): string
    {
        return [
            'candidate' => 'Kitöltő',
            'employee' => 'Munkavállaló',
            'admin_user' => 'Admin',
            'system' => 'Rendszer',
            'payroll' => 'Munkaügy',
            'recruiter' => 'Toborzó',
        ][$actorType] ?? ($actorType !== '' ? $this->humanizeKey($actorType) : 'Rendszer');
    }

    private function actorClass(string $actorType): string
    {
        return [
            'candidate' => 'info',
            'employee' => 'success',
            'admin_user' => 'primary',
            'system' => 'secondary',
            'payroll' => 'success',
            'recruiter' => 'warning',
        ][$actorType] ?? 'secondary';
    }

    private function technicalDetails(object $auditLog): array
    {
        $details = [];

        if (!empty($auditLog->ip_address)) {
            $details[] = 'IP: ' . (string) $auditLog->ip_address;
        }

        return $details;
    }

    private function toneForAction(string $action): string
    {
        if (str_contains($action, 'failed') || str_contains($action, 'rejected') || str_contains($action, 'revoked') || str_contains($action, 'cancelled')) {
            return 'danger';
        }

        if (str_contains($action, 'accepted') || str_contains($action, 'approved') || str_contains($action, 'completed') || str_contains($action, 'succeeded')) {
            return 'success';
        }

        if (str_contains($action, 'reopened') || str_contains($action, 'regenerated') || str_contains($action, 'closed')) {
            return 'warning';
        }

        if (str_contains($action, 'email') || str_contains($action, 'document') || str_contains($action, 'invitation')) {
            return 'info';
        }

        return 'primary';
    }

    private function iconForAction(string $action): string
    {
        if (str_contains($action, 'email')) {
            return 'fa-envelope';
        }

        if (str_contains($action, 'document')) {
            return 'fa-file-alt';
        }

        if (str_contains($action, 'invitation')) {
            return 'fa-link';
        }

        if (str_contains($action, 'person')) {
            return 'fa-user';
        }

        if (str_contains($action, 'relation')) {
            return 'fa-briefcase';
        }

        if (str_contains($action, 'rejected') || str_contains($action, 'failed')) {
            return 'fa-times';
        }

        if (str_contains($action, 'accepted') || str_contains($action, 'approved') || str_contains($action, 'succeeded')) {
            return 'fa-check';
        }

        return 'fa-history';
    }

    private function decodePayload($raw): array
    {
        if ($raw instanceof \stdClass) {
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
    }

    private function formatValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'igen' : 'nem';
        }

        if (is_array($value)) {
            return $this->formatArray($value);
        }

        if ($value === null || $value === '') {
            return '-';
        }

        $text = (string) $value;

        return $this->truncate($text, 140);
    }

    private function formatArray(array $value): string
    {
        if ($value === []) {
            return '-';
        }

        if (!$this->isAssociative($value)) {
            $parts = [];

            foreach ($value as $item) {
                if (is_scalar($item)) {
                    $parts[] = (string) $item;
                    continue;
                }

                if (is_array($item)) {
                    $parts[] = (string) ($item['template_name'] ?? $item['name'] ?? $item['email'] ?? $item['id'] ?? '-');
                }
            }

            return $this->truncate(implode(', ', array_filter($parts)), 180) ?: '-';
        }

        $parts = [];

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                continue;
            }

            $parts[] = ($this->payloadLabels[$key] ?? $this->humanizeKey((string) $key)) . ': ' . $this->formatValue($item);
        }

        return $this->truncate(implode(', ', $parts), 180) ?: '-';
    }

    private function datePart(string $value): string
    {
        $timestamp = strtotime($value);

        return $timestamp ? date('Y.m.d.', $timestamp) : ($value ?: '-');
    }

    private function timePart(string $value): string
    {
        $timestamp = strtotime($value);

        return $timestamp ? date('H:i:s', $timestamp) : '';
    }

    private function humanizeKey(string $key): string
    {
        $label = str_replace(['_', '-'], ' ', $key);

        if (function_exists('mb_convert_case')) {
            return mb_convert_case($label, MB_CASE_TITLE, 'UTF-8');
        }

        return ucfirst($label);
    }

    private function truncate(string $text, int $length): string
    {
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 3) . '...' : $text;
        }

        return strlen($text) > $length ? substr($text, 0, $length - 3) . '...' : $text;
    }

    private function isAssociative(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function deduplicateDetails(array $details): array
    {
        $seen = [];
        $result = [];

        foreach ($details as $detail) {
            $key = (string) ($detail['label'] ?? '') . ':' . (string) ($detail['value'] ?? '');

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $detail;
        }

        return $result;
    }
}
