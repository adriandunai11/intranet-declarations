<?php

namespace App\Modules\Declarations\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRevokedAtToDeclarationInvitations extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('revoked_at', 'declaration_invitations')) {
            $this->forge->addColumn('declaration_invitations', [
                'revoked_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'after' => 'completed_at',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('revoked_at', 'declaration_invitations')) {
            $this->forge->dropColumn('declaration_invitations', 'revoked_at');
        }
    }
}