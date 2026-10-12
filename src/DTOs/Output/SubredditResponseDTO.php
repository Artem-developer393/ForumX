<?php

namespace App\DTOs\Output;

use App\Models\Subreddit;

class SubredditResponseDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $title,
        public readonly string $description,
        public readonly ?int $creatorId,
        public readonly bool $isLocked,
        public readonly ?string $createdAt,

        public readonly ?string $iconUrl,
        public readonly int $subscribersCount
    ) {}

    public static function fromModel(
        Subreddit $subreddit
    ): self {
        return new self(
            id: $subreddit->id,
            title: $subreddit->title,
            description: $subreddit->description,
            creatorId: $subreddit->creatorId,
            isLocked: $subreddit->isLocked,
            createdAt: $subreddit->createdAt,

            iconUrl: self::getIconUrl(
                $subreddit->icon
            ),

            subscribersCount: $subreddit->subscribersCount
        );
    }

    private static function getIconUrl(
        ?string $icon
    ): ?string {
        if (!$icon) {
            return null;
        }

        $appUrl = rtrim(
            $_ENV['APP_URL'] ?? '',
            '/'
        );

        return $appUrl
            . '/uploads/subreddits/'
            . $icon;
    }
}
