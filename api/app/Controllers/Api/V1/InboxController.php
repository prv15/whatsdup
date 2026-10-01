<?php

declare(strict_types=1);

namespace WhatstheUp\Controllers\Api\V1;

use WhatstheUp\Services\InboxService;
use WhatstheUp\Support\Request;

final class InboxController
{
    public function __construct(private readonly InboxService $inbox)
    {
    }

    public function conversations(Request $request): array
    {
        $businessId = (string) $request->attributes['identity']['business']['id'];
        return ['data' => $this->inbox->listConversations($businessId, $request->query)];
    }

    public function conversation(Request $request): array
    {
        $businessId = (string) $request->attributes['identity']['business']['id'];
        $id = (string) ($request->attributes['route']['id'] ?? '');
        return ['data' => $this->inbox->getConversation($businessId, $id)];
    }

    public function messages(Request $request): array
    {
        $businessId = (string) $request->attributes['identity']['business']['id'];
        $id = (string) ($request->attributes['route']['id'] ?? '');
        $limit = isset($request->query['limit']) ? (int) $request->query['limit'] : 50;
        $beforeId = isset($request->query['before']) ? (string) $request->query['before'] : null;
        return ['data' => $this->inbox->getMessages($businessId, $id, $limit, $beforeId)];
    }

    public function sendMessage(Request $request): array
    {
        $identity = $request->attributes['identity'];
        $businessId = (string) $identity['business']['id'];
        $userId = (string) $identity['id'];
        $id = (string) ($request->attributes['route']['id'] ?? '');
        $json = $request->json();
        $content = (string) ($json['content'] ?? '');

        return ['data' => $this->inbox->sendMessage($businessId, $id, $userId, $content)];
    }

    public function markRead(Request $request): array
    {
        $businessId = (string) $request->attributes['identity']['business']['id'];
        $id = (string) ($request->attributes['route']['id'] ?? '');
        $this->inbox->markRead($businessId, $id);
        return ['data' => ['success' => true]];
    }

    public function update(Request $request): array
    {
        $businessId = (string) $request->attributes['identity']['business']['id'];
        $id = (string) ($request->attributes['route']['id'] ?? '');
        $json = $request->json();

        if (isset($json['status'])) {
            return ['data' => $this->inbox->updateStatus($businessId, $id, (string) $json['status'])];
        }

        if (array_key_exists('assignedUserId', $json)) {
            $assignedUserId = $json['assignedUserId'] !== null ? (string) $json['assignedUserId'] : null;
            return ['data' => $this->inbox->assign($businessId, $id, $assignedUserId)];
        }

        return ['data' => $this->inbox->getConversation($businessId, $id)];
    }
}
