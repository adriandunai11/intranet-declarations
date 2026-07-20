<?php

namespace App\Modules\Declarations\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSelectionSourceToDeclarationPacketItems extends Migration
{
    public function up(): void
    {
        if (!$this->db->fieldExists('selection_source', 'declaration_packet_items')) {
            $this->forge->addColumn('declaration_packet_items', [
                'selection_source' => [
                    'type' => 'VARCHAR',
                    'constraint' => 40,
                    'default' => 'admin_selected',
                    'after' => 'template_version_snapshot',
                ],
            ]);
        }

        if ($this->db->fieldExists('flow_type', 'declaration_packets')) {
            $this->db->query(
                "UPDATE declaration_packet_items dpi
                 INNER JOIN declaration_packets dp ON dp.id = dpi.packet_id
                 SET dpi.selection_source = 'self_service_primary'
                 WHERE dp.flow_type IN ('self_service', 'self_service_tax', 'self_service_change')
                   AND dpi.selection_source = 'admin_selected'"
            );
        }

        if ($this->db->tableExists('declaration_audit_logs')) {
            $this->db->query(
                "UPDATE declaration_packet_items dpi
                 INNER JOIN declaration_audit_logs dal ON dal.packet_item_id = dpi.id
                 SET dpi.selection_source = 'candidate_selected'
                 WHERE dal.action = 'optional_template_added'"
            );
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('selection_source', 'declaration_packet_items')) {
            $this->forge->dropColumn('declaration_packet_items', 'selection_source');
        }
    }
}
