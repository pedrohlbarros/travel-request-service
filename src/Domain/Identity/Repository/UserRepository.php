<?php

declare(strict_types=1);

namespace Domain\Identity\Repository;

use Domain\Identity\Entity\User;
use Domain\Identity\Exception\EmailAlreadyRegistered;
use Domain\Identity\Exception\UserNotFound;
use Domain\Identity\ValueObject\Email;
use Domain\Identity\ValueObject\UserId;

interface UserRepository
{
    /**
     * @throws UserNotFound
     */
    public function get(UserId $id): User;

    public function findByEmail(Email $email): ?User;

    /**
     * @throws EmailAlreadyRegistered when another user took the email in the meantime
     */
    public function add(User $user): void;
}
