<?php

namespace App\Modules\Declarations\Commands;

use App\Modules\Declarations\Services\DeclarationTemplateCatalogService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SyncDeclarationTemplateCatalog extends BaseCommand
{
    protected $group = 'Declarations';
    protected $name = 'declarations:sync-template-catalog';
    protected $description = 'Creates or updates the built-in declaration template catalog.';

    public function run(array $params): void
    {
        $service = new DeclarationTemplateCatalogService();
        $result = $service->sync();

        CLI::write('Nyilatkozat katalógus szinkron kész.', 'green');
        CLI::write('Létrehozva: ' . (int) $result['created']);
        CLI::write('Frissítve: ' . (int) $result['updated']);
        CLI::write('Változatlan: ' . (int) $result['skipped']);
    }
}
