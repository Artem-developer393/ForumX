<?php

namespace App\DTOs\Output;

use App\Models\Post;

class PostResponseDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $subredditId,
        public readonly ?int $authorId,
        public readonly string $title,
        public readonly string $body,
        public readonly bool $isLocked,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,

        public readonly ?string $imageUrl,
        public readonly ?PostAuthorResponseDTO $author,
        public readonly PostSubredditResponseDTO $subreddit,
        public readonly int $commentsCount
    ) {}

    public static function fromModel(Post $post): self
    {
        return new self(
            id: $post->id,
            subredditId: $post->subredditId,
            authorId: $post->creatorId,
            title: $post->title,
            body: $post->body,
            isLocked: $post->isLocked,
            createdAt: $post->createdAt,
            updatedAt: $post->updatedAt,

            imageUrl: self::getImageUrl($post->image),

            author: PostAuthorResponseDTO::fromData(
                $post->creatorId,
                $post->authorLogin,
                $post->authorAvatar
            ),

            subreddit: new PostSubredditResponseDTO(
                id: $post->subredditId,
                title: $post->subredditTitle ?? ''
            ),

            commentsCount: $post->commentsCount
        );
    }

    private static function getImageUrl(?string $image): ?string
    {
        if (!$image) {
            return null;
        }

        $appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');

        return $appUrl . '/uploads/posts/' . $image;
    }
}
