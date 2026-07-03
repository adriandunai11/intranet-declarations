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

    private array $payloadLabels = [
        'template_name' => 'Nyilatkozat',
        'template_code' => 'Kód',
        'template_id' => 'Sablon ID',
        'template_version' => 'Sablon verzió',
        'template_file' => 'Sablon fájl',
        'format' => 'Formátum',
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
        'submitted_length' => 'Megadott Antra hossza',
        'expected_present' => 'Rögzített Antra',
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
        $entityId = !empty($auditLog->entity_id) ? '#' . (int) $auditLog->entity_id : '';

        return [
            'created_at' => (string) ($auditLog->created_at ?: '-'),
            'title' => $this->actionLabels[$action] ?? ($action !== '' ? $action : '-'),
            'scope' => trim($entityLabel . ' ' . $entityId),
            'context' => $this->contextParts($auditLog),
            'details' => $this->details($auditLog, $payload),
            'old_status' => $this->status((string) ($auditLog->old_status ?? '')),
            'new_status' => $this->status((string) ($auditLog->new_status ?? '')),
            'actor' => $this->actorLabel($auditLog),
        ];
    }

    private function contextParts(object $auditLog): array
    {
        $parts = [];

        foreach ([
            'person_id' => 'személy',
            'employment_relation_id' => 'jogviszony',
            'packet_id' => 'csomag',
            'packet_item_id' => 'nyilatkozat',
            'submission_id' => 'beküldés',
            'invitation_id' => 'meghívó',
        ] as $field => $label) {
            if (empty($auditLog->{$field})) {
                continue;
            }

            if ($field === 'packet_id' && (int) $auditLog->{$field} === (int) ($auditLog->entity_id ?? 0)) {
                continue;
            }

            if ($field === 'packet_item_id' && (int) $auditLog->{$field} === (int) ($auditLog->entity_id ?? 0)) {
                continue;
            }

            $parts[] = $label . ' #' . (int) $auditLog->{$field};
        }

        return $parts;
    }

    private function details(object $auditLog, array $payload): array
    {
        $details = [];

        if (!empty($auditLog->note)) {
            $details[] = (string) $auditLog->note;
        }

        foreach ($this->payloadLabels as $key => $label) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $details[] = $label . ': ' . $this->formatValue($payload[$key]);
        }

        return $details;
    }

    private function status(string $status): ?array
    {
        if ($status === '') {
            return null;
        }

        [$label, $class] = $this->statusLabels[$status] ?? [$status, 'secondary'];

        return [
            'label' => $label,
            'class' => $class,
        ];
    }

    private function actorLabel(object $auditLog): string
    {
        if (!empty($auditLog->actor_label)) {
            return (string) $auditLog->actor_label;
        }

        if (!empty($auditLog->actor_user_id)) {
            return 'Felhasználó #' . (int) $auditLog->actor_user_id;
        }

        return (string) ($auditLog->actor_type ?: '-');
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
            $parts = [];

            foreach ($value as $item) {
                if (is_scalar($item)) {
                    $parts[] = (string) $item;
                    continue;
                }

                if (is_array($item)) {
                    $parts[] = (string) ($item['template_name'] ?? $item['name'] ?? $item['id'] ?? '-');
                }
            }

            return $parts !== [] ? implode(', ', array_slice($parts, 0, 5)) : '-';
        }

        if ($value === null || $value === '') {
            return '-';
        }

        $text = (string) $value;

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text) > 110 ? mb_substr($text, 0, 107) . '...' : $text;
        }

        return strlen($text) > 110 ? substr($text, 0, 107) . '...' : $text;
    }
}
