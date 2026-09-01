<?php

namespace App\Modules\Declarations\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProcessDateToDeclarationPackets extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('process_date', 'declaration_packets')) {
            return;
        }

        $this->forge->addColumn('declaration_packets', [
            'process_date' => [
                'type' => 'DATE',
                'null' => true,
                'after' => 'tax_year',
            ],
        ]);
    }

    public function down(): void
    {
        if (!$this->db->fieldExists('process_date', 'declaration_packets')) {
            return;
        }

        $this->forge->dropColumn('declaration_packets', 'process_date');
    }
}
