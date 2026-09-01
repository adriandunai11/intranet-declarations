<?php

namespace App\Modules\Declarations\Models;

use App\Modules\Declarations\Entities\DeclarationSubmission;
use CodeIgniter\Model;

class DeclarationSubmissionModel extends Model
{
    protected $table = 'declaration_submissions';
    protected $primaryKey = 'id';
    protected $returnType = DeclarationSubmission::class;

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $allowedFields = [
        'packet_id',
        'packet_item_id',
        'template_id',
        'person_id',
        'status',
        'submitter_type',
        'submitter_user_id',
        'submitter_label',
        'submitter_email',
        'submitter_ip_address',
        'submitter_user_agent',
        'data_json',
        'submission_hash',
        'hash_algorithm',
        'submitted_at',
        'accepted_at',
        'rejected_at',
    ];

    protected $validationRules = [
        'packet_id' => 'required|is_natural_no_zero',
        'packet_item_id' => 'required|is_natural_no_zero',
        'template_id' => 'required|is_natural_no_zero',
        'person_id' => 'required|is_natural_no_zero',
        'status' => 'required|max_length[30]',
    ];

    public function findByPacketItemId(int $packetItemId)
    {
        return $this->where('packet_item_id', $packetItemId)->first();
    }

    public function findByPacketIdIndexedByItemId(int $packetId): array
    {
        $submissions = $this->where('packet_id', $packetId)
            ->orderBy('id', 'ASC')
            ->findAll();

        $indexed = [];

        foreach ($submissions as $submission) {
            $indexed[(int) $submission->packet_item_id] = $submission;
        }

        return $indexed;
    }

    public function markAsAccepted(int $submissionId): bool
    {
        return $this->update($submissionId, [
            'status' => DeclarationSubmission::STATUS_ACCEPTED,
            'accepted_at' => date('Y-m-d H:i:s'),
            'rejected_at' => null,
        ]);
    }

    public function markAsRejected(int $submissionId): bool
    {
        return $this->update($submissionId, [
            'status' => DeclarationSubmission::STATUS_REJECTED,
            'rejected_at' => date('Y-m-d H:i:s'),
            'accepted_at' => null,
        ]);
    }

    public function markAsSubmittedAgain(int $submissionId, string $dataJson, array $evidence = []): bool
    {
        return $this->update($submissionId, array_merge([
            'status' => DeclarationSubmission::STATUS_SUBMITTED,
            'data_json' => $dataJson,
            'submitted_at' => date('Y-m-d H:i:s'),
            'accepted_at' => null,
            'rejected_at' => null,
        ], $evidence));
    }
}
