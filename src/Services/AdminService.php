<?php

namespace App\Services;

use App\DTOs\Input\AdminUpdateUserRoleDTO;
use App\DTOs\Output\AdminPostResponseDTO;
use App\DTOs\Output\AdminUserResponseDTO;
use App\DTOs\Output\AdminCommentResponseDTO;
use App\DTOs\Output\AdminSubredditResponseDTO;
use App\DTOs\Output\PaginatedResponseDTO;
use App\DTOs\Output\AdminReportResponseDTO;
use App\DTOs\Output\AdminDashboardResponseDTO;
use App\DTOs\Output\RefreshTokenStatsResponseDTO;
use App\Repositories\RefreshTokenRepository;
use App\Repositories\UserRepository;
use App\Repositories\PostRepository;
use App\Repositories\CommentRepository;
use App\Repositories\SubredditRepository;
use App\Repositories\ReportRepository;



class AdminService
{
    private UserRepository $userRepository;
    private CommentRepository $commentRepository;
    private PostRepository $postRepository;
    private SubredditRepository $subredditRepository;
    private ReportRepository $reportRepository;
    private RefreshTokenRepository $refreshTokenRepository;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
        $this->postRepository = new PostRepository();
        $this->commentRepository = new CommentRepository();
        $this->subredditRepository = new SubredditRepository();
        $this->reportRepository = new ReportRepository();
        $this->refreshTokenRepository = new RefreshTokenRepository();
    }

    public function getRefreshTokenStats(): RefreshTokenStatsResponseDTO
    {
        $stats = $this->refreshTokenRepository->getStats();
        return RefreshTokenStatsResponseDTO::fromArray($stats);
    }

    public function cleanupRefreshTokens(): int
    {
        $total = 0;

        for ($batch = 0; $batch < 10; $batch++) {
            $deleted = $this->refreshTokenRepository->deleteExpired(1000);
            $total += $deleted;

            if ($deleted < 1000) {
                break;
            }
        }

        return $total;
    }

    public function getUsers(
        int $page = 1,
        int $limit = 20,
        string $query = '',
        ?int $role = null,
        ?bool $banned = null
    ): PaginatedResponseDTO {
        $offset = ($page - 1) * $limit;

        $users = $this->userRepository->findAllForAdmin(
            $limit,
            $offset,
            $query,
            $role,
            $banned
        );

        $data = array_map(
            fn($user) =>
            AdminUserResponseDTO::fromModel($user),
            $users
        );

        $total = $this->userRepository->countForAdmin(
            $query,
            $role,
            $banned
        );

        return new PaginatedResponseDTO(
            data: $data,
            total: $total,
            page: $page,
            limit: $limit
        );
    }

    public function getUserById(int $id): AdminUserResponseDTO
    {
        $user = $this->userRepository->findById($id);

        if (!$user) {
            throw new \DomainException("User not found");
        }

        return AdminUserResponseDTO::fromModel($user);
    }

    public function banUser(
        int $userId,
        int $adminId
    ): AdminUserResponseDTO {
        $user = $this->userRepository->findById($userId);

        if (!$user) {
            throw new \DomainException("User not found");
        }

        if ($userId === $adminId) {
            throw new \Exception("You cannot block your own account");
        }

        if ($user->isBanned) {
            throw new \Exception("User is already blocked");
        }

        $this->userRepository->ban($userId);

        $updatedUser = $this->userRepository->findById($userId);

        if (!$updatedUser) {
            throw new \Exception(
                "Failed to retrieve the updated user"
            );
        }

        return AdminUserResponseDTO::fromModel($updatedUser);
    }

    public function unbanUser(int $userId): AdminUserResponseDTO
    {
        $user = $this->userRepository->findById($userId);

        if (!$user) {
            throw new \DomainException("User not found");
        }

        if (!$user->isBanned) {
            throw new \Exception("User is not blocked");
        }

        $this->userRepository->unban($userId);

        $updatedUser = $this->userRepository->findById($userId);

        if (!$updatedUser) {
            throw new \Exception(
                "Failed to retrieve the updated user"
            );
        }

        return AdminUserResponseDTO::fromModel($updatedUser);
    }

    public function changeUserRole(int $userId, int $adminId, AdminUpdateUserRoleDTO $dto): AdminUserResponseDTO
    {
        $user = $this->userRepository->findById($userId);

        if (!$user) {
            throw new \DomainException("User not found");
        }

        if ($userId === $adminId) {
            throw new \Exception(
                "You cannot change your own role"
            );
        }

        if ($user->role === $dto->role) {
            throw new \Exception(
                "User already has this role"
            );
        }

        $this->userRepository->updateRole(
            $userId,
            $dto->role
        );

        $updatedUser = $this->userRepository->findById($userId);

        if (!$updatedUser) {
            throw new \Exception(
                "Failed to retrieve the updated user"
            );
        }

        return AdminUserResponseDTO::fromModel($updatedUser);
    }

    public function deleteUser(int $userId, int $adminId): void
    {
        $user = $this->userRepository->findById($userId);

        if (!$user) {
            throw new \DomainException(
                "User not found"
            );
        }

        if ($userId === $adminId) {
            throw new \Exception(
                "You cannot delete your own account"
            );
        }

        $avatar = $user->avatar;

        $postImages =
            $this->postRepository
            ->findImagesByUserId($userId);

        $subreddits =
            $this->subredditRepository
            ->findByCreatorId($userId);

        $subredditIcons = [];

        foreach ($subreddits as $subreddit) {
            if ($subreddit->icon) {
                $subredditIcons[] = $subreddit->icon;
            }

            $images =
                $this->postRepository
                ->findImagesBySubreddit($subreddit->id);

            foreach ($images as $image) {
                $postImages[] = $image;
            }
        }

        $reportsDeleted =
            $this->reportRepository
            ->deleteByUserContent($userId);

        if (!$reportsDeleted) {
            throw new \Exception(
                "Failed to delete related reports"
            );
        }

        foreach ($subreddits as $subreddit) {
            $deleted =
                $this->subredditRepository
                ->delete($subreddit->id);

            if (!$deleted) {
                throw new \Exception(
                    "Failed to delete the subreddit created by the user"
                );
            }
        }

        $postsDeleted =
            $this->postRepository
            ->deleteByUserId($userId);

        if (!$postsDeleted) {
            throw new \Exception(
                "Failed to delete the user's posts"
            );
        }

        $commentsDeleted =
            $this->commentRepository
            ->deleteTreesByUserId($userId);

        if (!$commentsDeleted) {
            throw new \Exception(
                "Failed to delete the user's comments"
            );
        }

        $deleted =
            $this->userRepository->delete($userId);

        if (!$deleted) {
            throw new \Exception(
                "Failed to delete user"
            );
        }

        $postsUploadDirectory =
            dirname(__DIR__, 2)
            . '/uploads/posts/';

        foreach (array_unique($postImages) as $image) {
            if (!$image) {
                continue;
            }

            $imagePath =
                $postsUploadDirectory
                . basename($image);

            if (is_file($imagePath)) {
                unlink($imagePath);
            }
        }

        $subredditsUploadDirectory =
            dirname(__DIR__, 2)
            . '/uploads/subreddits/';

        foreach (array_unique($subredditIcons) as $icon) {
            if (!$icon) {
                continue;
            }

            $iconPath =
                $subredditsUploadDirectory
                . basename($icon);

            if (is_file($iconPath)) {
                unlink($iconPath);
            }
        }

        if ($avatar) {
            $avatarPath =
                dirname(__DIR__, 2)
                . '/uploads/avatars/'
                . basename($avatar);

            if (is_file($avatarPath)) {
                unlink($avatarPath);
            }
        }
    }

    public function getPosts(
        int $page = 1,
        int $limit = 20,
        string $query = '',
        ?bool $locked = null
    ): PaginatedResponseDTO {
        $offset = ($page - 1) * $limit;

        $rows = $this->postRepository->findAllForAdmin(
            $limit,
            $offset,
            $query,
            $locked
        );

        $data = array_map(
            fn(array $row) =>
            AdminPostResponseDTO::fromArray($row),
            $rows
        );

        return new PaginatedResponseDTO(
            data: $data,
            total: $this->postRepository->countForAdmin(
                $query,
                $locked
            ),
            page: $page,
            limit: $limit
        );
    }

    public function getPostById(int $id): AdminPostResponseDTO
    {
        $row = $this->postRepository->findByIdForAdmin($id);

        if (!$row) {
            throw new \DomainException("Post not found");
        }

        return AdminPostResponseDTO::fromArray($row);
    }

    public function lockPost(int $postId): AdminPostResponseDTO
    {
        $post = $this->postRepository->findById($postId);

        if (!$post) {
            throw new \DomainException("Post not found");
        }

        if ($post->isLocked) {
            throw new \Exception("Post is already locked");
        }

        $updated = $this->postRepository->lock($postId);

        if (!$updated) {
            throw new \Exception("Failed to lock post");
        }

        $post = $this->postRepository->findByIdForAdmin($postId);

        return AdminPostResponseDTO::fromArray($post);
    }

    public function unlockPost(int $postId): AdminPostResponseDTO
    {
        $post = $this->postRepository->findById($postId);

        if (!$post) {
            throw new \DomainException("Post not found");
        }

        if (!$post->isLocked) {
            throw new \Exception("Post is not locked");
        }

        $updated = $this->postRepository->unlock($postId);

        if (!$updated) {
            throw new \Exception("Failed to unlock post");
        }

        $post = $this->postRepository->findByIdForAdmin($postId);

        return AdminPostResponseDTO::fromArray($post);
    }

    public function deletePost(int $postId): void
    {
        $post = $this->postRepository->findById($postId);

        if (!$post) {
            throw new \DomainException("Post not found");
        }

        $image = $post->image;

        $reportsDeleted =
            $this->reportRepository->deleteByPostWithComments($postId);

        if (!$reportsDeleted) {
            throw new \Exception(
                "Failed to delete related reports"
            );
        }

        $deleted = $this->postRepository->delete($postId);

        if (!$deleted) {
            throw new \Exception(
                "Failed to delete post"
            );
        }

        if ($image) {
            $uploadDirectory =
                dirname(__DIR__, 2)
                . '/uploads/posts/';

            $imagePath =
                $uploadDirectory . basename($image);

            if (is_file($imagePath)) {
                unlink($imagePath);
            }
        }
    }

    public function getComments(
        int $page = 1,
        int $limit = 20,
        string $query = '',
        ?bool $deleted = null
    ): PaginatedResponseDTO {
        $offset = ($page - 1) * $limit;

        $rows = $this->commentRepository->findAllForAdmin(
            $limit,
            $offset,
            $query,
            $deleted
        );

        $comments = array_map(
            fn(array $row) =>
            AdminCommentResponseDTO::fromArray($row),
            $rows
        );

        return new PaginatedResponseDTO(
            data: $comments,
            total: $this->commentRepository->countForAdmin(
                $query,
                $deleted
            ),
            page: $page,
            limit: $limit
        );
    }
    public function getCommentById(int $commentId): AdminCommentResponseDTO
    {
        $row = $this->commentRepository->findByIdForAdmin(
            $commentId
        );

        if (!$row) {
            throw new \DomainException("Comment not found");
        }

        return AdminCommentResponseDTO::fromArray($row);
    }

    public function deleteComment(int $commentId): void
    {
        $comment = $this->commentRepository->findById($commentId);

        if (!$comment) {
            throw new \DomainException("Comment not found");
        }

        if ($comment->isDeleted) {
            throw new \Exception("Comment is already deleted");
        }

        $deleted = $this->commentRepository->delete($commentId);

        if (!$deleted) {
            throw new \Exception("Failed to delete comment");
        }
    }

    public function getSubreddits(
        int $page = 1,
        int $limit = 20,
        string $query = '',
        ?bool $locked = null
    ): PaginatedResponseDTO {
        $offset = ($page - 1) * $limit;

        $rows = $this->subredditRepository->findAllForAdmin(
            $limit,
            $offset,
            $query,
            $locked
        );

        $subreddits = array_map(
            fn(array $row) =>
            AdminSubredditResponseDTO::fromArray($row),
            $rows
        );

        return new PaginatedResponseDTO(
            data: $subreddits,
            total: $this->subredditRepository->countForAdmin(
                $query,
                $locked
            ),
            page: $page,
            limit: $limit
        );
    }

    public function getSubredditById(int $subredditId): AdminSubredditResponseDTO
    {
        $row = $this->subredditRepository->findByIdForAdmin(
            $subredditId
        );

        if (!$row) {
            throw new \DomainException("Subreddit not found");
        }

        return AdminSubredditResponseDTO::fromArray($row);
    }

    public function lockSubreddit(int $subredditId): AdminSubredditResponseDTO
    {
        $subreddit = $this->subredditRepository->findById($subredditId);

        if (!$subreddit) {
            throw new \DomainException("Subreddit not found");
        }

        if ($subreddit->isLocked) {
            throw new \Exception("Subreddit is already blocked");
        }

        $updated = $this->subredditRepository->lock($subredditId);

        if (!$updated) {
            throw new \Exception("Failed to block subreddit");
        }

        $row = $this->subredditRepository->findByIdForAdmin($subredditId);

        return AdminSubredditResponseDTO::fromArray($row);
    }

    public function unlockSubreddit(int $subredditId): AdminSubredditResponseDTO
    {
        $subreddit = $this->subredditRepository->findById($subredditId);

        if (!$subreddit) {
            throw new \DomainException("Subreddit not found");
        }

        if (!$subreddit->isLocked) {
            throw new \Exception("Subreddit is not blocked");
        }

        $updated = $this->subredditRepository->unlock($subredditId);

        if (!$updated) {
            throw new \Exception("Failed to unblock subreddit");
        }

        $row = $this->subredditRepository->findByIdForAdmin($subredditId);

        return AdminSubredditResponseDTO::fromArray($row);
    }

    public function deleteSubreddit(int $subredditId): void
    {
        $subreddit =
            $this->subredditRepository->findById($subredditId);

        if (!$subreddit) {
            throw new \DomainException(
                "Subreddit not found"
            );
        }

        $icon = $subreddit->icon;

        $postImages =
            $this->postRepository
            ->findImagesBySubreddit($subredditId);

        $reportsDeleted =
            $this->reportRepository
            ->deleteBySubredditWithContent($subredditId);

        if (!$reportsDeleted) {
            throw new \Exception(
                "Failed to delete related reports"
            );
        }

        $deleted =
            $this->subredditRepository->delete($subredditId);

        if (!$deleted) {
            throw new \Exception(
                "Failed to delete subreddit"
            );
        }

        $postsUploadDirectory =
            dirname(__DIR__, 2)
            . '/uploads/posts/';

        foreach ($postImages as $image) {
            if (!$image) {
                continue;
            }

            $imagePath =
                $postsUploadDirectory
                . basename($image);

            if (is_file($imagePath)) {
                unlink($imagePath);
            }
        }

        if ($icon) {
            $subredditsUploadDirectory =
                dirname(__DIR__, 2)
                . '/uploads/subreddits/';

            $iconPath =
                $subredditsUploadDirectory
                . basename($icon);

            if (is_file($iconPath)) {
                unlink($iconPath);
            }
        }
    }

    public function getReports(
        int $page = 1,
        int $limit = 20,
        ?string $status = null
    ): PaginatedResponseDTO {
        $page = max(1, $page);
        $limit = max(1, min($limit, 100));

        if (
            $status !== null &&
            !in_array(
                $status,
                ['pending', 'resolved', 'rejected'],
                true
            )
        ) {
            throw new \InvalidArgumentException(
                "Invalid report status"
            );
        }

        $offset = ($page - 1) * $limit;

        $rows = $this->reportRepository->findAllForAdmin(
            $limit,
            $offset,
            $status
        );

        $reports = array_map(
            fn(array $row) =>
            AdminReportResponseDTO::fromArray($row),
            $rows
        );

        return new PaginatedResponseDTO(
            data: $reports,
            total: $this->reportRepository->countForAdmin(
                $status
            ),
            page: $page,
            limit: $limit
        );
    }
    public function getReportById(
        int $reportId
    ): AdminReportResponseDTO {
        $report = $this->reportRepository->findByIdForAdmin(
            $reportId
        );

        if (!$report) {
            throw new \DomainException("Report not found");
        }

        return AdminReportResponseDTO::fromArray($report);
    }

    public function resolveReport(
        int $reportId,
        int $adminId
    ): AdminReportResponseDTO {
        $report = $this->reportRepository->findById($reportId);

        if (!$report) {
            throw new \DomainException("Report not found");
        }

        if ($report->status !== 'pending') {
            throw new \Exception(
                "This report has already been processed"
            );
        }

        $updated = $this->reportRepository->resolve(
            $reportId,
            $adminId
        );

        if (!$updated) {
            throw new \Exception(
                "Failed to process report"
            );
        }

        $report = $this->reportRepository->findByIdForAdmin(
            $reportId
        );

        if (!$report) {
            throw new \DomainException("Report not found");
        }

        return AdminReportResponseDTO::fromArray($report);
    }

    public function rejectReport(
        int $reportId,
        int $adminId
    ): AdminReportResponseDTO {
        $report = $this->reportRepository->findById($reportId);

        if (!$report) {
            throw new \DomainException("Report not found");
        }

        if ($report->status !== 'pending') {
            throw new \Exception(
                "This report has already been processed"
            );
        }

        $updated = $this->reportRepository->reject(
            $reportId,
            $adminId
        );

        if (!$updated) {
            throw new \Exception(
                "Failed to reject report"
            );
        }

        $report = $this->reportRepository->findByIdForAdmin(
            $reportId
        );

        if (!$report) {
            throw new \DomainException("Report not found");
        }

        return AdminReportResponseDTO::fromArray($report);
    }

    public function getDashboard(): AdminDashboardResponseDTO
    {
        return new AdminDashboardResponseDTO(
            users: $this->userRepository->countAll(),
            bannedUsers: $this->userRepository->countBanned(),

            posts: $this->postRepository->countAll(),
            lockedPosts: $this->postRepository->countLocked(),

            comments: $this->commentRepository->countActive(),

            subreddits: $this->subredditRepository->countAll(),
            lockedSubreddits: $this->subredditRepository->countLocked(),

            pendingReports: $this->reportRepository->countPending()
        );
    }
}
