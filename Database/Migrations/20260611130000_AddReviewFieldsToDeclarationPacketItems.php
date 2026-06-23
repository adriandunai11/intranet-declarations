<?php

namespace App\Modules\Declarations\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddReviewFieldsToDeclarationPacketItems extends Migration
{
    public function up()
    {
        $this->forge->addColumn('declaration_packet_items', [
            'review_note' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'rejected_at',
            ],
            'reviewed_by_user_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
                'after' => 'review_note',
            ],
            'reviewed_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'reviewed_by_user_id',
            ],
        ]);

        $this->db->query(
            'ALTER TABLE declaration_packet_items ADD INDEX declaration_packet_items_reviewed_by_user_id (reviewed_by_user_id)'
        );
    }

    public function down()
    {
        $this->forge->dropColumn('declaration_packet_items', [
            'review_note',
            'reviewed_by_user_id',
            'reviewed_at',
        ]);
    }
}