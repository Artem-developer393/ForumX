<?php

namespace App\Services;

use App\Repositories\UserRepository;
use App\Repositories\PostRepository;
use App\Repositories\CommentRepository;
use App\Repositories\ReportRepository;
use App\DTOs\Output\UserResponseDTO;
use App\DTOs\Output\PublicUserResponseDTO;
use App\DTOs\Output\PostResponseDTO;
use App\DTOs\Output\CommentResponseDTO;
use App\DTOs\Output\CommentAuthorResponseDTO;
use App\DTOs\Input\UpdateUserDTO;
use App\Services\MailService;
use Exception;

class UserService
{
    private UserRepository $userRepository;
    private MailService $mailService;
    private PostRepository $postRepository;
    private ReportRepository $reportRepository;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
        $this->mailService = new MailService();
        $this->postRepository = new PostRepository();
        $this->reportRepository = new ReportRepository();
    }

    public function getCurrentUser(int $userId, bool $needsEmail): PublicUserResponseDTO|UserResponseDTO
    {
        $user = $this->userRepository->findById(
            $userId
        );

        if (!$user) {
            throw new Exception(
                "User not found"
            );
        }

        if ($user->isBanned) {
            throw new Exception(
                "Your account is blocked"
            );
        }

        if ($needsEmail) {
            $profileData = $this->userRepository->getPublicProfileData($userId);

            if (!$profileData) {
                throw new Exception("User not found");
            }

            return UserResponseDTO::fromModelWithProfile($user, $profileData);
        }

        $profileData = $this->userRepository->getPublicProfileData($userId);

        if (!$profileData) {
            throw new Exception(
                "User not found"
            );
        }

        return PublicUserResponseDTO::fromArray(
            $profileData
        );
    }

    private function buildPrivateProfile(\App\Models\UserModel $user): UserResponseDTO
    {
        if ($user->id === null) {
            throw new \RuntimeException('Cannot build profile for an unsaved user');
        }

        $profile = $this->userRepository->getPublicProfileData($user->id);
        if ($profile === null) {
            throw new \RuntimeException('User profile data not found');
        }

        return UserResponseDTO::fromModelWithProfile($user, $profile);
    }

    public function updateUser(int $userId, UpdateUserDTO $dto): UserResponseDTO
    {

        $user = $this->userRepository->findById($userId);

        if (!$user) {
            throw new Exception("User not found");
        }

        if ($user->isBanned) {
            throw new Exception("Your account is blocked");
        }

        $emailChanged = $user->email !== $dto->email;

        $existingUser = $this->userRepository->findByEmailOrLoginExceptUser(
            $dto->email,
            $dto->login,
            $userId
        );

        if ($existingUser) {
            if ($existingUser->email === $dto->email) {
                throw new Exception(
                    "A user with this email already exists"
                );
            }

            if ($existingUser->login === $dto->login) {
                throw new Exception(
                    "A user with this username already exists"
                );
            }
        }

        $updatedUser = $this->userRepository->update(
            $userId,
            $dto->login,
            $dto->email
        );

        if (!$updatedUser) {
            throw new Exception(
                "Failed to update user information"
            );
        }

        if ($emailChanged) {
            $reset = $this->userRepository->resetEmailVerification(
                $userId
            );

            if (!$reset) {
                throw new Exception(
                    "Failed to reset email verification"
                );
            }

            $this->sendVerificationCode(
                $userId,
                $dto->email
            );

            $updatedUser = $this->userRepository->findById($userId);

            if (!$updatedUser) {
                throw new Exception(
                    "Failed to retrieve updated user information"
                );
            }
        }

        return $this->buildPrivateProfile($updatedUser);
    }

    public function updateAvatar(int $userId, array $file): UserResponseDTO
    {
        $user = $this->userRepository->findById($userId);

        if (!$user) {
            throw new \DomainException("User not found");
        }

        if ($user->isBanned) {
            throw new Exception("Your account is blocked");
        }

        if (
            !isset($file['error'], $file['tmp_name'], $file['size']) ||
            $file['error'] !== UPLOAD_ERR_OK ||
            !is_string($file['tmp_name']) ||
            !is_uploaded_file($file['tmp_name'])
        ) {
            throw new \InvalidArgumentException("Invalid uploaded file");
        }

        if ($file['size'] <= 0 || $file['size'] > 5 * 1024 * 1024) {
            throw new \InvalidArgumentException("Image must be 5 MB or smaller");
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];

        if (!isset($extensions[$mimeType])) {
            throw new Exception("Unsupported image format");
        }

        $extension = $extensions[$mimeType];

        $fileName = $userId . '_' . bin2hex(random_bytes(16)) . '.' . $extension;

        $uploadDirectory = dirname(__DIR__, 2) . '/uploads/avatars/';

        if (!is_dir($uploadDirectory)) {
            throw new Exception("Avatar directory does not exist");
        }

        $destination = $uploadDirectory . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new Exception("Failed to save avatar");
        }

        $oldAvatar = $user->avatar;

        $updatedUser = $this->userRepository->updateAvatar(
            $userId,
            $fileName
        );

        if (!$updatedUser) {
            if (file_exists($destination)) {
                unlink($destination);
            }

            throw new Exception("Failed to update avatar");
        }

        if ($oldAvatar) {
            $oldAvatarPath = $uploadDirectory . basename($oldAvatar);

            if (is_file($oldAvatarPath)) {
                unlink($oldAvatarPath);
            }
        }

        return $this->buildPrivateProfile($updatedUser);
    }

    public function deleteAvatar(int $userId): UserResponseDTO
    {
        $user = $this->userRepository->findById($userId);

        if (!$user) {
            throw new \DomainException("User not found");
        }

        if ($user->isBanned) {
            throw new Exception("Your account is blocked");
        }

        if (!$user->avatar) {
            throw new Exception("User has no avatar");
        }

        $oldAvatar = $user->avatar;

        $updatedUser = $this->userRepository->updateAvatar(
            $userId,
            null
        );

        if (!$updatedUser) {
            throw new Exception("Failed to delete avatar");
        }

        $uploadDirectory = dirname(__DIR__, 2) . '/uploads/avatars/';
        $avatarPath = $uploadDirectory . basename($oldAvatar);

        if (is_file($avatarPath)) {
            unlink($avatarPath);
        }

        return $this->buildPrivateProfile($updatedUser);
    }

    private function sendVerificationCode(int $userId, string $email): void
    {
        $code = (string)random_int(100000, 999999);

        $codeHash = password_hash($code, PASSWORD_DEFAULT);

        $expiresAt = date(
            'Y-m-d H:i:s',
            time() + 15 * 60
        );

        $saved = $this->userRepository->saveEmailVerificationCode(
            $userId,
            $codeHash,
            $expiresAt
        );

        if (!$saved) {
            throw new Exception(
                "Failed to create email verification code"
            );
        }

        $this->mailService->sendVerificationCode(
            $email,
            $code
        );
    }

    public function getUserPosts(int $userId, int $page = 1, int $limit = 20): array
    {
        $user = $this->userRepository->findById($userId);

        if (!$user) {
            throw new \DomainException(
                "User not found"
            );
        }

        $offset = ($page - 1) * $limit;

        $posts = $this->postRepository->findByUserId($userId, $limit, $offset);

        $total = $this->postRepository->countByUserId($userId);

        return [
            'data' => array_map(
                fn($post) =>
                PostResponseDTO::fromModel($post),
                $posts
            ),
            'count' => count($posts),
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ];
    }

    public function requestDeletion(int $userId): void
    {
        if ($this->reportRepository->hasPendingAccountDeletionRequest($userId)) {
            throw new \DomainException('Account deletion request already exists');
        }

        if (!$this->reportRepository->createAccountDeletionRequest($userId)) {
            throw new Exception('Failed to create account deletion request');
        }
    }
}
