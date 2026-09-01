<?php

namespace App\Modules\Declarations\Models;

use App\Modules\Declarations\Entities\DeclarationInvitation;
use CodeIgniter\Model;

class DeclarationInvitationModel extends Model
{
    protected $table = 'declaration_invitations';
    protected $primaryKey = 'id';
    protected $returnType = DeclarationInvitation::class;
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $allowedFields = [
        'person_id',
        'packet_id',
        'email',
        'token_hash',
        'status',
        'expires_at',
        'sent_at',
        'opened_at',
        'completed_at',
        'revoked_at',
    ];

    public function findActiveByPacketId(int $packetId)
    {
        return $this->where('packet_id', $packetId)
            ->whereIn('status', [
                DeclarationInvitation::STATUS_CREATED,
                DeclarationInvitation::STATUS_SENT,
                DeclarationInvitation::STATUS_OPENED,
            ])
            ->orderBy('id', 'DESC')
            ->first();
    }

    public function findAnyByTokenHash(string $tokenHash)
    {
        return $this->where('token_hash', $tokenHash)
            ->orderBy('id', 'DESC')
            ->first();
    }

    public function findByTokenHash(string $tokenHash)
    {
        return $this->where('token_hash', $tokenHash)
            ->whereIn('status', [
                DeclarationInvitation::STATUS_CREATED,
                DeclarationInvitation::STATUS_SENT,
                DeclarationInvitation::STATUS_OPENED,
            ])
            ->first();
    }

    public function findLatestByPacketId(int $packetId)
    {
        return $this->where('packet_id', $packetId)
            ->orderBy('id', 'DESC')
            ->first();
    }

    public function revokeActiveByPacketId(int $packetId): bool
    {
        return $this->where('packet_id', $packetId)
            ->whereIn('status', [
                DeclarationInvitation::STATUS_CREATED,
                DeclarationInvitation::STATUS_SENT,
                DeclarationInvitation::STATUS_OPENED,
            ])
            ->set([
                'status' => DeclarationInvitation::STATUS_REVOKED,
                'revoked_at' => date('Y-m-d H:i:s'),
            ])
            ->update();
    }

    public function countActiveByPacketId(int $packetId): int
    {
        return $this->where('packet_id', $packetId)
            ->whereIn('status', [
                DeclarationInvitation::STATUS_CREATED,
                DeclarationInvitation::STATUS_SENT,
                DeclarationInvitation::STATUS_OPENED,
            ])
            ->countAllResults();
    }
}
