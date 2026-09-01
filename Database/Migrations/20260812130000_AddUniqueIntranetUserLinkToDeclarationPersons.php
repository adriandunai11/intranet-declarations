<?php

namespace App\Modules\Declarations\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

class AddUniqueIntranetUserLinkToDeclarationPersons extends Migration
{
    private const TABLE = 'declaration_persons';
    private const INDEX = 'declaration_persons_intranet_user_unique';

    public function up(): void
    {
        if (!$this->db->tableExists(self::TABLE)
            || !$this->db->fieldExists('intranet_user_id', self::TABLE)
            || $this->indexExists()) {
            return;
        }

        $duplicate = $this->db->query(
            'SELECT intranet_user_id, COUNT(*) AS row_count
             FROM ' . self::TABLE . '
             WHERE intranet_user_id IS NOT NULL
             GROUP BY intranet_user_id
             HAVING COUNT(*) > 1
             LIMIT 1'
        )->getRowArray();

        if ($duplicate) {
            throw new RuntimeException(
                'Az intranet felhasználói kapcsolat nem tehető egyedivé: a(z) '
                . (int) $duplicate['intranet_user_id']
                . ' azonosító több nyilatkozati személyhez kapcsolódik.'
            );
        }

        $this->db->query(
            'ALTER TABLE ' . self::TABLE
            . ' ADD UNIQUE INDEX ' . self::INDEX . ' (intranet_user_id)'
        );
    }

    public function down(): void
    {
        if (!$this->db->tableExists(self::TABLE) || !$this->indexExists()) {
            return;
        }

        $this->db->query(
            'ALTER TABLE ' . self::TABLE . ' DROP INDEX ' . self::INDEX
        );
    }

    private function indexExists(): bool
    {
        $result = $this->db->query(
            'SHOW INDEX FROM ' . self::TABLE . ' WHERE Key_name = ?',
            [self::INDEX]
        )->getResultArray();

        return $result !== [];
    }
}
