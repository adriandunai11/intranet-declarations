<?php

namespace App\Modules\Declarations\Presenters\Submissions;

use App\Modules\Declarations\Entities\DeclarationSubmission;

interface SubmissionPresenterInterface
{
    public function supports(string $templateCode): bool;

    public function rows(DeclarationSubmission $submission): array;
}