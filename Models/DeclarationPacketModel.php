<?php

namespace App\Modules\Declarations\Models;

use App\Modules\Declarations\Entities\DeclarationPacket;
use App\Modules\Declarations\Entities\EmploymentRelation;
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
        'employment_relation_id',
        'company_id',
        'status',
        'tax_year',
        'created_by_user_id',
        'sent_at',
        'completed_at',
        'cancelled_at',
    ];

    protected $validationRules = [
        'person_id' => 'required|is_natural_no_zero',
        'employment_relation_id' => 'required|is_natural_no_zero',
        'company_id' => 'required|is_natural_no_zero',
        'status' => 'required|max_length[30]',
        'tax_year' => 'permit_empty|integer',
    ];

    public function findByRelationId(int $relationId): array
    {
        return $this->where('employment_relation_id', $relationId)
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    public function findByPersonId(int $personId): array
    {
        return $this->where('person_id', $personId)
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    public function findActiveByRelationAndTaxYear(int $relationId, ?int $taxYear)
    {
        $builder = $this->where('employment_relation_id', $relationId)
            ->whereIn('status', [
                DeclarationPacket::STATUS_DRAFT,
                DeclarationPacket::STATUS_SENT,
                DeclarationPacket::STATUS_IN_PROGRESS,
                DeclarationPacket::STATUS_SUBMITTED,
                DeclarationPacket::STATUS_APPROVED,
                DeclarationPacket::STATUS_COMPLETED,
            ]);

        if ($taxYear === null) {
            $builder->where('tax_year', null);
        } else {
            $builder->where('tax_year', $taxYear);
        }

        return $builder->orderBy('id', 'DESC')->first();
    }

    public function findBlockingByPersonCompanyAndTaxYearForOpenRelations(
        int $personId,
        int $companyId,
        int $taxYear,
        ?int $excludePacketId = null
    ) {
        $builder = $this
            ->select('declaration_packets.*')
            ->join(
                'declaration_employment_relations',
                'declaration_employment_relations.id = declaration_packets.employment_relation_id',
                'inner'
            )
            ->where('declaration_packets.person_id', $personId)
            ->where('declaration_packets.company_id', $companyId)
            ->groupStart()
                ->where('declaration_packets.tax_year', $taxYear)
                ->orWhere('declaration_packets.tax_year', null)
            ->groupEnd()
            ->whereIn('declaration_packets.status', [
                DeclarationPacket::STATUS_DRAFT,
                DeclarationPacket::STATUS_SENT,
                DeclarationPacket::STATUS_IN_PROGRESS,
                DeclarationPacket::STATUS_SUBMITTED,
                DeclarationPacket::STATUS_APPROVED,
                DeclarationPacket::STATUS_COMPLETED,
                DeclarationPacket::STATUS_CLOSED,
            ])
            ->whereNotIn('declaration_employment_relations.status', [
                EmploymentRelation::STATUS_CLOSED,
                EmploymentRelation::STATUS_CANCELLED,
            ]);

        if ($excludePacketId !== null) {
            $builder->where('declaration_packets.id !=', $excludePacketId);
        }

        return $builder
            ->orderBy('declaration_packets.id', 'DESC')
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
        ]);
    }

    public function markAsSubmitted(int $packetId): bool
    {
        return $this->update($packetId, [
            'status' => DeclarationPacket::STATUS_SUBMITTED,
        ]);
    }

    public function markAsApproved(int $packetId): bool
    {
        return $this->update($packetId, [
            'status' => DeclarationPacket::STATUS_APPROVED,
            'completed_at' => date('Y-m-d H:i:s'),
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
