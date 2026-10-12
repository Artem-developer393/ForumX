<?php

namespace App\DTOs\Output;

class AdminDashboardResponseDTO
{
    public function __construct(
        public readonly int $users,
        public readonly int $bannedUsers,
        public readonly int $posts,
        public readonly int $lockedPosts,
        public readonly int $comments,
        public readonly int $subreddits,
        public readonly int $lockedSubreddits,
        public readonly int $pendingReports
    ) {}
}