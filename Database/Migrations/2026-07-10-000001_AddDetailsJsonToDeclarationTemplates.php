<?php

namespace App\Modules\Declarations\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDetailsJsonToDeclarationTemplates extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('details_json', 'declaration_templates')) {
            return;
        }

        $this->forge->addColumn('declaration_templates', [
            'details_json' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'description',
            ],
        ]);
    }

    public function down(): void
    {
        if (!$this->db->fieldExists('details_json', 'declaration_templates')) {
            return;
        }

        $this->forge->dropColumn('declaration_templates', 'details_json');
    }
}
