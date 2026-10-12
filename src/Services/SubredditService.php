<?php

namespace App\Services;

use App\DTOs\Input\CreateSubredditDTO;
use App\DTOs\Input\UpdateSubredditDTO;
use App\DTOs\Output\SubredditResponseDTO;
use App\Middleware\AuthMiddleware;
use App\Models\Subreddit;
use App\Repositories\SubredditRepository;
use App\Repositories\UserRepository;
use App\Repositories\PostRepository;
use App\Repositories\ReportRepository;
use Exception;

class SubredditService
{
    private SubredditRepository $subredditRepository;
    private UserRepository $userRepository;
    private PostRepository $postRepository;
    private ReportRepository $reportRepository;

    public function __construct()
    {
        $this->subredditRepository = new SubredditRepository();
        $this->userRepository = new UserRepository();
        $this->postRepository = new PostRepository();
        $this->reportRepository = new ReportRepository();
    }

    public function createSubreddit(CreateSubredditDTO $dto): SubredditResponseDTO
    {
        $creator = $this->userRepository->findById($dto->creatorId);
        if (!$creator) {
            throw new \InvalidArgumentException("User (creator) not found");
        }

        if ($creator->isBanned) {
            throw new \InvalidArgumentException("Blocked users cannot create subreddits");
        }

        $existing = $this->subredditRepository->findByTitle($dto->title);
        if ($existing) {
            throw new \InvalidArgumentException("A subreddit named '{$dto->title}' already exists");
        }

        $subreddit = new Subreddit(
            id: null,
            title: $dto->title,
            description: $dto->description,
            creatorId: $dto->creatorId
        );

        $savedSubreddit =  $this->subredditRepository->save($subreddit);
        return SubredditResponseDTO::fromModel($savedSubreddit);
    }

    public function updateSubreddit(UpdateSubredditDTO $dto): SubredditResponseDTO
    {
        $subreddit = $this->subredditRepository->findById($dto->id);
        if (!$subreddit) {
            throw new \DomainException("Subreddit not found");
        }


        $isOwner = ((int)$subreddit->creatorId === (int)$dto->userId);
        $isAdmin = ((int)$dto->userRole === AuthMiddleware::ROLE_ADMIN);

        if (!$isOwner && !$isAdmin) {
            throw new \RuntimeException("You do not have permission to edit this subreddit");
        }

        if ($subreddit->title !== $dto->title) {
            $existing = $this->subredditRepository->findByTitle($dto->title);
            if ($existing) {
                throw new \InvalidArgumentException("A subreddit named '{$dto->title}' already exists");
            }
        }
        $updatedSubreddit = new Subreddit(
            id: $subreddit->id,
            title: $dto->title,
            description: $dto->description,
            creatorId: $subreddit->creatorId,
            isLocked: $subreddit->isLocked,
            createdAt: $subreddit->createdAt
        );

        $this->subredditRepository->update($updatedSubreddit);

        $savedSubreddit = $this->subredditRepository->findById($dto->id);

        if (!$savedSubreddit) {
            throw new Exception(
                "Failed to retrieve the updated subreddit"
            );
        }

        return SubredditResponseDTO::fromModel($savedSubreddit);
    }


    public function deleteSubreddit(
        int $id,
        int $userId,
        int $userRole
    ): void {
        $subreddit = $this->subredditRepository->findById($id);

        if (!$subreddit) {
            throw new \DomainException(
                "Subreddit not found"
            );
        }

        $isOwner = (
            (int)$subreddit->creatorId === (int)$userId
        );

        $isAdmin = (
            (int)$userRole === AuthMiddleware::ROLE_ADMIN
            || (int)$userRole === 2
        );

        if (!$isOwner && !$isAdmin) {
            throw new \RuntimeException("You do not have permission to delete this subreddit");
        }

        $icon = $subreddit->icon;

        $postImages =
            $this->postRepository->findImagesBySubreddit($id);

        $reportsDeleted =
            $this->reportRepository
            ->deleteBySubredditWithContent($id);

        if (!$reportsDeleted) {
            throw new \Exception(
                "Failed to delete related reports"
            );
        }

        $deleted =
            $this->subredditRepository->delete($id);

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

    public function getAllSubreddits(): array
    {
        $subreddits = $this->subredditRepository->findAll();

        return array_map(function (Subreddit $subreddit) {
            return SubredditResponseDTO::fromModel($subreddit);
        }, $subreddits);
    }

    public function getSubredditById(int $id): SubredditResponseDTO
    {
        $subreddit = $this->subredditRepository->findById($id);

        if (!$subreddit) {
            throw new \DomainException("Subreddit with ID {$id} not found");
        }

        return SubredditResponseDTO::fromModel($subreddit);
    }

    public function updateIcon(int $subredditId, int $userId, int $userRole, array $file): SubredditResponseDTO
    {
        $subreddit = $this->subredditRepository->findById(
            $subredditId
        );

        if (!$subreddit) {
            throw new \DomainException(
                "Subreddit not found"
            );
        }

        $isOwner = (
            (int)$subreddit->creatorId === (int)$userId
        );

        $isAdmin = (
            (int)$userRole === AuthMiddleware::ROLE_ADMIN ||
            (int)$userRole === 2
        );

        if (!$isOwner && !$isAdmin) {
            throw new \RuntimeException(
                "You do not have permission to change this subreddit's icon"
            );
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
        $mimeType = $finfo->file(
            $file['tmp_name']
        );

        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];

        if (!isset($extensions[$mimeType])) {
            throw new \InvalidArgumentException("Unsupported image format");
        }

        $extension = $extensions[$mimeType];

        $fileName = $subredditId
            . '_'
            . bin2hex(random_bytes(16))
            . '.'
            . $extension;

        $uploadDirectory =
            dirname(__DIR__, 2)
            . '/uploads/subreddits/';

        if (!is_dir($uploadDirectory)) {
            throw new Exception(
                "Subreddit icon directory does not exist"
            );
        }

        $destination =
            $uploadDirectory . $fileName;

        if (!move_uploaded_file(
            $file['tmp_name'],
            $destination
        )) {
            throw new Exception(
                "Failed to save subreddit icon"
            );
        }

        $oldIcon = $subreddit->icon;

        $updated = $this->subredditRepository->updateIcon(
            $subredditId,
            $fileName
        );

        if (!$updated) {
            if (is_file($destination)) {
                unlink($destination);
            }

            throw new Exception(
                "Failed to update subreddit icon"
            );
        }

        if ($oldIcon) {
            $oldIconPath = $uploadDirectory . basename($oldIcon);

            if (is_file($oldIconPath)) {
                unlink($oldIconPath);
            }
        }

        $updatedSubreddit =
            $this->subredditRepository->findById(
                $subredditId
            );

        if (!$updatedSubreddit) {
            throw new Exception(
                "Failed to retrieve the updated subreddit"
            );
        }

        return SubredditResponseDTO::fromModel(
            $updatedSubreddit
        );
    }

    public function deleteIcon(int $subredditId, int $userId, int $userRole): SubredditResponseDTO
    {
        $subreddit = $this->subredditRepository->findById(
            $subredditId
        );

        if (!$subreddit) {
            throw new \DomainException(
                "Subreddit not found"
            );
        }

        $isOwner = ((int)$subreddit->creatorId === (int)$userId);

        $isAdmin = ((int)$userRole === AuthMiddleware::ROLE_ADMIN);

        if (!$isOwner && !$isAdmin) {
            throw new \RuntimeException(
                "You do not have permission to delete this subreddit's icon"
            );
        }

        if (!$subreddit->icon) {
            throw new Exception(
                "Subreddit has no icon"
            );
        }

        $oldIcon = $subreddit->icon;

        $updated = $this->subredditRepository->updateIcon(
            $subredditId,
            null
        );

        if (!$updated) {
            throw new Exception(
                "Failed to delete subreddit icon"
            );
        }

        $uploadDirectory =
            dirname(__DIR__, 2)
            . '/uploads/subreddits/';

        $iconPath =
            $uploadDirectory . basename($oldIcon);

        if (is_file($iconPath)) {
            unlink($iconPath);
        }

        $updatedSubreddit =
            $this->subredditRepository->findById(
                $subredditId
            );

        if (!$updatedSubreddit) {
            throw new Exception(
                "Failed to retrieve the updated subreddit"
            );
        }

        return SubredditResponseDTO::fromModel(
            $updatedSubreddit
        );
    }
}
