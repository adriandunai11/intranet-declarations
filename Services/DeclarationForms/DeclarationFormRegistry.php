<?php

namespace App\Modules\Declarations\Services\DeclarationForms;

use App\Modules\Declarations\Services\Documents\DeclarationTemplatePlaceholderScanner;

class DeclarationFormRegistry
{
    /** @var DeclarationFormHandlerInterface[] */
    protected array $handlers;
    protected DeclarationTemplatePlaceholderScanner $placeholderScanner;

    public function __construct(?array $handlers = null, ?DeclarationTemplatePlaceholderScanner $placeholderScanner = null)
    {
        $this->handlers = $handlers ?? [
            new PersonalDataDeclarationHandler(),
            new BankAccountDeclarationHandler(),
            new TaxDeclarationHandler(),
        ];
        $this->placeholderScanner = $placeholderScanner ?? new DeclarationTemplatePlaceholderScanner();
    }

    public function forItem(object $item): DeclarationFormHandlerInterface
    {
        $templateCode = (string) ($item->template_code ?? '');

        foreach ($this->handlers as $handler) {
            if ($handler->supports($templateCode)) {
                if ($handler instanceof TaxDeclarationHandler) {
                    return new TaxDeclarationHandler($item);
                }

                return $handler;
            }
        }

        if ($this->hasTemplateBackedItem($item)) {
            return new TemplateBackedDeclarationHandler($item, $this->placeholderScanner);
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

        return $this->isTemplateReadyForOnlineCompletion($template);
    }

    private function hasTemplateBackedItem(object $item): bool
    {
        $group = (string) ($item->declaration_group ?? $item->template_declaration_group ?? '');

        if ($group === 'tax') {
            return true;
        }

        return $this->placeholderScanner->templateExistsForItem($item);
    }

    private function isTemplateReadyForOnlineCompletion(object $template): bool
    {
        return $this->placeholderScanner->templateExistsForItem($template)
            && $this->placeholderScanner->placeholdersForItem($template) !== [];
    }
}
