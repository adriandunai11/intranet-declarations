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
                return $presenter->rows($submission);
            }
        }

        return $this->fallbackPresenter->rows($submission);
    }
}