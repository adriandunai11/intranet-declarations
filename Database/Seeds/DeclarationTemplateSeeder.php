<?php

namespace App\Modules\Declarations\Database\Seeds;

use App\Modules\Declarations\Entities\DeclarationTemplate;
use CodeIgniter\Database\Seeder;

class DeclarationTemplateSeeder extends Seeder
{
    public function run()
    {
        $templates = [
            [
                'code' => 'absence_statement',
                'name' => 'Nyilatkozat kieső időről',
                'category' => DeclarationTemplate::CATEGORY_EMPLOYMENT,
                'declaration_group' => DeclarationTemplate::GROUP_EMPLOYMENT,
                'tax_year' => null,
                'version' => 'v1',
                'renewal_policy' => DeclarationTemplate::RENEWAL_PER_RELATION,
                'required_policy' => DeclarationTemplate::REQUIRED_ALWAYS,
                'review_role' => DeclarationTemplate::REVIEW_ROLE_RECRUITER,
                'needs_signature' => 0,
                'is_candidate_selectable' => 0,
                'company_scope' => 'global',
                'description' => 'Nem adóügyi beléptetési nyilatkozat kieső időről.',
                'sort_order' => 10,
                'is_active' => 1,
            ],
            [
                'code' => 'deduction_statement',
                'name' => 'Nyilatkozat letiltásról',
                'category' => DeclarationTemplate::CATEGORY_EMPLOYMENT,
                'declaration_group' => DeclarationTemplate::GROUP_EMPLOYMENT,
                'tax_year' => null,
                'version' => 'v1',
                'renewal_policy' => DeclarationTemplate::RENEWAL_PER_RELATION,
                'required_policy' => DeclarationTemplate::REQUIRED_ALWAYS,
                'review_role' => DeclarationTemplate::REVIEW_ROLE_RECRUITER,
                'needs_signature' => 0,
                'is_candidate_selectable' => 0,
                'company_scope' => 'global',
                'description' => 'Nem adóügyi beléptetési nyilatkozat letiltásról.',
                'sort_order' => 20,
                'is_active' => 1,
            ],
            [
                'code' => 'bank_account_statement',
                'name' => 'Nyilatkozat bankszámlaszámról',
                'category' => DeclarationTemplate::CATEGORY_PAYROLL,
                'declaration_group' => DeclarationTemplate::GROUP_EMPLOYMENT,
                'tax_year' => null,
                'version' => 'v1',
                'renewal_policy' => DeclarationTemplate::RENEWAL_WHEN_CHANGED,
                'required_policy' => DeclarationTemplate::REQUIRED_ALWAYS,
                'review_role' => DeclarationTemplate::REVIEW_ROLE_RECRUITER,
                'needs_signature' => 0,
                'is_candidate_selectable' => 0,
                'company_scope' => 'global',
                'description' => 'Munkabér utalásához szükséges bankszámlaszám nyilatkozat.',
                'sort_order' => 30,
                'is_active' => 1,
            ],
            [
                'code' => 'personal_data_statement',
                'name' => 'Személyes adatok nyilatkozata',
                'category' => DeclarationTemplate::CATEGORY_ONBOARDING,
                'declaration_group' => DeclarationTemplate::GROUP_PERSONAL_DATA,
                'tax_year' => null,
                'version' => 'v1',
                'renewal_policy' => DeclarationTemplate::RENEWAL_PER_RELATION,
                'required_policy' => DeclarationTemplate::REQUIRED_ALWAYS,
                'review_role' => DeclarationTemplate::REVIEW_ROLE_RECRUITER,
                'needs_signature' => 0,
                'is_candidate_selectable' => 0,
                'company_scope' => 'global',
                'description' => 'Belépéshez szükséges személyes alapadatok, adóazonosító és TAJ adatok nyilatkozata.',
                'sort_order' => 40,
                'is_active' => 1,
            ],
            [
                'code' => 'child_extra_leave_statement',
                'name' => 'Nyilatkozat gyermek után járó pótszabadság igénybevételéről',
                'category' => DeclarationTemplate::CATEGORY_EMPLOYMENT,
                'declaration_group' => DeclarationTemplate::GROUP_EMPLOYMENT,
                'tax_year' => null,
                'version' => 'v1',
                'renewal_policy' => DeclarationTemplate::RENEWAL_WHEN_CHANGED,
                'required_policy' => DeclarationTemplate::REQUIRED_CONDITIONAL,
                'review_role' => DeclarationTemplate::REVIEW_ROLE_RECRUITER,
                'needs_signature' => 0,
                'is_candidate_selectable' => 1,
                'company_scope' => 'global',
                'description' => 'Nem adóügyi, feltételes nyilatkozat gyermek után járó pótszabadsághoz.',
                'sort_order' => 50,
                'is_active' => 1,
            ],
            [
                'code' => 'family_tax_discount',
                'name' => 'Adóelőleg-nyilatkozat a családi kedvezmény érvényesítéséről',
                'category' => DeclarationTemplate::CATEGORY_TAX_ADVANCE,
                'declaration_group' => DeclarationTemplate::GROUP_TAX,
                'tax_year' => 2026,
                'version' => 'v1',
                'renewal_policy' => DeclarationTemplate::RENEWAL_YEARLY,
                'required_policy' => DeclarationTemplate::REQUIRED_OPTIONAL,
                'review_role' => DeclarationTemplate::REVIEW_ROLE_PAYROLL,
                'needs_signature' => 1,
                'is_candidate_selectable' => 1,
                'company_scope' => 'global',
                'description' => 'Adóügyi nyilatkozat családi kedvezmény és járulékkedvezmény érvényesítéséhez.',
                'sort_order' => 110,
                'is_active' => 1,
            ],
            [
                'code' => 'first_marriage_discount',
                'name' => 'Adóelőleg-nyilatkozat az első házasok kedvezményének érvényesítéséről',
                'category' => DeclarationTemplate::CATEGORY_TAX_ADVANCE,
                'declaration_group' => DeclarationTemplate::GROUP_TAX,
                'tax_year' => 2026,
                'version' => 'v1',
                'renewal_policy' => DeclarationTemplate::RENEWAL_YEARLY,
                'required_policy' => DeclarationTemplate::REQUIRED_OPTIONAL,
                'review_role' => DeclarationTemplate::REVIEW_ROLE_PAYROLL,
                'needs_signature' => 1,
                'is_candidate_selectable' => 1,
                'company_scope' => 'global',
                'description' => 'Adóügyi nyilatkozat első házasok kedvezményéhez.',
                'sort_order' => 120,
                'is_active' => 1,
            ],
            [
                'code' => 'personal_discount',
                'name' => 'Adóelőleg-nyilatkozat a személyi kedvezmény érvényesítéséről',
                'category' => DeclarationTemplate::CATEGORY_TAX_ADVANCE,
                'declaration_group' => DeclarationTemplate::GROUP_TAX,
                'tax_year' => 2026,
                'version' => 'v1',
                'renewal_policy' => DeclarationTemplate::RENEWAL_YEARLY,
                'required_policy' => DeclarationTemplate::REQUIRED_OPTIONAL,
                'review_role' => DeclarationTemplate::REVIEW_ROLE_PAYROLL,
                'needs_signature' => 1,
                'is_candidate_selectable' => 1,
                'company_scope' => 'global',
                'description' => 'Adóügyi nyilatkozat személyi kedvezmény érvényesítéséhez.',
                'sort_order' => 130,
                'is_active' => 1,
            ],
            [
                'code' => 'under_25_tax_discount_waiver',
                'name' => 'Nyilatkozat a 25 év alatti fiatalok kedvezményének mellőzéséről',
                'category' => DeclarationTemplate::CATEGORY_TAX_ADVANCE,
                'declaration_group' => DeclarationTemplate::GROUP_TAX,
                'tax_year' => 2026,
                'version' => 'v1',
                'renewal_policy' => DeclarationTemplate::RENEWAL_YEARLY,
                'required_policy' => DeclarationTemplate::REQUIRED_OPTIONAL,
                'review_role' => DeclarationTemplate::REVIEW_ROLE_PAYROLL,
                'needs_signature' => 1,
                'is_candidate_selectable' => 1,
                'company_scope' => 'global',
                'description' => 'Adóügyi nyilatkozat a 25 év alatti fiatalok kedvezményének részben vagy egészben történő mellőzéséről.',
                'sort_order' => 140,
                'is_active' => 1,
            ],
            [
                'code' => 'mothers_of_four_discount',
                'name' => 'Adóelőleg-nyilatkozat a négy vagy több gyermeket nevelő anyák kedvezményéről',
                'category' => DeclarationTemplate::CATEGORY_TAX_ADVANCE,
                'declaration_group' => DeclarationTemplate::GROUP_TAX,
                'tax_year' => 2026,
                'version' => 'v1',
                'renewal_policy' => DeclarationTemplate::RENEWAL_YEARLY,
                'required_policy' => DeclarationTemplate::REQUIRED_OPTIONAL,
                'review_role' => DeclarationTemplate::REVIEW_ROLE_PAYROLL,
                'needs_signature' => 1,
                'is_candidate_selectable' => 1,
                'company_scope' => 'global',
                'description' => 'Adóügyi nyilatkozat a négy vagy több gyermeket nevelő anyák kedvezményének érvényesítéséhez.',
                'sort_order' => 150,
                'is_active' => 1,
            ],
        ];

        foreach ($templates as $template) {
            $existing = $this->db->table('declaration_templates')
                ->where('code', $template['code'])
                ->where('tax_year', $template['tax_year'])
                ->where('version', $template['version'])
                ->get()
                ->getRow();

            if ($existing) {
                $this->db->table('declaration_templates')
                    ->where('id', $existing->id)
                    ->update($template);
                continue;
            }

            $this->db->table('declaration_templates')->insert($template);
        }
    }
}
