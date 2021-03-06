<?php


namespace Blog\Models;

use Blog\Entities\PostImage;
use Blog\Entities\User;
use Core\Manager;

abstract class UserManager extends Manager
{
    abstract public function getList(?string $role = null);

    abstract public function count(?array $roles = null);

    abstract public function findById(int $userId);

    abstract public function findByEmail(string $email): ?User;

    abstract public function findByToken(string $type, string $token);

    abstract public function activate(string $token);

    abstract public function changeEmail(string $token);

    abstract public function mailExists(string $email, ?int $ignoreId = null);

    abstract public function userExists(string $username, ?int $ignoreId = null);

    abstract public function startPasswordReset(User $user);

    abstract public function resetPassword(User $user);

    abstract protected function add(User $user);

    abstract protected function modify(User $user);

    abstract public function delete(int $userId);

    public function save(User $user) {
        if ($user->isValid()) {
            return $user->isNew() ? $this->add($user) : $this->modify($user);
        }
        throw new \RuntimeException("Les paramètres de l'utilisateur ne sont pas valides.");
    }
}