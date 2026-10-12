<?php

namespace App\Controllers;

use App\Services\AdminService;
use App\DTOs\Input\AdminUpdateUserRoleDTO;
use App\Validators\AdminUserValidator;
use App\Http\Request;
use App\Http\Response;

class AdminController
{
    private AdminService $adminService;
    private AdminUserValidator $adminUserValidator;

    public function __construct()
    {
        $this->adminService = new AdminService();
        $this->adminUserValidator = new AdminUserValidator();
    }

    public function refreshTokenStats(Request $request): void
    {
        try {
            $stats = $this->adminService->getRefreshTokenStats();

            Response::success([
                'stats' => $stats
            ], 200);
        } catch (\Throwable $e) {
            error_log('Refresh token stats failed: ' . $e->getMessage());
            Response::error('Unable to get refresh token statistics', 500);
        }
    }

    public function cleanupRefreshTokens(Request $request): void
    {
        try {
            $deleted = $this->adminService->cleanupRefreshTokens();
            $adminId = (int)($request->user->id ?? 0);

            error_log('Refresh token cleanup: admin_id=' . $adminId . ', deleted_count=' . $deleted);

            Response::success([
                'message' => 'Refresh tokens cleanup completed',
                'deleted_count' => $deleted
            ], 200);
        } catch (\Throwable $e) {
            error_log('Refresh token cleanup failed: ' . $e->getMessage());
            Response::error('Unable to clean up refresh tokens', 500);
        }
    }

    public function users(Request $request): void
    {
        try {
            $page = max(
                1,
                (int)($request->params['page'] ?? 1)
            );

            $limit = max(
                1,
                min(
                    100,
                    (int)($request->params['limit'] ?? 20)
                )
            );

            $query = trim(
                (string)($request->params['q'] ?? '')
            );

            $role = isset($request->params['role'])
                && $request->params['role'] !== ''
                ? (int)$request->params['role']
                : null;

            $banned = null;

            if (isset($request->params['banned'])) {
                if ($request->params['banned'] === '1') {
                    $banned = true;
                } elseif ($request->params['banned'] === '0') {
                    $banned = false;
                }
            }

            $result = $this->adminService->getUsers(
                $page,
                $limit,
                $query,
                $role,
                $banned
            );

            Response::success($result, 200);
        } catch (\Throwable $e) {
            Response::error(
                "Failed to retrieve the user list: "
                    . $e->getMessage(),
                500
            );
        }
    }

    public function user(Request $request, int $id): void
    {
        try {
            if ($id <= 0) {
                Response::error("Invalid user ID", 400);
                return;
            }

            $user = $this->adminService->getUserById($id);

            Response::success([
                'user' => $user
            ], 200);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Throwable $e) {
            Response::error(
                "Failed to retrieve user: "
                    . $e->getMessage(),
                500
            );
        }
    }

    public function banUser(Request $request, int $id): void
    {
        $userData = $request->user ?? null;
        $adminId = (int)($userData->userId ?? $userData->id ?? 0);

        if ($adminId === 0) {
            Response::error(
                "Unauthorized: Invalid user in token",
                401
            );
            return;
        }

        try {
            $user = $this->adminService->banUser(
                $id,
                $adminId
            );

            Response::success([
                'message' => 'User blocked successfully',
                'user' => $user
            ], 200);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function unbanUser(Request $request, int $id): void
    {
        try {
            $user = $this->adminService->unbanUser($id);

            Response::success([
                'message' => 'User unblocked successfully',
                'user' => $user
            ], 200);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function changeUserRole(Request $request, int $id): void
    {
        $userData = $request->user ?? null;
        $adminId = (int)($userData->userId ?? $userData->id ?? 0);

        if ($adminId === 0) {
            Response::error(
                "Unauthorized: Invalid user in token",
                401
            );
            return;
        }

        $requestData = $request->body ?? [];

        $dto = AdminUpdateUserRoleDTO::fromArray($requestData);

        $errors = $this->adminUserValidator->validateRole($dto);

        if (!empty($errors)) {
            Response::validationError($errors);
            return;
        }

        try {
            $user = $this->adminService->changeUserRole(
                $id,
                $adminId,
                $dto
            );

            Response::success([
                'message' => 'User role updated successfully',
                'user' => $user
            ], 200);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function deleteUser(Request $request, int $id): void
    {
        $userData = $request->user ?? null;
        $adminId = (int)($userData->userId ?? $userData->id ?? 0);

        if ($adminId === 0) {
            Response::error(
                "Unauthorized: Invalid user in token",
                401
            );
            return;
        }

        try {
            $this->adminService->deleteUser(
                $id,
                $adminId
            );

            Response::success([
                'message' => 'User deleted successfully'
            ], 200);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function posts(Request $request): void
    {
        try {
            $page = isset($_GET['page'])
                ? (int)$_GET['page']
                : 1;

            $limit = isset($_GET['limit'])
                ? (int)$_GET['limit']
                : 20;

            $page = max(1, $page);
            $limit = max(1, min(100, $limit));

            $query = trim(
                (string)($request->params['q'] ?? '')
            );

            $locked = null;

            if (isset($request->params['locked'])) {
                if ($request->params['locked'] === '1') {
                    $locked = true;
                } elseif ($request->params['locked'] === '0') {
                    $locked = false;
                }
            }


            $result = $this->adminService->getPosts(
                $page,
                $limit,
                $query,
                $locked
            );

            Response::success($result);
        } catch (\Throwable $e) {
            Response::error(
                "Failed to retrieve the post list: "
                    . $e->getMessage(),
                500
            );
        }
    }
    public function post(Request $request, int $id): void
    {
        try {
            if ($id <= 0) {
                Response::error("Invalid post ID", 400);
                return;
            }

            $post = $this->adminService->getPostById($id);

            Response::success([
                'post' => $post
            ], 200);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Throwable $e) {
            Response::error(
                "Failed to retrieve post: "
                    . $e->getMessage(),
                500
            );
        }
    }
    public function lockPost(Request $request, int $id): void
    {
        try {
            if ($id <= 0) {
                Response::error("Invalid post ID", 400);
                return;
            }

            $post = $this->adminService->lockPost($id);

            Response::success([
                'message' => 'Post locked successfully',
                'post' => $post
            ], 200);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function unlockPost(Request $request, int $id): void
    {
        try {
            if ($id <= 0) {
                Response::error("Invalid post ID", 400);
                return;
            }

            $post = $this->adminService->unlockPost($id);

            Response::success([
                'message' => 'Post unlocked successfully',
                'post' => $post
            ], 200);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function deletePost(Request $request, int $id): void
    {
        try {
            if ($id <= 0) {
                Response::error("Invalid post ID", 400);
                return;
            }

            $this->adminService->deletePost($id);

            Response::success([
                'message' => 'Post deleted successfully'
            ], 200);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function comments(Request $request): void
    {
        try {
            $page = isset($_GET['page'])
                ? (int)$_GET['page']
                : 1;

            $limit = isset($_GET['limit'])
                ? (int)$_GET['limit']
                : 20;

            $page = max(1, $page);
            $limit = max(1, min(100, $limit));

            $query = trim(
                (string)($request->params['q'] ?? '')
            );

            $deleted = null;

            if (isset($request->params['deleted'])) {
                if ($request->params['deleted'] === '1') {
                    $deleted = true;
                } elseif ($request->params['deleted'] === '0') {
                    $deleted = false;
                }
            }

            $result = $this->adminService->getComments(
                $page,
                $limit,
                $query,
                $deleted
            );

            Response::success($result);
        } catch (\Throwable $e) {
            Response::error(
                "Failed to retrieve the comment list: "
                    . $e->getMessage(),
                500
            );
        }
    }

    public function comment(Request $request, int $id): void
    {
        try {
            if ($id <= 0) {
                Response::error(
                    "Invalid comment ID",
                    400
                );
                return;
            }

            $comment = $this->adminService->getCommentById($id);

            Response::success([
                'comment' => $comment
            ], 200);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    public function deleteComment(Request $request, int $id): void
    {
        try {
            if ($id <= 0) {
                Response::error(
                    "Invalid comment ID",
                    400
                );
                return;
            }

            $this->adminService->deleteComment($id);

            Response::success([
                'message' => 'Comment deleted successfully'
            ], 200);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function subreddits(Request $request): void
    {
        try {
            $page = isset($_GET['page'])
                ? (int)$_GET['page']
                : 1;

            $limit = isset($_GET['limit'])
                ? (int)$_GET['limit']
                : 20;

            $page = max(1, $page);
            $limit = max(1, min(100, $limit));

            $query = trim(
                (string)($request->params['q'] ?? '')
            );

            $locked = null;

            if (isset($request->params['locked'])) {
                if ($request->params['locked'] === '1') {
                    $locked = true;
                } elseif ($request->params['locked'] === '0') {
                    $locked = false;
                }
            }

            $result = $this->adminService->getSubreddits(
                $page,
                $limit,
                $query,
                $locked
            );

            Response::success($result, 200);
        } catch (\Throwable $e) {
            Response::error(
                "Failed to retrieve the subreddit list: "
                    . $e->getMessage(),
                500
            );
        }
    }

    public function subreddit(Request $request, int $id): void
    {
        try {
            $subreddit = $this->adminService->getSubredditById($id);

            Response::success([
                'subreddit' => $subreddit
            ], 200);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    public function lockSubreddit(Request $request, int $id): void
    {
        try {
            $subreddit = $this->adminService->lockSubreddit($id);

            Response::success([
                'message' => 'Subreddit blocked successfully',
                'subreddit' => $subreddit
            ], 200);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function unlockSubreddit(Request $request, int $id): void
    {
        try {
            $subreddit = $this->adminService->unlockSubreddit($id);

            Response::success([
                'message' => 'Subreddit unblocked successfully',
                'subreddit' => $subreddit
            ], 200);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function deleteSubreddit(Request $request, int $id): void
    {
        try {
            $this->adminService->deleteSubreddit($id);

            Response::success([
                'message' => 'Subreddit deleted successfully'
            ], 200);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function reports(Request $request): void
    {
        $page = max(1, (int)($request->params['page'] ?? 1));

        $limit = max(1, min((int)($request->params['limit'] ?? 20), 100));

        $status = trim(
            (string)($request->params['status'] ?? '')
        );

        $status = $status !== ''
            ? strtolower($status)
            : null;

        try {
            $result = $this->adminService->getReports(
                $page,
                $limit,
                $status
            );

            Response::success([
                'reports' => $result
            ], 200);
        } catch (\InvalidArgumentException $e) {
            Response::error(
                $e->getMessage(),
                422
            );
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function report(Request $request, int $reportId): void
    {
        try {
            $report = $this->adminService->getReportById($reportId);

            Response::success([
                'report' => $report
            ], 200);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    public function resolveReport(Request $request, int $reportId): void
    {
        $userData = $request->user ?? null;

        $adminId = (int)(
            $userData->userId ??
            $userData->id ??
            0
        );

        if ($adminId === 0) {
            Response::error(
                "Unauthorized: Invalid user in token",
                401
            );
            return;
        }

        try {
            $report = $this->adminService->resolveReport($reportId, $adminId);

            Response::success([
                'message' => 'Report processed successfully',
                'report' => $report
            ], 200);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function rejectReport(Request $request, int $reportId): void
    {
        $userData = $request->user ?? null;

        $adminId = (int)($userData->userId ?? $userData->id ?? 0);

        if ($adminId === 0) {
            Response::error(
                "Unauthorized: Invalid user in token",
                401
            );
            return;
        }

        try {
            $report = $this->adminService->rejectReport($reportId, $adminId);

            Response::success([
                'message' => 'Report rejected',
                'report' => $report
            ], 200);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function dashboard(Request $request): void
    {
        try {
            $dashboard = $this->adminService->getDashboard();

            Response::success([
                'dashboard' => $dashboard
            ], 200);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }
}
