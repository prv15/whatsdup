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

    public function plans(): array
    {
        return ['data' => $this->admin->plans()];
    }

    public function createPlan(Request $request): array
    {
        return ['data' => $this->admin->createPlan($request->json(), $request->attributes['identity']['id'])];
    }

    public function updatePlan(Request $request): array
    {
        return ['data' => $this->admin->updatePlan(
            (string) ($request->attributes['route']['id'] ?? ''),
            $request->json(),
            $request->attributes['identity']['id'],
        )];
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

    public function assignBusinessPlan(Request $request): array
    {
        return ['data' => $this->admin->assignBusinessPlan(
            (string) ($request->attributes['route']['id'] ?? ''),
            $request->json(),
            $request->attributes['identity']['id'],
        )];
    }

    public function metaConnections(): array
    {
        return ['data' => $this->admin->metaConnections()];
    }

    public function queueHealth(): array
    {
        return ['data' => $this->admin->queueHealth()];
    }

    public function failedJobs(Request $request): array
    {
        $limit = (int) ($request->query['limit'] ?? 50);
        return ['data' => $this->admin->failedJobs($limit)];
    }

    public function retryJob(Request $request): array
    {
        $id = (int) ($request->attributes['route']['id'] ?? 0);
        $ok = $this->admin->retryJob($id);
        return ['data' => ['success' => $ok, 'jobId' => $id]];
    }

    public function retryAllJobs(): array
    {
        $retried = $this->admin->retryAllFailed();
        return ['data' => ['retriedCount' => $retried]];
    }

    public function clearStaleLocks(): array
    {
        $reclaimed = $this->admin->clearStaleLocks();
        return ['data' => ['reclaimedLocks' => $reclaimed]];
    }

    public function updateUserStatus(Request $request): array
    {
        return ['data' => $this->admin->updateUserStatus(
            (string) ($request->attributes['route']['id'] ?? ''),
            (string) ($request->json()['status'] ?? ''),
            $request->attributes['identity']['id'],
        )];
    }

    public function resetUserPassword(Request $request): array
    {
        return ['data' => $this->admin->resetUserPassword(
            (string) ($request->attributes['route']['id'] ?? ''),
            (string) ($request->json()['password'] ?? ''),
            $request->attributes['identity']['id'],
        )];
    }

    public function revokeUserSessions(Request $request): array
    {
        return ['data' => $this->admin->revokeUserSessions(
            (string) ($request->attributes['route']['id'] ?? ''),
            $request->attributes['identity']['id'],
        )];
    }

    public function auditLogs(Request $request): array
    {
        return ['data' => $this->admin->auditLogs($request->query)];
    }
}