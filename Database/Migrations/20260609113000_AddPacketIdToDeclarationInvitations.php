<?php

namespace App\Modules\Declarations\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPacketIdToDeclarationInvitations extends Migration
{
    public function up()
    {
        $this->forge->addColumn('declaration_invitations', [
            'packet_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
                'after' => 'employment_relation_id',
            ],
        ]);

        $this->db->query(
            'ALTER TABLE declaration_invitations ADD INDEX declaration_invitations_packet_id (packet_id)'
        );
    }

    public function down()
    {
        $this->forge->dropColumn('declaration_invitations', 'packet_id');
    }
}