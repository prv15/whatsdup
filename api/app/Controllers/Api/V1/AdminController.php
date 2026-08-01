<?php

declare(strict_types=1);

namespace WhatstheUp\Controllers\Api\V1;

use WhatstheUp\Services\AdminService;
use WhatstheUp\Support\Request;

final class AdminController
{
    public function __construct(private readonly AdminService $admin)
    {
    }

    public function dashboard(): array
    {
        return ['data' => $this->admin->dashboard()];
    }

    public function businesses(): array
    {
        return ['data' => $this->admin->businesses()];
    }

    public function users(): array
    {
        return ['data' => $this->admin->users()];
    }

    public function createBusiness(Request $request): array
    {
        return ['data' => $this->admin->createBusiness($request->json(), $request->attributes['identity']['id'])];
    }

    public function updateBusiness(Request $request): array
    {
        return ['data' => $this->admin->updateBusinessStatus(
            (string) ($request->attributes['route']['id'] ?? ''),
            (string) ($request->json()['status'] ?? ''),
            $request->attributes['identity']['id'],
        )];
    }
}
