<?php

namespace App\Modules\Declarations\Services;

use App\Models\UserModel;

class AuditActorResolverService
{
    protected UserModel $userModel;
    protected RecruiterService $recruiterService;
    protected array $userLabelCache = [];

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->recruiterService = new RecruiterService();
    }

    public function enrichAuditLogs(array $auditLogs): array
    {
        foreach ($auditLogs as $auditLog) {
            if (!is_object($auditLog)) {
                continue;
            }

            $actorLabel = trim((string) ($auditLog->actor_label ?? ''));
            $actorUserId = (int) ($auditLog->actor_user_id ?? 0);

            if ($actorUserId <= 0) {
                $actorUserId = $this->userIdFromFallbackLabel($actorLabel);
            }

            if ($actorUserId > 0 && ($actorLabel === '' || $this->isFallbackUserLabel($actorLabel, $actorUserId))) {
                $auditLog->actor_label = $this->userLabel($actorUserId);
            }

            $payload = $this->decodePayload($auditLog->payload_json ?? null);

            if ($payload === []) {
                continue;
            }

            $payload = $this->enrichPayloadUserReferences($payload);
            $auditLog->payload_json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        }

        return $auditLogs;
    }

    private function enrichPayloadUserReferences(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->enrichPayloadUserReferences($value);
                continue;
            }

            if (!$this->isUserReferenceField((string) $key) || !is_numeric($value)) {
                continue;
            }

            $payload[$key] = $this->userLabel((int) $value);
        }

        return $payload;
    }

    private function isUserReferenceField(string $field): bool
    {
        return in_array($field, [
            'actor_user_id',
            'primary_recruiter_user_id',
            'reviewed_by_user_id',
            'created_by_user_id',
            'submitter_user_id',
            'updated_by_user_id',
            'closed_by_user_id',
            'reopened_by_user_id',
        ], true);
    }

    private function isFallbackUserLabel(string $label, int $userId): bool
    {
        return in_array($label, [
            'Felhasználó #' . $userId,
            'User #' . $userId,
        ], true);
    }

    private function userIdFromFallbackLabel(string $label): int
    {
        if (!preg_match('/^(?:Felhasználó|User)\s+#(\d+)$/u', $label, $matches)) {
            return 0;
        }

        return (int) $matches[1];
    }

    private function userLabel(int $userId): string
    {
        if ($userId <= 0) {
            return '-';
        }

        if (isset($this->userLabelCache[$userId])) {
            return $this->userLabelCache[$userId];
        }

        try {
            $user = $this->userModel->find($userId);
        } catch (\Throwable $e) {
            $user = null;
        }

        if (!$user) {
            return $this->userLabelCache[$userId] = 'Felhasználó #' . $userId;
        }

        $label = $this->recruiterService->getDisplayName($user);
        $antraId = $this->recruiterService->getAntraId($user);

        if ($antraId !== '') {
            $label .= ' (' . $antraId . ')';
        }

        return $this->userLabelCache[$userId] = $label;
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
}
