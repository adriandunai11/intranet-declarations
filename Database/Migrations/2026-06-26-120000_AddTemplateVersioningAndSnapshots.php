<?php

namespace App\Modules\Declarations\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTemplateVersioningAndSnapshots extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('declaration_templates', [
            'template_file' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'version',
            ],
            'effective_from' => [
                'type' => 'DATE',
                'null' => true,
                'after' => 'template_file',
            ],
            'effective_to' => [
                'type' => 'DATE',
                'null' => true,
                'after' => 'effective_from',
            ],
            'parent_template_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => true,
                'after' => 'effective_to',
            ],
        ]);

        $this->forge->addColumn('declaration_packet_items', [
            'template_code_snapshot' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
                'after' => 'template_id',
            ],
            'template_name_snapshot' => [
                'type' => 'VARCHAR',
                'constraint' => 190,
                'null' => true,
                'after' => 'template_code_snapshot',
            ],
            'template_version_snapshot' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
                'null' => true,
                'after' => 'template_name_snapshot',
            ],
            'template_file_snapshot' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'template_version_snapshot',
            ],
        ]);

        $this->db->query(
            "UPDATE declaration_templates SET template_file = CONCAT(code, '.docx') WHERE template_file IS NULL OR template_file = ''"
        );

        $this->db->query(
            "UPDATE declaration_packet_items dpi
             LEFT JOIN declaration_templates dt ON dt.id = dpi.template_id
             SET
                dpi.template_code_snapshot = COALESCE(NULLIF(dpi.template_code_snapshot, ''), dt.code),
                dpi.template_name_snapshot = COALESCE(NULLIF(dpi.template_name_snapshot, ''), dt.name),
                dpi.template_version_snapshot = COALESCE(NULLIF(dpi.template_version_snapshot, ''), dt.version),
                dpi.template_file_snapshot = COALESCE(NULLIF(dpi.template_file_snapshot, ''), dt.template_file)"
        );
    }

    public function down(): void
    {
        $this->forge->dropColumn('declaration_packet_items', [
            'template_code_snapshot',
            'template_name_snapshot',
            'template_version_snapshot',
            'template_file_snapshot',
        ]);

        $this->forge->dropColumn('declaration_templates', [
            'template_file',
            'effective_from',
            'effective_to',
            'parent_template_id',
        ]);
    }
}
