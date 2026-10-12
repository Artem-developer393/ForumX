<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\UserService;
use App\DTOs\Input\UpdateUserDTO;
use App\Validators\UpdateUserValidator;
use App\Validators\AvatarValidator;

class UserController
{
    private UserService $userService;
    private UpdateUserValidator $updateUserValidator;
    private AvatarValidator $avatarValidator;

    public function __construct()
    {
        $this->userService = new UserService();
        $this->updateUserValidator = new UpdateUserValidator();
        $this->avatarValidator = new AvatarValidator();
    }

    public function show(Request $request, int $userId): void
    {
        try {
            $data = $this->userService->getCurrentUser($userId, false);
            Response::success(
                [
                    'user' => $data
                ],
                200
            );
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function update(Request $request): void
    {
        $userData = $request->user ?? null;

        $userId = (int)($userData->userId ?? $userData->id ?? 0);

        if ($userId === 0) {
            Response::error("Unauthorized: Invalid user in token", 401);
            return;
        }

        $requestData = $request->body ?? [];

        $dto = UpdateUserDTO::fromArray($requestData);

        $errors = $this->updateUserValidator->validate($dto);

        if (!empty($errors)) {
            Response::validationError($errors);
            return;
        }

        try {
            $user = $this->userService->updateUser($userId, $dto);

            Response::success([
                'message' => 'User information updated successfully',
                'user' => $user
            ], 200);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function updateAvatar(Request $request): void
    {
        $userData = $request->user ?? null;
        $userId = (int)($userData->userId ?? $userData->id ?? 0);

        if ($userId === 0) {
            Response::error("Unauthorized: Invalid user in token", 401);
            return;
        }

        $file = $_FILES['avatar'] ?? null;

        $errors = $this->avatarValidator->validate($file);

        if (!empty($errors)) {
            Response::validationError($errors);
            return;
        }

        try {
            $user = $this->userService->updateAvatar(
                $userId,
                $file
            );

            Response::success([
                'message' => 'Avatar updated successfully',
                'user' => $user
            ], 200);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function deleteAvatar(Request $request): void
    {
        $userData = $request->user ?? null;
        $userId = (int)($userData->userId ?? $userData->id ?? 0);

        if ($userId === 0) {
            Response::error("Unauthorized: Invalid user in token", 401);
            return;
        }

        try {
            $user = $this->userService->deleteAvatar($userId);

            Response::success([
                'message' => 'Avatar deleted successfully',
                'user' => $user
            ], 200);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function getUserPosts(Request $request, int $userId): void
    {
        $page = max(1, (int)($request->params['page'] ?? 1));

        $limit = max(1, min(100, (int)($request->params['limit'] ?? 20)));

        try {
            $result = $this->userService->getUserPosts($userId, $page, $limit);

            Response::success($result, 200);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    public function requestDeletion(Request $request): void
    {
        try {
            $userData = $request->user ?? null;
            $userId = (int)($userData->userId ?? $userData->id ?? 0);

            if ($userId === 0) {
                Response::error("Unauthorized: Invalid user in token", 401);
                return;
            }

            $this->userService->requestDeletion($userId);

            Response::success([
                'message' => 'Account deletion request created'
            ]);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }
}
