<?php

namespace App\Services;

use App\DTOs\Input\CreateCommentDTO;
use App\DTOs\Input\UpdateCommentDTO;
use App\DTOs\Output\CommentResponseDTO;
use App\DTOs\Output\CommentAuthorResponseDTO;
use App\Models\Comment;
use App\Repositories\CommentRepository;
use App\Repositories\PostRepository;
use App\Repositories\UserRepository;
use App\Repositories\SubredditRepository;
use App\Middleware\AuthMiddleware;
use Exception;

class CommentService
{
    private CommentRepository $commentRepository;
    private PostRepository $postRepository;
    private UserRepository $userRepository;
    private SubredditRepository $subredditRepository;

    public function __construct()
    {
        $this->commentRepository = new CommentRepository();
        $this->postRepository = new PostRepository();
        $this->userRepository = new UserRepository();
        $this->subredditRepository = new SubredditRepository();
    }

    public function createComment(CreateCommentDTO $dto): CommentResponseDTO
    {
        $post = $this->postRepository->findById($dto->postId);

        if (!$post) {
            throw new \DomainException("Post not found");
        }

        if ($post->isLocked) {
            throw new \InvalidArgumentException("Discussion on this post is locked");
        }

        $subreddit = $this->subredditRepository->findById(
            $post->subredditId
        );

        if (!$subreddit) {
            throw new \InvalidArgumentException("Subreddit not found");
        }

        if ($subreddit->isLocked) {
            throw new \InvalidArgumentException("Comments are disabled in blocked subreddits");
        }

        $author = $this->userRepository->findById($dto->authorId);

        if (!$author) {
            throw new \InvalidArgumentException("User not found");
        }

        if ($author->isBanned) {
            throw new \InvalidArgumentException("Your account is blocked");
        }

        if ($dto->parentId !== null) {
            $parentComment = $this->commentRepository->findById($dto->parentId);

            if (!$parentComment) {
                throw new \DomainException(
                    "Parent comment not found"
                );
            }

            if ($parentComment->postId !== $dto->postId) {
                throw new \InvalidArgumentException("Parent comment belongs to a different post");
            }
        }

        $comment = new Comment(
            id: null,
            postId: $dto->postId,
            authorId: $dto->authorId,
            parentId: $dto->parentId,
            body: $dto->body
        );

        $savedComment = $this->commentRepository->save($comment);

        $authorDTO = CommentAuthorResponseDTO::fromModel($author);

        return CommentResponseDTO::fromModel($savedComment, $authorDTO);
    }

    public function updateComment(UpdateCommentDTO $dto): CommentResponseDTO
    {
        $comment = $this->commentRepository->findById($dto->id);

        if (!$comment) {
            throw new \DomainException("Comment not found");
        }

        if ($comment->isDeleted) {
            throw new \InvalidArgumentException("Deleted comments cannot be edited");
        }

        $isOwner = ((int)$comment->authorId === (int)$dto->authorId);

        $isAdmin = (
            (int)$dto->userRole === AuthMiddleware::ROLE_ADMIN ||

            (int)$dto->userRole === 2
        );

        if (!$isOwner && !$isAdmin) {
            throw new \RuntimeException(
                "You do not have permission to edit this comment"
            );
        }

        $updatedComment = new Comment(
            id: $comment->id,
            postId: $comment->postId,
            authorId: $comment->authorId,
            parentId: $comment->parentId,
            body: $dto->body,
            createdAt: $comment->createdAt,
            updatedAt: $comment->updatedAt
        );

        $this->commentRepository->update($updatedComment);

        $savedComment = $this->commentRepository->findById($dto->id);

        if (!$savedComment) {
            throw new Exception(
                "Failed to retrieve the updated comment"
            );
        }

        $author = $this->userRepository->findById(
            $savedComment->authorId
        );

        if (!$author) {
            throw new \InvalidArgumentException("User not found");
        }

        $authorDTO = CommentAuthorResponseDTO::fromModel($author);

        return CommentResponseDTO::fromModel(
            $savedComment,
            $authorDTO
        );
    }

    public function deleteComment(int $id, int $userId, int $userRole): void
    {
        $comment = $this->commentRepository->findById($id);

        if (!$comment) {
            throw new \DomainException("Comment not found");
        }

        if ($comment->isDeleted) {
            throw new \DomainException("Comment is already deleted");
        }

        $isOwner = $comment->authorId === $userId;

        $isAdmin = ($userRole === AuthMiddleware::ROLE_ADMIN || $userRole === 2);

        if (!$isOwner && !$isAdmin) {
            throw new \RuntimeException(
                "You do not have permission to delete this comment"
            );
        }

        $this->commentRepository->delete($id);
    }

    public function getCommentsByPost(int $postId): array
    {
        $post = $this->postRepository->findById($postId);

        if (!$post) {
            throw new \DomainException("Post not found");
        }

        $rows = $this->commentRepository->findByPostIdWithAuthors($postId);

        $grouped = [];
        foreach ($rows as $row) {
            $parentId = $row['parent_id'] ?? 0;
            $grouped[$parentId][] = $row;
        }

        return $this->buildCommentTree($grouped, 0);
    }

    private function buildCommentTree(array $grouped, int $parentId = 0): array
    {
        if (!isset($grouped[$parentId])) {
            return [];
        }

        $comments = [];

        foreach ($grouped[$parentId] as $row) {
            $comment = Comment::fromArray($row);

            $author = ($comment->isDeleted || $comment->authorId === null) ? null : CommentAuthorResponseDTO::fromArray($row);

            $replies = $this->buildCommentTree($grouped, $comment->id);

            $comments[] = CommentResponseDTO::fromModel(
                $comment,
                $author,
                $replies
            );
        }

        return $comments;
    }

    public function getCommentsByUser(int $userId, int $page = 1, int $limit = 20): array
    {
        $user = $this->userRepository->findById($userId);

        if (!$user) {
            throw new \DomainException(
                "User not found"
            );
        }

        $offset = ($page - 1) * $limit;

        $rows = $this->commentRepository->findByUserId($userId, $limit, $offset);

        $total = $this->commentRepository->countByUserId($userId);

        $comments = array_map(
            function (array $row) {
                $comment = Comment::fromArray($row);

                $author = CommentAuthorResponseDTO::fromArray($row);

                return CommentResponseDTO::fromModel($comment, $author);
            },
            $rows
        );

        return [
            'data' => $comments,
            'count' => count($comments),
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ];
    }
}
