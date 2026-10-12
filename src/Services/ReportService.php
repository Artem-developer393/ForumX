<?php

namespace App\Services;

use App\DTOs\Input\CreateReportDTO;
use App\DTOs\Output\ReportCategoryResponseDTO;
use App\DTOs\Output\ReportResponseDTO;
use App\Models\Report;
use App\Repositories\CommentRepository;
use App\Repositories\PostRepository;
use App\Repositories\ReportCategoryRepository;
use App\Repositories\ReportRepository;
use App\Repositories\SubredditRepository;
use App\Repositories\UserRepository;

class ReportService
{
    private ReportRepository $reportRepository;
    private ReportCategoryRepository $categoryRepository;
    private UserRepository $userRepository;
    private PostRepository $postRepository;
    private CommentRepository $commentRepository;
    private SubredditRepository $subredditRepository;

    public function __construct()
    {
        $this->reportRepository = new ReportRepository();
        $this->categoryRepository = new ReportCategoryRepository();
        $this->userRepository = new UserRepository();
        $this->postRepository = new PostRepository();
        $this->commentRepository = new CommentRepository();
        $this->subredditRepository = new SubredditRepository();
    }

    public function getCategories(): array
    {
        $categories = $this->categoryRepository->findAllActive();

        return array_map(
            fn($category) =>
            ReportCategoryResponseDTO::fromModel($category),
            $categories
        );
    }

    public function createReport(CreateReportDTO $dto): ReportResponseDTO
    {
        $reporter = $this->userRepository->findById(
            $dto->reporterId
        );

        if (!$reporter) {
            throw new \InvalidArgumentException("User not found");
        }

        if ($reporter->isBanned) {
            throw new \InvalidArgumentException(
                "Blocked users cannot submit reports"
            );
        }

        $category = $this->categoryRepository->findById(
            $dto->categoryId
        );

        if (!$category) {
            throw new \InvalidArgumentException("Report category not found");
        }

        if (!$category->isActive) {
            throw new \InvalidArgumentException(
                "This report category is unavailable"
            );
        }

        if (
            $category->requiresDescription && empty($dto->description)
        ) {
            throw new \InvalidArgumentException(
                "A description is required for this category"
            );
        }

        $this->validateTarget(
            $dto->targetType,
            $dto->targetId
        );

        if (
            $dto->targetType === 'user' && $dto->targetId === $dto->reporterId
        ) {
            throw new \InvalidArgumentException(
                "You cannot report yourself"
            );
        }

        $exists = $this->reportRepository->existsPendingReport(
            $dto->reporterId,
            $dto->targetType,
            $dto->targetId
        );

        if ($exists) {
            throw new \InvalidArgumentException(
                "You have already reported this item"
            );
        }

        $report = new Report(
            id: null,
            reporterId: $dto->reporterId,
            categoryId: $dto->categoryId,
            targetType: $dto->targetType,
            targetId: $dto->targetId,
            description: $dto->description,
            status: 'pending'
        );

        $savedReport = $this->reportRepository->save($report);

        return ReportResponseDTO::fromModel($savedReport);
    }

    private function validateTarget(string $targetType, int $targetId): void
    {
        $exists = match ($targetType) {
            'post' =>
            $this->postRepository->findById($targetId) !== null,

            'comment' =>
            $this->commentExistsForReport($targetId),

            'subreddit' =>
            $this->subredditRepository->findById($targetId) !== null,

            'user' =>
            $this->userRepository->findById($targetId) !== null,

            default => false
        };

        if (!$exists) {
            throw new \DomainException(
                "Reported item not found"
            );
        }
    }
    private function commentExistsForReport(int $id): bool
    {
        $comment = $this->commentRepository->findById($id);

        return $comment !== null && !$comment->isDeleted;
    }
}
