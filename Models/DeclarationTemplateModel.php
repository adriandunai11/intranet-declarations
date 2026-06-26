<?php

namespace App\Modules\Declarations\Models;

use App\Modules\Declarations\Entities\DeclarationTemplate;
use CodeIgniter\Model;

class DeclarationTemplateModel extends Model
{
    protected $table = 'declaration_templates';
    protected $primaryKey = 'id';
    protected $returnType = DeclarationTemplate::class;

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $allowedFields = [
        'code',
        'name',
        'category',
        'declaration_group',
        'tax_year',
        'version',
        'template_file',
        'effective_from',
        'effective_to',
        'parent_template_id',
        'renewal_policy',
        'required_policy',
        'review_role',
        'needs_signature',
        'is_candidate_selectable',
        'company_scope',
        'class_name',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $validationRules = [
        'code' => 'required|max_length[100]',
        'name' => 'required|max_length[190]',
        'category' => 'required|max_length[80]',
        'declaration_group' => 'required|max_length[50]',
        'tax_year' => 'permit_empty|integer',
        'version' => 'required|max_length[30]',
        'template_file' => 'permit_empty|max_length[255]',
        'effective_from' => 'permit_empty|valid_date[Y-m-d]',
        'effective_to' => 'permit_empty|valid_date[Y-m-d]',
        'parent_template_id' => 'permit_empty|is_natural_no_zero',
        'renewal_policy' => 'required|max_length[50]',
        'required_policy' => 'required|max_length[50]',
        'review_role' => 'required|max_length[50]',
        'needs_signature' => 'permit_empty|in_list[0,1]',
        'is_candidate_selectable' => 'permit_empty|in_list[0,1]',
        'company_scope' => 'required|max_length[50]',
        'class_name' => 'permit_empty|max_length[255]',
        'sort_order' => 'permit_empty|integer',
        'is_active' => 'permit_empty|in_list[0,1]',
    ];

    public function findActive(): array
    {
        return $this->where('is_active', 1)
            ->groupStart()
                ->where('effective_from', null)
                ->orWhere('effective_from <=', date('Y-m-d'))
            ->groupEnd()
            ->groupStart()
                ->where('effective_to', null)
                ->orWhere('effective_to >=', date('Y-m-d'))
            ->groupEnd()
            ->orderBy('category', 'ASC')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    public function findActiveForYear(?int $taxYear = null): array
    {
        $builder = $this->where('is_active', 1)
            ->groupStart()
                ->where('effective_from', null)
                ->orWhere('effective_from <=', date('Y-m-d'))
            ->groupEnd()
            ->groupStart()
                ->where('effective_to', null)
                ->orWhere('effective_to >=', date('Y-m-d'))
            ->groupEnd();

        if ($taxYear !== null) {
            $builder->groupStart()
                ->where('tax_year', $taxYear)
                ->orWhere('tax_year', null)
                ->groupEnd();
        }

        return $builder
            ->orderBy('category', 'ASC')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    public function findDefaultOnboardingTemplates(?int $taxYear = null): array
    {
        $builder = $this->where('is_active', 1)
            ->whereIn('declaration_group', [
                DeclarationTemplate::GROUP_EMPLOYMENT,
                DeclarationTemplate::GROUP_PERSONAL_DATA,
            ])
            ->where('required_policy', DeclarationTemplate::REQUIRED_ALWAYS)
            ->groupStart()
                ->where('effective_from', null)
                ->orWhere('effective_from <=', date('Y-m-d'))
            ->groupEnd()
            ->groupStart()
                ->where('effective_to', null)
                ->orWhere('effective_to >=', date('Y-m-d'))
            ->groupEnd();

        if ($taxYear !== null) {
            $builder->groupStart()
                ->where('tax_year', $taxYear)
                ->orWhere('tax_year', null)
                ->groupEnd();
        }

        return $builder
            ->orderBy('sort_order', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    public function findCandidateSelectableTaxTemplates(?int $taxYear = null): array
    {
        $builder = $this->where('is_active', 1)
            ->where('declaration_group', DeclarationTemplate::GROUP_TAX)
            ->where('is_candidate_selectable', 1)
            ->groupStart()
                ->where('effective_from', null)
                ->orWhere('effective_from <=', date('Y-m-d'))
            ->groupEnd()
            ->groupStart()
                ->where('effective_to', null)
                ->orWhere('effective_to >=', date('Y-m-d'))
            ->groupEnd();

        if ($taxYear !== null) {
            $builder->groupStart()
                ->where('tax_year', $taxYear)
                ->orWhere('tax_year', null)
                ->groupEnd();
        }

        return $builder
            ->orderBy('sort_order', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    public function findByCode(string $code, ?int $taxYear = null): ?DeclarationTemplate
    {
        $builder = $this->where('code', $code);

        if ($taxYear !== null) {
            $builder->where('tax_year', $taxYear);
        }

        $template = $builder
            ->orderBy('id', 'DESC')
            ->first();

        return $template ?: null;
    }
}
