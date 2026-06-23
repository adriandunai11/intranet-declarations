<?php

namespace App\Modules\Declarations\Services\DeclarationForms;

class DeclarationFormRegistry
{
    /** @var DeclarationFormHandlerInterface[] */
    protected array $handlers;

    public function __construct(?array $handlers = null)
    {
        $this->handlers = $handlers ?? [
            new PersonalDataDeclarationHandler(),
            new BankAccountDeclarationHandler(),
        ];
    }

    public function forItem(object $item): DeclarationFormHandlerInterface
    {
        $templateCode = (string) ($item->template_code ?? '');

        foreach ($this->handlers as $handler) {
            if ($handler->supports($templateCode)) {
                return $handler;
            }
        }

        return new UnsupportedDeclarationHandler();
    }


    public function hasConcreteHandlerForCode(string $templateCode): bool
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($templateCode)) {
                return !($handler instanceof UnsupportedDeclarationHandler);
            }
        }

        return false;
    }
}
