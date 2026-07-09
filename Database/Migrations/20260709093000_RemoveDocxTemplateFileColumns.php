<?php

namespace App\Modules\Declarations\Database\Migrations;

use CodeIgniter\Database\Migration;

class RemoveDocxTemplateFileColumns extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('template_file_snapshot', 'declaration_packet_items')) {
            $this->forge->dropColumn('declaration_packet_items', 'template_file_snapshot');
        }

        if ($this->db->fieldExists('template_file', 'declaration_templates')) {
            $this->forge->dropColumn('declaration_templates', 'template_file');
        }
    }

    public function down(): void
    {
        $templateFields = [];

        if (!$this->db->fieldExists('template_file', 'declaration_templates')) {
            $templateFields['template_file'] = [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'version',
            ];
        }

        if ($templateFields !== []) {
            $this->forge->addColumn('declaration_templates', $templateFields);
        }

        $itemFields = [];

        if (!$this->db->fieldExists('template_file_snapshot', 'declaration_packet_items')) {
            $itemFields['template_file_snapshot'] = [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'template_version_snapshot',
            ];
        }

        if ($itemFields !== []) {
            $this->forge->addColumn('declaration_packet_items', $itemFields);
        }
    }
}
