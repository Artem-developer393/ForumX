<?php

namespace App\Services;

use App\DTOs\Input\CreatePostDTO;
use App\DTOs\Input\UpdatePostDTO;
use App\DTOs\Output\PostResponseDTO;
use App\Models\Post;
use App\Middleware\AuthMiddleware;
use App\Repositories\PostRepository;
use App\Repositories\SubredditRepository;
use App\Repositories\UserRepository;
use App\Repositories\ReportRepository;
use Exception;

class PostService
{
    private PostRepository $postRepository;
    private SubredditRepository $subredditRepository;
    private UserRepository $userRepository;
    private ReportRepository $reportRepository;

    public function __construct()
    {
        $this->postRepository = new PostRepository();
        $this->subredditRepository = new SubredditRepository();
        $this->userRepository = new UserRepository();
        $this->reportRepository = new ReportRepository();
    }

    public function createPost(CreatePostDTO $dto): PostResponseDTO
    {
        $subreddit = $this->subredditRepository->findById($dto->subredditId);
        if (!$subreddit) {
            throw new Exception("Subreddit not found");
        }


        if ($subreddit->isLocked) {
            throw new Exception(
                "Posts cannot be created in blocked subreddits"
            );
        }


        $creator = $this->userRepository->findById($dto->authorId);
        if (!$creator) {
            throw new Exception("User not found");
        }

        if ($creator->isBanned) {
            throw new Exception("Blocked users cannot create posts");
        }
        $post = new Post(
            id: null,
            subredditId: $subreddit->id,
            creatorId: $creator->id,
            title: $dto->title,
            body: $dto->body,
            isLocked: false
        );

        $savedPost = $this->postRepository->save($post);

        return PostResponseDTO::fromModel($savedPost);
    }

    public function updatePost(UpdatePostDTO $dto): PostResponseDTO
    {
        $post = $this->postRepository->findById($dto->id);
        if (!$post) {
            throw new \DomainException("Post not found");
        }

        if ($post->isLocked) {
            throw new \Exception(
                "Locked posts cannot be edited"
            );
        }


        $isOwner = ((int)$post->creatorId === (int)$dto->creatorId);
        $isAdmin = ((int)$dto->userRole === AuthMiddleware::ROLE_ADMIN);

        if (!$isOwner && !$isAdmin) {
            throw new \Exception("You do not have permission to edit this post");
        }


        $updatedPost = new Post(
            id: $dto->id,
            subredditId: $post->subredditId,
            creatorId: $dto->creatorId,
            title: $dto->title,
            body: $dto->body,
            isLocked: $post->isLocked,
            createdAt: $post->createdAt,
            updatedAt: $post->updatedAt
        );

        $this->postRepository->update($updatedPost);

        $savedPost = $this->postRepository->findById($dto->id);

        if (!$savedPost) {
            throw new Exception(
                "Failed to retrieve the updated post"
            );
        }

        return PostResponseDTO::fromModel($savedPost);
    }

    public function deletePost(int $id, int $userId, int $userRole): void
    {
        $post = $this->postRepository->findById($id);

        if (!$post) {
            throw new \DomainException("Post not found");
        }

        $isOwner = ((int)$post->creatorId === (int)$userId);

        $isAdmin = (
            (int)$userRole === AuthMiddleware::ROLE_ADMIN
            || (int)$userRole === 2
        );

        if (!$isOwner && !$isAdmin) {
            throw new \Exception(
                "You do not have permission to delete this post"
            );
        }

        $image = $post->image;

        $reportsDeleted =
            $this->reportRepository->deleteByPostWithComments($id);

        if (!$reportsDeleted) {
            throw new \Exception(
                "Failed to delete related reports"
            );
        }

        $deleted = $this->postRepository->delete($id);

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

    public function toggleLock(int $id, int $userId, int $userRole)
    {
        $post = $this->postRepository->findById($id);
        if (!$post) {
            throw new \DomainException("Post not found");
        }

        $isOwner = ((int)$post->creatorId === (int)$userId);
        $isAdmin = ((int)$userRole === AuthMiddleware::ROLE_ADMIN);

        if (!$isOwner && !$isAdmin) {
            throw new \RuntimeException("You do not have permission to change this post");
        }

        $isLocked = $this->postRepository->isLock($id);

        if ($isLocked) {
            $this->postRepository->unlock($id);
            return [
                'is_lock' => false,
                'message' => 'Post discussion unlocked'
            ];
        } else {
            $this->postRepository->lock($id);
            return [
                'is_lock' => true,
                'message' => 'Post discussion locked'
            ];
        }
    }

    public function getAllPosts(int $page = 1, int $limit = 20, string $sort = 'new'): array
    {
        if (!in_array(
            $sort,
            ['new', 'popular'],
            true
        )) {
            throw new \InvalidArgumentException(
                "Invalid sort type"
            );
        }

        $offset = ($page - 1) * $limit;

        $posts = $this->postRepository->findAll(
            $limit,
            $offset,
            $sort
        );

        $total = $this->postRepository->countAll();

        return [
            'data' => array_map(
                fn($post) =>
                PostResponseDTO::fromModel($post),
                $posts
            ),
            'count' => count($posts),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'sort' => $sort
        ];
    }

    public function getAllSmartPosts(int $userId, int $page = 1, int $limit = 20): array
    {
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new \Exception("User not found");
        }

        $offset = ($page - 1) * $limit;

        $posts = $this->postRepository->findSmartFeed($userId, $limit, $offset);

        return array_map(function (Post $post) {
            return PostResponseDTO::fromModel($post);
        }, $posts);
    }

    public function getPostById(int $id): PostResponseDTO
    {
        $post = $this->postRepository->findById($id);

        if (!$post) {
            throw new \DomainException("Post with ID {$id} not found");
        }

        return PostResponseDTO::fromModel($post);
    }

    public function getPostBySubreddit(int $subredditId, int $page = 1, int $limit = 20): array
    {
        $subreddit = $this->subredditRepository->findById($subredditId);

        if (!$subreddit) {
            throw new Exception('Subreddit not found');
        }

        $offset = ($page - 1) * $limit;

        $posts = $this->postRepository->findBySubreddit($subredditId, $limit, $offset);

        return array_map(function (Post $post) {
            return PostResponseDTO::fromModel($post);
        }, $posts);
    }

    public function updateImage(int $postId, int $userId, int $userRole, array $file): PostResponseDTO
    {
        $post = $this->postRepository->findById($postId);

        if (!$post) {
            throw new \DomainException("Post not found");
        }

        $isOwner = ((int)$post->creatorId === (int)$userId);

        $isAdmin = ((int)$userRole === AuthMiddleware::ROLE_ADMIN);

        if (!$isOwner && !$isAdmin) {
            throw new Exception(
                "You do not have permission to change this post's image"
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
        $mimeType = $finfo->file($file['tmp_name']);

        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];

        if (!isset($extensions[$mimeType])) {
            throw new Exception(
                "Unsupported image format"
            );
        }

        $extension = $extensions[$mimeType];

        $fileName = $postId
            . '_'
            . bin2hex(random_bytes(16))
            . '.'
            . $extension;

        $uploadDirectory =
            dirname(__DIR__, 2)
            . '/uploads/posts/';

        if (!is_dir($uploadDirectory)) {
            throw new Exception(
                "Post image directory does not exist"
            );
        }

        $destination =
            $uploadDirectory . $fileName;

        if (!move_uploaded_file(
            $file['tmp_name'],
            $destination
        )) {
            throw new Exception(
                "Failed to save post image"
            );
        }

        $oldImage = $post->image;

        $updated = $this->postRepository->updateImage($postId, $fileName);

        if (!$updated) {
            if (is_file($destination)) {
                unlink($destination);
            }

            throw new Exception(
                "Failed to update post image"
            );
        }

        if ($oldImage) {
            $oldImagePath =
                $uploadDirectory . basename($oldImage);

            if (is_file($oldImagePath)) {
                unlink($oldImagePath);
            }
        }

        $updatedPost =
            $this->postRepository->findById($postId);

        if (!$updatedPost) {
            throw new Exception(
                "Failed to retrieve the updated post"
            );
        }

        return PostResponseDTO::fromModel(
            $updatedPost
        );
    }

    public function deleteImage(int $postId, int $userId, int $userRole): PostResponseDTO
    {
        $post = $this->postRepository->findById($postId);

        if (!$post) {
            throw new \DomainException("Post not found");
        }

        $isOwner = ((int)$post->creatorId === (int)$userId);

        $isAdmin = ((int)$userRole === AuthMiddleware::ROLE_ADMIN);

        if (!$isOwner && !$isAdmin) {
            throw new Exception(
                "You do not have permission to delete this post's image"
            );
        }

        if (!$post->image) {
            throw new Exception(
                "Post has no image"
            );
        }

        $oldImage = $post->image;

        $updated = $this->postRepository->updateImage($postId, null);

        if (!$updated) {
            throw new Exception(
                "Failed to delete post image"
            );
        }

        $uploadDirectory =
            dirname(__DIR__, 2)
            . '/uploads/posts/';

        $imagePath =
            $uploadDirectory . basename($oldImage);

        if (is_file($imagePath)) {
            unlink($imagePath);
        }

        $updatedPost =
            $this->postRepository->findById($postId);

        if (!$updatedPost) {
            throw new Exception(
                "Failed to retrieve the updated post"
            );
        }

        return PostResponseDTO::fromModel(
            $updatedPost
        );
    }
}
