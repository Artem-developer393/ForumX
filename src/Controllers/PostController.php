<?php

namespace App\Controllers;

use App\Services\PostService;
use App\DTOs\Input\CreatePostDTO;
use App\DTOs\Input\UpdatePostDTO;
use App\Http\Request;
use App\Http\Response;
use App\Validators\PostValidator;
use App\Validators\ImageValidator;

class PostController
{
    private PostService $postService;
    private PostValidator $postValidator;
    private ImageValidator $imageValidator;

    public function __construct()
    {
        $this->postService = new PostService();
        $this->postValidator = new PostValidator();
        $this->imageValidator = new ImageValidator();
    }

    public function feed(Request $request)
    {
        $userData = $request->user ?? null;
        $userId = (int)($userData->userId ?? $userData->id ?? 0);

        if ($userId === 0) {
            Response::error("Unauthorized: Invalid user in token", 401);
            return;
        }

        try {
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

            $page = max(1, $page);
            $limit = max(1, min(100, $limit));

            $posts = $this->postService->getAllSmartPosts($userId, $page, $limit);

            Response::success([
                'data' => $posts,
                'count' => count($posts),
                'page' => $page,
                'limit' => $limit
            ]);
        } catch (\Throwable $e) {
            Response::error("Failed to retrieve the post list: " . $e->getMessage(), 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $sort = strtolower(trim((string)($request->params['sort'] ?? 'new')));

            $page = max(1, $page);
            $limit = max(1, min(100, $limit));

            $result = $this->postService->getAllPosts($page, $limit, $sort);

            Response::success($result);
        } catch (\InvalidArgumentException $e) {
            Response::error(
                $e->getMessage(),
                422
            );
        } catch (\Exception $e) {
            Response::error(
                $e->getMessage(),
                500
            );
        }
    }

    public function show(Request $request, int $id): void
    {
        try {
            $post = $this->postService->getPostById($id);

            Response::success([
                'data' => $post
            ], 200);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Response::error("Server error: " . $e->getMessage(), 500);
        }
    }

    public function bySubreddit(Request $request, int $subredditId): void
    {
        try {
            if ($subredditId <= 0) {
                Response::error("Invalid subreddit ID", 400);
                return;
            }

            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

            $page = max(1, $page);
            $limit = max(1, min(100, $limit));

            $posts = $this->postService->getPostBySubreddit($subredditId, $page, $limit);
            Response::success([
                'data' => $posts,
                'count' => count($posts),
                'page' => $page,
                'limit' => $limit
            ]);
        } catch (\Throwable $e) {
            Response::error("Failed to retrieve the post list: " . $e->getMessage(), 500);
        }
    }

    public function create(Request $request): void
    {
        $userData = $request->user ?? null;
        $userId = (int)($userData->userId ?? $userData->id ?? 0);

        if ($userId === 0) {
            Response::error("Unauthorized: Invalid user in token", 401);
            return;
        }

        $requestData = $request->body ?? [];
        $dto = CreatePostDTO::fromArray($requestData, $userId);

        $errors = $this->postValidator->validate($dto);
        if (!empty($errors)) {
            Response::validationError($errors);
            return;
        }

        try {
            $post = $this->postService->createPost($dto);

            Response::success([
                'message' => 'Post created successfully',
                'post' => $post
            ], 201);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function update(Request $request, int $id)
    {
        $userData =  $request->user ?? null;
        $userId = (int)($userData->userId ?? $userData->id ?? 0);

        if ($userId === 0) {
            Response::error("Unauthorized: Invalid user in token", 401);
            return;
        }

        $userRole = (int)($userData->role ?? 1);

        $requestData = $request->body ?? [];
        $dto = UpdatePostDTO::fromArray($requestData, $id, $userId, $userRole);

        $errors = $this->postValidator->validateUpdate($dto);
        if (!empty($errors)) {
            Response::validationError($errors);
            return;
        }

        try {
            $post = $this->postService->updatePost($dto);

            Response::success([
                'message' => 'Post updated successfully',
                'subreddit' => $post
            ]);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            $code = ($e->getMessage() === "You do not have permission to edit this post") ? 403 : 400;
            Response::error($e->getMessage(), $code);
        }
    }

    public function delete(Request $request, int $id): void
    {
        $userData =  $request->user ?? null;
        $userId = (int)($userData->userId ?? $userData->id ?? 0);
        $userRole = (int)($userData->role ?? 1);

        try {
            $this->postService->deletePost($id, $userId, $userRole);
            Response::success([
                'message' => 'Post deleted successfully'
            ], 200);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            $code = ($e->getMessage() === "You do not have permission to delete this post") ? 403 : 400;
            Response::error($e->getMessage(), $code);
        }
    }

    public function toggleLock(Request $request, int $id): void
    {
        $userData = $request->user ?? null;
        $userId = (int)($userData->userId ?? $userData->id ?? 0);
        $userRole = (int)($userData->role ?? 1);

        if ($userId === 0) {
            Response::error("Unauthorized: Invalid user in token", 401);
            return;
        }

        try {
            $result = $this->postService->toggleLock($id, $userId, $userRole);

            Response::success([
                'message' => $result['message'],
                'is_lock' => $result['is_lock']
            ]);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 403);
        } catch (\Exception $e) {
            error_log($e->getMessage());
            Response::error("Internal server error", 500);
        }
    }
    public function updateImage(Request $request, int $id): void
    {
        $userData = $request->user ?? null;

        $userId = (int)($userData->userId ?? $userData->id ?? 0);

        $userRole = (int)($userData->role ?? 1);

        if ($userId === 0) {
            Response::error(
                "Unauthorized: Invalid user in token",
                401
            );
            return;
        }

        $file = $_FILES['image'] ?? null;

        $errors = $this->imageValidator->validate(
            $file,
            'image'
        );

        if (!empty($errors)) {
            Response::validationError($errors);

            return;
        }

        try {
            $post = $this->postService->updateImage(
                $id,
                $userId,
                $userRole,
                $file
            );

            Response::success([
                'message' => 'Post image updated successfully',
                'post' => $post
            ], 200);
        } catch (\DomainException $e) {
            Response::error(
                $e->getMessage(),
                404
            );
        } catch (\Exception $e) {
            $code = str_contains(
                $e->getMessage(),
                'permission'
            ) ? 403 : 400;

            Response::error($e->getMessage(), $code);
        }
    }

    public function deleteImage(Request $request, int $id): void
    {
        $userData = $request->user ?? null;

        $userId = (int)($userData->userId ?? $userData->id ?? 0);

        $userRole = (int)($userData->role ?? 1);

        if ($userId === 0) {
            Response::error(
                "Unauthorized: Invalid user in token",
                401
            );
            return;
        }

        try {
            $post = $this->postService->deleteImage($id, $userId, $userRole);

            Response::success([
                'message' => 'Post image deleted successfully',
                'post' => $post
            ], 200);
        } catch (\DomainException $e) {
            Response::error(
                $e->getMessage(),
                404
            );
        } catch (\Exception $e) {
            $code = str_contains(
                $e->getMessage(),
                'permission'
            ) ? 403 : 400;

            Response::error($e->getMessage(), $code);
        }
    }
}
