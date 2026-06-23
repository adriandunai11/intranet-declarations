<?php

namespace App\Modules\Declarations\Config;

use CodeIgniter\Config\BaseConfig;

class Declarations extends BaseConfig
{
    public string $publicBaseUrl = 'https://nyilatkozatok2.miellgroup.com';
    public string $mailFromEmail = 'noreply@miellgroup.com';
    public string $mailFromName = 'Miell Group nyilatkozatok';
}