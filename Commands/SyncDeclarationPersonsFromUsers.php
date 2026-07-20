<?php

namespace App\Modules\Declarations\Commands;

use App\Modules\Declarations\Services\IntranetUserLinkService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SyncDeclarationPersonsFromUsers extends BaseCommand
{
    protected $group = 'Declarations';
    protected $name = 'declarations:sync-persons-from-users';
    protected $description = 'Creates or links declaration person records for active intranet users.';

    public function run(array $params): void
    {
        $dryRun = in_array('--dry-run', $params, true) || in_array('dry-run', $params, true);
        $service = new IntranetUserLinkService();
        $result = $service->syncDeclarationPersonsForActiveUsers($dryRun);

        CLI::write($dryRun ? 'Nyilatkozati személy szinkron előnézet kész.' : 'Nyilatkozati személy szinkron kész.', 'green');
        CLI::write('Létrehozva: ' . (int) $result['created']);
        CLI::write('Kapcsolva meglévő személyhez: ' . (int) $result['linked']);
        CLI::write('Már kapcsolt: ' . (int) $result['already_linked']);
        CLI::write('Kihagyva: ' . (int) $result['skipped']);

        foreach (array_slice($result['errors'], 0, 30) as $error) {
            CLI::write('- ' . $error, 'yellow');
        }

        if (count($result['errors']) > 30) {
            CLI::write('További kihagyott sorok: ' . (count($result['errors']) - 30), 'yellow');
        }
    }
}
