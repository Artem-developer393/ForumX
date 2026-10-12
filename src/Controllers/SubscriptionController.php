<?php

namespace App\Controllers;

use App\DTOs\Input\SubscribeDTO;
use App\Services\SubscriptionService;
use App\Http\Request;
use App\Http\Response;

class SubscriptionController
{
    private SubscriptionService $subscriptionService;

    public function __construct()
    {
        $this->subscriptionService = new SubscriptionService();
    }

    public function toggleSub(Request $request, int $id): void
    {
        $userData = $request->user ?? null;
        $userId = (int)($userData->userId ?? $userData->id ?? 0);
        if ($userId === 0) {
            Response::error("Unauthorized: Invalid user in token", 401);
            return;
        }

        $dto = SubscribeDTO::fromArray($id, $userId);

        try {
            $result = $this->subscriptionService->toggleSubscription($dto);

            Response::success([
                'message' => $result['message'],
                'is_subscribed' => $result['is_subscribed']
            ]);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Response::error("Failed to process subscription: " . $e->getMessage(), 500);
        }
    }

    public function getMySubscriptions(Request $request): void
    {
        $userData = $request->user ?? null;
        $userId = (int)($userData->userId ?? $userData->id ?? 0);

        if ($userId === 0) {
            Response::error("Unauthorized: Invalid user in token", 401);
            return;
        }

        $page = max(1, (int)($request->params['page'] ?? 1));

        $limit = max(1, min(100, (int)($request->params['limit'] ?? 20)));

        try {
            $result = $this->subscriptionService->getUserSubscriptions($userId, $page, $limit);

            Response::success($result, 200);
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 500);
        }
    }
}
