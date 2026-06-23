<?php

namespace App\Modules\Declarations\Entities;

use CodeIgniter\Entity\Entity;

class DeclarationTemplate extends Entity
{
    public const CATEGORY_TAX_ADVANCE = 'tax_advance';
    public const CATEGORY_PAYROLL = 'payroll';
    public const CATEGORY_ONBOARDING = 'onboarding';
    public const CATEGORY_GDPR = 'gdpr';
    public const CATEGORY_WORK_SAFETY = 'work_safety';
    public const CATEGORY_COMPANY_POLICY = 'company_policy';
    public const CATEGORY_TRAVEL_COST = 'travel_cost';
    public const CATEGORY_EMPLOYMENT = 'employment';

    public const GROUP_EMPLOYMENT = 'employment';
    public const GROUP_TAX = 'tax';
    public const GROUP_PERSONAL_DATA = 'personal_data';

    public const REVIEW_ROLE_RECRUITER = 'recruiter';
    public const REVIEW_ROLE_PAYROLL = 'payroll';
    public const REVIEW_ROLE_NONE = 'none';

    public const RENEWAL_YEARLY = 'yearly';
    public const RENEWAL_PER_RELATION = 'per_relation';
    public const RENEWAL_WHEN_CHANGED = 'when_changed';
    public const RENEWAL_UNTIL_REVOKED = 'until_revoked';

    public const REQUIRED_ALWAYS = 'always';
    public const REQUIRED_OPTIONAL = 'optional';
    public const REQUIRED_CONDITIONAL = 'conditional';

    protected $dates = [
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'tax_year' => '?integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'needs_signature' => 'boolean',
        'is_candidate_selectable' => 'boolean',
    ];

    public function displayName(): string
    {
        $name = (string) ($this->attributes['name'] ?? '');

        if (!empty($this->attributes['tax_year'])) {
            $name .= ' (' . $this->attributes['tax_year'] . ')';
        }

        return trim($name);
    }

    public function isActive(): bool
    {
        return (bool) ($this->attributes['is_active'] ?? false);
    }

    public function isTaxDeclaration(): bool
    {
        return ($this->attributes['declaration_group'] ?? null) === self::GROUP_TAX;
    }

    public function isRecruiterReviewed(): bool
    {
        return ($this->attributes['review_role'] ?? null) === self::REVIEW_ROLE_RECRUITER;
    }

    public function isPayrollReviewed(): bool
    {
        return ($this->attributes['review_role'] ?? null) === self::REVIEW_ROLE_PAYROLL;
    }

    public function needsSignature(): bool
    {
        return (bool) ($this->attributes['needs_signature'] ?? false);
    }
}