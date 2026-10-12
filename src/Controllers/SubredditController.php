<?php


namespace App\controllers;

use App\DTOs\Input\CreateSubredditDTO;
use App\DTOs\Input\UpdateSubredditDTO;
use App\Http\Request;
use App\Http\Response;
use App\Services\SubredditService;
use App\Validators\SubredditValidator;
use App\Validators\ImageValidator;

class SubredditController
{

    private SubredditService $subredditService;
    private SubredditValidator $subredditValidator;
    private ImageValidator $imageValidator;

    public function __construct()
    {
        $this->subredditService = new SubredditService();
        $this->subredditValidator = new SubredditValidator();
        $this->imageValidator = new ImageValidator();
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
        $dto = CreateSubredditDTO::fromArray($requestData, $userId);

        $errors = $this->subredditValidator->validate($dto);

        if (!empty($errors)) {
            Response::validationError($errors);
            return;
        }

        try {
            $subreddit = $this->subredditService->createSubreddit($dto);

            Response::success([
                'message' => 'Subreddit created successfully',
                'subreddit' => $subreddit
            ], 201);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 500);
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
        $dto = UpdateSubredditDTO::fromArray($requestData, $id, $userId, $userRole);

        $errors = $this->subredditValidator->validateUpdate($dto);
        if (!empty($errors)) {
            Response::validationError($errors);
            return;
        }

        try {
            $subreddit = $this->subredditService->updateSubreddit($dto);

            Response::success([
                'message' => 'Subreddit updated successfully',
                'subreddit' => $subreddit
            ]);
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
        $userData =  $request->user ?? null;
        $userId = (int)($userData->userId ?? $userData->id ?? 0);
        $userRole = (int)($userData->role ?? 1);

        try {
            $this->subredditService->deleteSubreddit($id, $userId, $userRole);
            Response::success([
                'message' => 'Subreddit deleted successfully'
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

    public function index(): void
    {
        try {
            $subreddits = $this->subredditService->getAllSubreddits();

            Response::success([
                'data' => $subreddits,
                'count' => count($subreddits)
            ], 200);
        } catch (\Exception $e) {
            Response::error("Failed to retrieve the subreddit list: " . $e->getMessage(), 500);
        }
    }

    public function show(Request $request, int $id): void
    {
        try {
            $subreddit = $this->subredditService->getSubredditById($id);

            Response::success([
                'data' => $subreddit
            ], 200);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Response::error("Server error: " . $e->getMessage(), 500);
        }
    }

    public function updateIcon(Request $request, int $id): void
    {
        $userData = $request->user ?? null;

        $userId = (int)($userData->userId ?? $userData->id ?? 0);

        $userRole = (int)(
            $userData->role ?? 1
        );

        if ($userId === 0) {
            Response::error(
                "Unauthorized: Invalid user in token",
                401
            );
            return;
        }

        $file = $_FILES['icon'] ?? null;

        $errors = $this->imageValidator->validate($file, 'icon');

        if (!empty($errors)) {
            Response::validationError($errors);

            return;
        }

        try {
            $subreddit =
                $this->subredditService->updateIcon($id, $userId, $userRole, $file);

            Response::success([
                'message' => 'Subreddit icon updated successfully',
                'subreddit' => $subreddit
            ], 200);
        } catch (\DomainException $e) {
            Response::error(
                $e->getMessage(),
                404
            );
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 403);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    public function deleteIcon(Request $request, int $id): void
    {
        $userData = $request->user ?? null;

        $userId = (int)($userData->userId ?? $userData->id ?? 0);

        $userRole = (int)(
            $userData->role ?? 1
        );

        if ($userId === 0) {
            Response::error(
                "Unauthorized: Invalid user in token",
                401
            );
            return;
        }

        try {
            $subreddit = $this->subredditService->deleteIcon($id, $userId, $userRole);

            Response::success([
                'message' => 'Subreddit icon deleted successfully',
                'subreddit' => $subreddit
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
}
