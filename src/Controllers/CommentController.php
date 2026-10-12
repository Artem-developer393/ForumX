<?php

namespace App\Controllers;

use App\Services\CommentService;
use App\DTOs\Input\CreateCommentDTO;
use App\DTOs\Input\UpdateCommentDTO;
use App\Http\Request;
use App\Http\Response;
use App\Validators\CommentValidator;

class CommentController
{
    private CommentService $commentService;
    private CommentValidator $commentValidator;

    public function __construct()
    {
        $this->commentService = new CommentService();
        $this->commentValidator = new CommentValidator();
    }

    public function create(Request $request, int $postId): void
    {
        $userData = $request->user ?? null;
        $userId = (int)($userData->userId ?? $userData->id ?? 0);

        if ($userId === 0) {
            Response::error("Unauthorized: Invalid user in token", 401);
            return;
        }

        $requestData = $request->body ?? [];

        $dto = CreateCommentDTO::fromArray(
            $requestData,
            $postId,
            $userId
        );

        $errors = $this->commentValidator->validate($dto);

        if (!empty($errors)) {
            Response::validationError($errors);
            return;
        }

        try {
            $comment = $this->commentService->createComment($dto);

            Response::success([
                'message' => 'Comment created successfully',
                'comment' => $comment
            ], 201);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    public function update(Request $request, int $id): void
    {
        $userData = $request->user ?? null;
        $userId = (int)($userData->userId ?? $userData->id ?? 0);

        if ($userId === 0) {
            Response::error("Unauthorized: Invalid user in token", 401);
            return;
        }

        $userRole = (int)($userData->role ?? 1);

        $requestData = $request->body ?? [];

        $dto = UpdateCommentDTO::fromArray($requestData, $id, $userId, $userRole);

        $errors = $this->commentValidator->validateUpdate($dto);

        if (!empty($errors)) {
            Response::validationError($errors);
            return;
        }

        try {
            $comment = $this->commentService->updateComment($dto);

            Response::success([
                'message' => 'Comment updated successfully',
                'comment' => $comment
            ], 200);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 403);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    public function delete(Request $request, int $id): void
    {
        $userData = $request->user ?? null;
        $userId = (int)($userData->userId ?? $userData->id ?? 0);

        if ($userId === 0) {
            Response::error(
                "Unauthorized: Invalid user in token",
                401
            );
            return;
        }

        $userRole = (int)($userData->role ?? 1);

        try {
            $this->commentService->deleteComment($id, $userId, $userRole);

            Response::success([
                'message' => 'Comment deleted successfully'
            ], 200);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 403);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    public function index(Request $request, int $postId): void
    {
        try {
            if ($postId <= 0) {
                Response::error("Invalid post ID", 400);
                return;
            }

            $comments = $this->commentService->getCommentsByPost(
                $postId
            );

            Response::success([
                'data' => $comments,
                'count' => count($comments)
            ], 200);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Response::error(
                "Failed to retrieve comments: " . $e->getMessage(),
                500
            );
        }
    }

    public function getUserComments(Request $request, int $userId): void
    {
        $page = max(1, (int)($request->params['page'] ?? 1));

        $limit = max(1, min(100, (int)($request->params['limit'] ?? 20)));

        try {
            $result = $this->commentService->getCommentsByUser($userId, $page, $limit);

            Response::success($result, 200);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }
}
