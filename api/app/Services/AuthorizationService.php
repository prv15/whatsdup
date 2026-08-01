<?php

declare(strict_types=1);

namespace WhatstheUp\Services;

use WhatstheUp\Support\HttpException;

final class AuthorizationService
{
    public function allows(array $identity, string $permission): bool
    {
        return in_array($permission, $identity['permissions'] ?? [], true);
    }

    public function require(array $identity, string $permission): void
    {
        if (!$this->allows($identity, $permission)) {
            throw new HttpException(403, 'You do not have permission to perform this action.', 'forbidden');
        }
    }
}
