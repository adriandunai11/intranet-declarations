<?php

namespace App\Modules\Declarations\Services\Public;

class PublicUrlService
{
    protected string $baseUrl;

    public function __construct()
    {
        $config = config(\App\Modules\Declarations\Config\Declarations::class);

        $this->baseUrl = rtrim((string) $config->publicBaseUrl, '/');
    }

    public function start(string $token): string
    {
        return $this->baseUrl . '/start/' . rawurlencode($token);
    }

    public function verifyAntra(string $token): string
    {
        return $this->start($token) . '/verify-antra';
    }

    public function item(string $token, int $itemId): string
    {
        return $this->start($token) . '/item/' . $itemId;
    }
}
