<?php

namespace App\Modules\Declarations\Services;

class PacketReviewAuthorizationService
{
    public const REVIEW_ROLE_RECRUITER = 'recruiter';
    public const REVIEW_ROLE_PAYROLL = 'payroll';

    public function canReviewItem(object $packet, ?object $relation, object $item): bool
    {
        if ($this->canAdminOverride()) {
            return true;
        }

        $reviewRole = (string) ($item->template_review_role ?? '');

        if ($reviewRole === self::REVIEW_ROLE_PAYROLL) {
            return $this->canReviewPayroll();
        }

        if ($reviewRole === self::REVIEW_ROLE_RECRUITER) {
            return $this->canReviewRecruiter($relation);
        }

        return false;
    }

    private function canAdminOverride(): bool
    {
        return function_exists('hasPermissions')
            && hasPermissions('declarations_admin_override');
    }

    public function assertCanReviewItem(object $packet, ?object $relation, object $item): void
    {
        if (!$this->canReviewItem($packet, $relation, $item)) {
            throw new \RuntimeException('Nincs jogosultságod ennek a nyilatkozatnak az ellenőrzéséhez.');
        }
    }

    private function canReviewPayroll(): bool
    {
        return function_exists('hasPermissions')
            && hasPermissions('declarations_review_payroll');
    }

    private function canReviewRecruiter(?object $relation): bool
    {
        if (!function_exists('hasPermissions') || !hasPermissions('declarations_review_recruiter')) {
            return false;
        }

        if (!$relation || empty($relation->primary_recruiter_user_id)) {
            return false;
        }

        $loggedUserId = function_exists('logged') ? (int) logged('id') : 0;

        return $loggedUserId > 0
            && (int) $relation->primary_recruiter_user_id === $loggedUserId;
    }
}