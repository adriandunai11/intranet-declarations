<?php

namespace App\Modules\Declarations\Presenters;

use App\Modules\Declarations\Entities\Person;

class PersonTablePresenter
{
    public function statusBadge(?string $status): string
    {
        return match ($status) {
            Person::STATUS_ACTIVE => '<span class="badge badge-success">Aktív</span>',
            Person::STATUS_INACTIVE => '<span class="badge badge-secondary">Inaktív</span>',
            Person::STATUS_BLOCKED => '<span class="badge badge-danger">Letiltva</span>',
            Person::STATUS_MERGED => '<span class="badge badge-dark">Összevonva</span>',
            default => '<span class="badge badge-warning">Ismeretlen</span>',
        };
    }

    public function intranetLinkBadge($row): string
    {
        return !empty($row->intranet_user_id)
            ? '<span class="badge badge-success">Kapcsolva</span>'
            : '<span class="badge badge-secondary">Nincs</span>';
    }

    public function actions($row): string
    {
        $personId = (int) ($row->id ?? 0);
        $buttons = [];

        $buttons[] = '<a href="' . url('declarations/persons/' . $personId) . '"'
            . ' class="btn btn-sm btn-default"'
            . ' title="Megnyitás"'
            . ' aria-label="Megnyitás">'
            . '<i class="fas fa-eye"></i>'
            . '</a>';

        if (hasPermissions('declarations_persons_edit')) {
            $buttons[] = '<button type="button"'
                . ' class="btn btn-sm btn-default js-edit-person"'
                . ' title="Szerkesztés"'
                . ' aria-label="Szerkesztés"'
                . ' data-id="' . $personId . '"'
                . ' data-antra-id="' . $this->attr($row->antra_id ?? '') . '"'
                . ' data-lastname="' . $this->attr($row->lastname ?? '') . '"'
                . ' data-firstname="' . $this->attr($row->firstname ?? '') . '"'
                . ' data-birth-name="' . $this->attr($row->birth_name ?? '') . '"'
                . ' data-mother-name="' . $this->attr($row->mother_name ?? '') . '"'
                . ' data-birth-place="' . $this->attr($row->birth_place ?? '') . '"'
                . ' data-birth-date="' . $this->attr($row->birth_date ?? '') . '"'
                . ' data-tax-number="' . $this->attr($row->tax_number_raw ?? $row->tax_number ?? '') . '"'
                . ' data-taj-number="' . $this->attr($row->taj_number_raw ?? $row->taj_number ?? '') . '"'
                . ' data-email="' . $this->attr($row->email ?? '') . '"'
                . ' data-phone="' . $this->attr($row->phone ?? '') . '"'
                . ' data-status="' . $this->attr($row->status ?? Person::STATUS_ACTIVE) . '">'
                . '<i class="fas fa-edit"></i>'
                . '</button>';
        }

        return '<div class="btn-group btn-group-sm" role="group" aria-label="Műveletek">'
            . implode('', $buttons)
            . '</div>';
    }

    public function mask(?string $value, int $visibleEnd): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        return str_repeat('*', max(strlen($value) - $visibleEnd, 0)) . substr($value, -$visibleEnd);
    }

    private function attr($value): string
    {
        return esc((string) ($value ?? ''), 'attr');
    }
}
