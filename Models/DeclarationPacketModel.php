<?php

namespace App\Modules\Declarations\Models;

use App\Modules\Declarations\Entities\DeclarationPacket;
use CodeIgniter\Model;

class DeclarationPacketModel extends Model
{
    protected $table = 'declaration_packets';
    protected $primaryKey = 'id';
    protected $returnType = DeclarationPacket::class;

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $allowedFields = [
        'person_id',
        'company_id',
        'primary_recruiter_user_id',
        'status',
        'flow_type',
        'tax_year',
        'process_date',
        'created_by_user_id',
        'sent_at',
        'completed_at',
        'cancelled_at',
    ];

    protected $validationRules = [
        'person_id' => 'required|is_natural_no_zero',
        'company_id' => 'required|is_natural_no_zero',
        'primary_recruiter_user_id' => 'permit_empty|is_natural_no_zero',
        'status' => 'required|max_length[30]',
        'flow_type' => 'permit_empty|max_length[50]',
        'tax_year' => 'permit_empty|integer',
        'process_date' => 'permit_empty|valid_date[Y-m-d]',
    ];

    public function findByPersonId(int $personId): array
    {
        return $this->where('person_id', $personId)
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    public function findOpenBlockingByPerson(int $personId, ?int $excludePacketId = null)
    {
        $builder = $this
            ->where('person_id', $personId)
            ->whereIn('status', [
                DeclarationPacket::STATUS_DRAFT,
                DeclarationPacket::STATUS_SENT,
                DeclarationPacket::STATUS_IN_PROGRESS,
                DeclarationPacket::STATUS_SUBMITTED,
                DeclarationPacket::STATUS_APPROVED,
                DeclarationPacket::STATUS_COMPLETED,
            ]);

        if ($excludePacketId !== null) {
            $builder->where('id !=', $excludePacketId);
        }

        return $builder
            ->orderBy('id', 'DESC')
            ->first();
    }

    public function markAsSent(int $packetId): bool
    {
        return $this->update($packetId, [
            'status' => DeclarationPacket::STATUS_SENT,
            'sent_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function markAsInProgress(int $packetId): bool
    {
        return $this->update($packetId, [
            'status' => DeclarationPacket::STATUS_IN_PROGRESS,
            'completed_at' => null,
        ]);
    }

    public function markAsSubmitted(int $packetId): bool
    {
        return $this->update($packetId, [
            'status' => DeclarationPacket::STATUS_SUBMITTED,
            'completed_at' => null,
        ]);
    }

    public function markAsApproved(int $packetId): bool
    {
        return $this->update($packetId, [
            'status' => DeclarationPacket::STATUS_APPROVED,
        ]);
    }

    public function markAsClosed(int $packetId): bool
    {
        return $this->update($packetId, [
            'status' => DeclarationPacket::STATUS_CLOSED,
            'completed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function markAsCompleted(int $packetId): bool
    {
        // Backward compatibility: a régi completed jelentését az új approved státusz váltja ki.
        return $this->markAsApproved($packetId);
    }

}
