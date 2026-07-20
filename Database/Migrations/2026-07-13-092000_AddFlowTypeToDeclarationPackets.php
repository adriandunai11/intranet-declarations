<?php

namespace App\Modules\Declarations\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFlowTypeToDeclarationPackets extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('flow_type', 'declaration_packets')) {
            return;
        }

        $this->forge->addColumn('declaration_packets', [
            'flow_type' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
                'after' => 'status',
            ],
        ]);
    }

    public function down(): void
    {
        if (!$this->db->fieldExists('flow_type', 'declaration_packets')) {
            return;
        }

        $this->forge->dropColumn('declaration_packets', 'flow_type');
    }
}
