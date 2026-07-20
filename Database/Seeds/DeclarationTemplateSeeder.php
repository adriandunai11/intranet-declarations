<?php

namespace App\Modules\Declarations\Database\Seeds;

use App\Modules\Declarations\Entities\DeclarationTemplate;
use App\Modules\Declarations\Services\DeclarationTemplateCatalogService;
use CodeIgniter\Database\Seeder;

class DeclarationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $catalogService = new DeclarationTemplateCatalogService();
        $catalogService->sync();

        $this->deactivateLegacyTaxSeeds($catalogService->catalog());
    }

    /**
     * @param list<array<string,mixed>> $catalog
     */
    private function deactivateLegacyTaxSeeds(array $catalog): void
    {
        if (!$this->db->fieldExists('details_json', 'declaration_templates')) {
            return;
        }

        $taxCodes = [];

        foreach ($catalog as $template) {
            if (($template['declaration_group'] ?? null) === DeclarationTemplate::GROUP_TAX) {
                $taxCodes[] = (string) $template['code'];
            }
        }

        $taxCodes = array_values(array_unique(array_filter($taxCodes)));

        if ($taxCodes === []) {
            return;
        }

        $this->db->table('declaration_templates')
            ->whereIn('code', $taxCodes)
            ->where('declaration_group', DeclarationTemplate::GROUP_TAX)
            ->where('tax_year IS NOT NULL', null, false)
            ->where('version', 'v1')
            ->groupStart()
                ->where('details_json', null)
                ->orWhere('details_json', '')
            ->groupEnd()
            ->update([
                'is_active' => 0,
            ]);
    }
}
