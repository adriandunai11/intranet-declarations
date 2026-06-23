<?php

namespace App\Modules\Declarations\Services\Public;

use App\Modules\Declarations\Entities\DeclarationInvitation;
use App\Modules\Declarations\Models\DeclarationInvitationModel;
use App\Modules\Declarations\Models\DeclarationPacketModel;
use App\Modules\Declarations\Models\PersonModel;
use App\Modules\Declarations\Services\InvitationTokenService;

class InvitationContextService
{
    protected InvitationTokenService $tokenService;
    protected DeclarationInvitationModel $invitationModel;
    protected DeclarationPacketModel $packetModel;
    protected PersonModel $personModel;
    protected PacketWorkflowService $workflowService;

    public function __construct()
    {
        $this->tokenService = new InvitationTokenService();
        $this->invitationModel = new DeclarationInvitationModel();
        $this->packetModel = new DeclarationPacketModel();
        $this->personModel = new PersonModel();
        $this->workflowService = new PacketWorkflowService();
    }

    public function resolve(string $token): ?InvitationContext
    {
        $tokenHash = $this->tokenService->hashToken($token);
        $invitation = $this->invitationModel->findByTokenHash($tokenHash);

        if (!$invitation || $invitation->isExpired()) {
            return null;
        }

        $packet = $this->packetModel->find((int) $invitation->packet_id);

        if (!$packet) {
            return null;
        }

        $person = $this->personModel->find((int) $packet->person_id);

        return new InvitationContext($token, $invitation, $packet, $person ?: null);
    }

    public function resolveOrFail(string $token): InvitationContext
    {
        $context = $this->resolve($token);

        if ($context) {
            return $context;
        }

        $tokenHash = $this->tokenService->hashToken($token);
        $invitation = $this->invitationModel->findAnyByTokenHash($tokenHash);

        if ($invitation) {
            if ($invitation->isExpired()) {
                throw new \RuntimeException('A meghívó link lejárt. Kérjük, kérjen új dokumentumkitöltő linket.');
            }

            if ((string) $invitation->status === DeclarationInvitation::STATUS_REVOKED) {
                throw new \RuntimeException('Ez a meghívó link már nem használható, mert új link került kiküldésre. Kérjük, a legutóbb kapott e-mailben található linket nyissa meg.');
            }

            if ((string) $invitation->status === DeclarationInvitation::STATUS_COMPLETED) {
                throw new \RuntimeException('Ez a dokumentumkitöltő link már lezárt folyamatra mutat. Ha javítás szükséges, kérjen új meghívó linket.');
            }

            if ((string) $invitation->status === DeclarationInvitation::STATUS_CANCELLED) {
                throw new \RuntimeException('Ez a meghívó link már nem használható. Kérjük, kérjen új dokumentumkitöltő linket.');
            }
        }

        throw new \RuntimeException('A meghívó link nem érvényes vagy már nem használható.');
    }

    public function markOpened(InvitationContext $context): void
    {
        $this->workflowService->markInvitationOpened($context);
    }
}
