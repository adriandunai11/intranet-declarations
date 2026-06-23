<?php

namespace App\Modules\Declarations\Services;

use App\Models\UserModel;
use App\Models\UserRolesModel;
use RuntimeException;

class RecruiterService
{
    public const RECRUITER_ROLE_ID = 7;

    protected UserModel $userModel;
    protected UserRolesModel $userRolesModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->userRolesModel = new UserRolesModel();
    }

    public function getRecruiters(): array
    {
        $userIds = $this->getRecruiterUserIds();

        if ($userIds === []) {
            return [];
        }

        $users = $this->userModel
            ->whereIn('id', $userIds)
            ->findAll();

        usort($users, function ($a, $b): int {
            return strcasecmp($this->getDisplayName($a), $this->getDisplayName($b));
        });

        return $users;
    }

    public function findRecruiterById(int $userId)
    {
        if ($userId <= 0 || !$this->isRecruiter($userId)) {
            return null;
        }

        return $this->userModel->find($userId);
    }

    public function ensureRecruiterExists(int $userId): void
    {
        if ($userId <= 0) {
            throw new RuntimeException('Az elsődleges toborzó megadása kötelező.');
        }

        if (!$this->isRecruiter($userId)) {
            throw new RuntimeException('A kiválasztott felhasználó nem rendelkezik Toborzó szerepkörrel.');
        }

        if (!$this->userModel->find($userId)) {
            throw new RuntimeException('A kiválasztott toborzó felhasználó nem található.');
        }
    }

    public function getRecruiterDisplayMap(): array
    {
        $map = [];

        foreach ($this->getRecruiters() as $user) {
            $id = (int) $this->getValue($user, 'id');

            if ($id <= 0) {
                continue;
            }

            $antraid = $this->getAntraId($user);
            $map[$id] = $this->getDisplayName($user) . ($antraid !== '' ? ' (' . $antraid . ')' : '');
        }

        return $map;
    }

    public function getDisplayName($user): string
    {
        foreach (['name', 'full_name', 'display_name'] as $field) {
            $value = trim((string) $this->getValue($user, $field));

            if ($value !== '') {
                return $value;
            }
        }

        $lastname = trim((string) ($this->getValue($user, 'lastname') ?: $this->getValue($user, 'last_name')));
        $firstname = trim((string) ($this->getValue($user, 'firstname') ?: $this->getValue($user, 'first_name')));
        $fullName = trim($lastname . ' ' . $firstname);

        if ($fullName !== '') {
            return $fullName;
        }

        $username = trim((string) $this->getValue($user, 'username'));

        return $username !== '' ? $username : ('Felhasználó #' . (int) $this->getValue($user, 'id'));
    }

    public function getEmail($user): string
    {
        $email = $this->getValue($user, 'email');

        if (is_array($email)) {
            $email = reset($email) ?: '';
        }

        return trim((string) $email);
    }

    public function getAntraId($user): string
    {
        $antraid = $this->getValue($user, 'antraid');

        if (is_array($antraid)) {
            $antraid = reset($antraid) ?: '';
        }

        return trim((string) $antraid);
    }

    public function isRecruiter(int $userId): bool
    {
        return $this->userRolesModel
            ->where('userid', $userId)
            ->where('role', self::RECRUITER_ROLE_ID)
            ->first() !== null;
    }

    private function getRecruiterUserIds(): array
    {
        $rows = $this->userRolesModel
            ->select('userid')
            ->where('role', self::RECRUITER_ROLE_ID)
            ->findAll();

        $ids = [];

        foreach ($rows as $row) {
            $userId = (int) $this->getValue($row, 'userid');

            if ($userId > 0) {
                $ids[] = $userId;
            }
        }

        return array_values(array_unique($ids));
    }

    private function getValue($source, string $field)
    {
        if (is_array($source)) {
            return $source[$field] ?? null;
        }

        if (is_object($source)) {
            return $source->{$field} ?? null;
        }

        return null;
    }
}
