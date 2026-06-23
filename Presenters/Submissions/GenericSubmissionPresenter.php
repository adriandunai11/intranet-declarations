<?php

namespace App\Modules\Declarations\Presenters\Submissions;

use App\Modules\Declarations\Entities\DeclarationSubmission;

class GenericSubmissionPresenter implements SubmissionPresenterInterface
{
    public function supports(string $templateCode): bool
    {
        return true;
    }

    public function rows(DeclarationSubmission $submission): array
    {
        $data = $submission->data_json ?? [];

        if (is_string($data)) {
            $decoded = json_decode($data, true);
            $data = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($data) || empty($data)) {
            return [
                'Beküldött adat' => '-',
            ];
        }

        $rows = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            $rows[$this->humanizeKey((string) $key)] = (string) $value;
        }

        return $rows;
    }

    private function humanizeKey(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }
}