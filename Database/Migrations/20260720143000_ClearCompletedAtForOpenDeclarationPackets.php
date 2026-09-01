<?php

namespace App\Modules\Declarations\Database\Migrations;

use CodeIgniter\Database\Migration;

class ClearCompletedAtForOpenDeclarationPackets extends Migration
{
    public function up(): void
    {
        if (!$this->db->tableExists('declaration_packets')
            || !$this->db->fieldExists('completed_at', 'declaration_packets')) {
            return;
        }

        $this->db->table('declaration_packets')
            ->where('status !=', 'closed')
            ->where('completed_at IS NOT NULL', null, false)
            ->update(['completed_at' => null]);
    }

    public function down(): void
    {
        // Previous values cannot be restored reliably.
    }
}
