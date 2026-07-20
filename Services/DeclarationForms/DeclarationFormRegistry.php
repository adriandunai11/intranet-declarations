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
            new StructuredStatementDeclarationHandler(),
            new TaxDeclarationHandler(),
        ];
    }

    public function forItem(object $item): DeclarationFormHandlerInterface
    {
        $templateCode = (string) ($item->template_code ?? '');

        foreach ($this->handlers as $handler) {
            if ($handler->supports($templateCode)) {
                if ($handler instanceof StructuredStatementDeclarationHandler) {
                    return new StructuredStatementDeclarationHandler($item);
                }

                if ($handler instanceof TaxDeclarationHandler) {
                    return new TaxDeclarationHandler($item);
                }

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

    public function hasConcreteHandlerForTemplate(object $template): bool
    {
        $templateCode = (string) ($template->code ?? $template->template_code ?? '');

        if ($this->hasConcreteHandlerForCode($templateCode)) {
            return true;
        }

        return false;
    }
}
