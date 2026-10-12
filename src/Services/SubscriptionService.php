<?php

namespace App\Services;

use App\Repositories\SubredditRepository;
use App\Repositories\SubscriptionRepository;
use App\DTOs\Input\SubscribeDTO;
use App\DTOs\Output\SubredditResponseDTO;

class SubscriptionService
{
    private SubredditRepository $subredditRepository;
    private SubscriptionRepository $subscriptionRepository;

    public function __construct()
    {
        $this->subredditRepository = new SubredditRepository();
        $this->subscriptionRepository = new SubscriptionRepository();
    }

    public function toggleSubscription(SubscribeDTO $dto)
    {
        $subreddit = $this->subredditRepository->findById($dto->subredditId);
        if (!$subreddit) {
            throw new \DomainException("Subreddit not found");
        }
        $isSubscripted = $this->subscriptionRepository->isSubscripted($dto->userId, $dto->subredditId);

        if ($isSubscripted) {
            $this->subscriptionRepository->unsubscribe($dto->userId, $dto->subredditId);
            return [
                'is_subscribed' => false,
                'message' => 'Successfully unsubscribed from subreddit'
            ];
        } else {
            $this->subscriptionRepository->subscribe($dto->userId, $dto->subredditId);
            return [
                'is_subscribed' => true,
                'message' => 'Successfully subscribed to subreddit'
            ];
        }
    }

    public function getUserSubscriptions(int $userId, int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;

        $subreddits = $this->subscriptionRepository->findByUserId($userId, $limit, $offset);

        $total = $this->subscriptionRepository->countByUserId($userId);

        return [
            'data' => array_map(
                fn($subreddit) => SubredditResponseDTO::fromModel($subreddit),
                $subreddits
            ),
            'count' => count($subreddits),
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ];
    }
}
