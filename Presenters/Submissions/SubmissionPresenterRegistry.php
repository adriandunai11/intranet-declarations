<?php

namespace App\Modules\Declarations\Presenters\Submissions;

use App\Modules\Declarations\Entities\DeclarationSubmission;

class SubmissionPresenterRegistry
{
    /**
     * @var SubmissionPresenterInterface[]
     */
    private array $presenters;

    private SubmissionPresenterInterface $fallbackPresenter;

    public function __construct()
    {
        $this->presenters = [
            new PersonalDataSubmissionPresenter(),
            new BankAccountSubmissionPresenter(),
            new TaxSubmissionPresenter(),
            new StructuredStatementSubmissionPresenter(),
        ];

        $this->fallbackPresenter = new GenericSubmissionPresenter();
    }

    public function rowsFor(string $templateCode, ?DeclarationSubmission $submission): array
    {
        if (!$submission) {
            return [];
        }

        foreach ($this->presenters as $presenter) {
            if ($presenter->supports($templateCode)) {
                if (method_exists($presenter, 'rowsForTemplate')) {
                    return $presenter->rowsForTemplate($templateCode, $submission);
                }

                return $presenter->rows($submission);
            }
        }

        return $this->fallbackPresenter->rows($submission);
    }

    public function tablesFor(string $templateCode, ?DeclarationSubmission $submission): array
    {
        if (!$submission) {
            return [];
        }

        foreach ($this->presenters as $presenter) {
            if ($presenter->supports($templateCode) && method_exists($presenter, 'tables')) {
                if (method_exists($presenter, 'tablesForTemplate')) {
                    return $presenter->tablesForTemplate($templateCode, $submission);
                }

                return $presenter->tables($submission);
            }
        }

        return method_exists($this->fallbackPresenter, 'tables')
            ? $this->fallbackPresenter->tables($submission)
            : [];
    }
}
