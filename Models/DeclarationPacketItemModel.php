<?php

namespace App\Modules\Declarations\Models;

use App\Modules\Declarations\Entities\DeclarationPacketItem;
use CodeIgniter\Model;

class DeclarationPacketItemModel extends Model
{
    protected $table = 'declaration_packet_items';
    protected $primaryKey = 'id';
    protected $returnType = DeclarationPacketItem::class;

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $allowedFields = [
        'packet_id',
        'template_id',
        'template_code_snapshot',
        'template_name_snapshot',
        'template_version_snapshot',
        'template_file_snapshot',
        'status',
        'sort_order',
        'completed_at',
        'accepted_at',
        'rejected_at',
        'review_note',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected $validationRules = [
        'packet_id' => 'required|is_natural_no_zero',
        'template_id' => 'required|is_natural_no_zero',
        'status' => 'required|max_length[30]',
        'sort_order' => 'permit_empty|integer',
    ];

    public function findByPacketId(int $packetId): array
    {
        return $this->where('packet_id', $packetId)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    public function findWithTemplatesByPacketId(int $packetId): array
    {
        return $this->select([
            'declaration_packet_items.*',
            'COALESCE(declaration_packet_items.template_code_snapshot, declaration_templates.code) AS template_code',
            'COALESCE(declaration_packet_items.template_name_snapshot, declaration_templates.name) AS template_name',
            'COALESCE(declaration_packet_items.template_version_snapshot, declaration_templates.version) AS template_version',
            'COALESCE(declaration_packet_items.template_file_snapshot, declaration_templates.template_file) AS template_file',
            'declaration_templates.code AS current_template_code',
            'declaration_templates.name AS current_template_name',
            'declaration_templates.category AS template_category',
            'declaration_templates.declaration_group AS template_declaration_group',
            'declaration_templates.review_role AS template_review_role',
            'declaration_templates.needs_signature AS template_needs_signature',
            'declaration_templates.is_candidate_selectable AS template_is_candidate_selectable',
            'declaration_templates.tax_year AS template_tax_year',
            'declaration_templates.required_policy AS template_required_policy',
        ])
            ->join('declaration_templates', 'declaration_templates.id = declaration_packet_items.template_id', 'left')
            ->where('declaration_packet_items.packet_id', $packetId)
            ->orderBy('declaration_packet_items.sort_order', 'ASC')
            ->orderBy('declaration_packet_items.id', 'ASC')
            ->findAll();
    }

    public function findByPacketAndTemplateId(int $packetId, int $templateId)
    {
        return $this->where('packet_id', $packetId)
            ->where('template_id', $templateId)
            ->first();
    }

    public function nextSortOrderForPacket(int $packetId): int
    {
        $row = $this->selectMax('sort_order', 'max_sort_order')
            ->where('packet_id', $packetId)
            ->first();

        $max = $row ? (int) ($row->max_sort_order ?? 0) : 0;

        return $max + 10;
    }

    public function markAsCompleted(int $itemId): bool
    {
        return $this->update($itemId, [
            'status' => DeclarationPacketItem::STATUS_COMPLETED,
            'completed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function markAsAccepted(int $itemId, ?int $reviewedByUserId = null): bool
    {
        return $this->update($itemId, [
            'status' => DeclarationPacketItem::STATUS_ACCEPTED,
            'accepted_at' => date('Y-m-d H:i:s'),
            'rejected_at' => null,
            'review_note' => null,
            'reviewed_by_user_id' => $reviewedByUserId,
            'reviewed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function markAsRejected(int $itemId, string $reviewNote, ?int $reviewedByUserId = null): bool
    {
        return $this->update($itemId, [
            'status' => DeclarationPacketItem::STATUS_REJECTED,
            'rejected_at' => date('Y-m-d H:i:s'),
            'accepted_at' => null,
            'review_note' => $reviewNote,
            'reviewed_by_user_id' => $reviewedByUserId,
            'reviewed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function resetReviewForResubmission(int $itemId): bool
    {
        return $this->update($itemId, [
            'status' => DeclarationPacketItem::STATUS_COMPLETED,
            'completed_at' => date('Y-m-d H:i:s'),
            'accepted_at' => null,
            'rejected_at' => null,
            'review_note' => null,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
        ]);
    }

    public function countNotAcceptedByPacketId(int $packetId): int
    {
        return $this->where('packet_id', $packetId)
            ->where('status !=', DeclarationPacketItem::STATUS_ACCEPTED)
            ->countAllResults();
    }
}
