<?php

namespace App\Services;

use App\Repositories\PostRepository;
use App\Repositories\SubredditRepository;
use App\Repositories\UserRepository;
use App\DTOs\Output\PostResponseDTO;
use App\DTOs\Output\SubredditResponseDTO;
use App\DTOs\Output\PublicUserResponseDTO;
use InvalidArgumentException;

class SearchService
{
    private PostRepository $postRepository;
    private SubredditRepository $subredditRepository;
    private UserRepository $userRepository;

    public function __construct()
    {
        $this->postRepository = new PostRepository();
        $this->subredditRepository = new SubredditRepository();
        $this->userRepository = new UserRepository();
    }

    public function search(string $query, string $type, int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;

        return match ($type) {
            'posts' => $this->searchPosts($query, $page, $limit, $offset),

            'subreddits' => $this->searchSubreddits($query, $page, $limit, $offset),

            'users' => $this->searchUsers($query, $page, $limit, $offset),

            default => throw new InvalidArgumentException(
                'Invalid search type'
            )
        };
    }

    private function searchPosts(string $query, int $page, int $limit, int $offset): array
    {
        $posts = $this->postRepository->searchByTitle(
            $query,
            $limit,
            $offset
        );

        $total = $this->postRepository->countSearchByTitle($query);

        return [
            'data' => array_map(
                fn($post) => PostResponseDTO::fromModel($post),
                $posts
            ),
            'count' => count($posts),
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ];
    }

    private function searchSubreddits(string $query, int $page, int $limit, int $offset): array
    {
        $subreddits = $this->subredditRepository->searchByTitle(
            $query,
            $limit,
            $offset
        );

        $total = $this->subredditRepository->countSearchByTitle($query);

        return [
            'data' => array_map(
                fn($subreddit) =>
                SubredditResponseDTO::fromModel($subreddit),
                $subreddits
            ),
            'count' => count($subreddits),
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ];
    }

    private function searchUsers(string $query, int $page, int $limit, int $offset): array
    {
        $users = $this->userRepository->searchByLogin(
            $query,
            $limit,
            $offset
        );

        $total = $this->userRepository->countSearchByLogin($query);

        return [
            'data' => array_map(
                fn(array $user) =>
                PublicUserResponseDTO::fromArray($user),
                $users
            ),
            'count' => count($users),
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ];
    }
}
