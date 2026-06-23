<?php

namespace App\Modules\Declarations\Services\Exceptions;

use RuntimeException;

class FormValidationException extends RuntimeException
{
    public function __construct(
        protected array $errors,
        string $message = 'Kérjük, ellenőrizd a megadott adatokat.'
    ) {
        parent::__construct($message);
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
