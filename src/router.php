<?php

use App\Controllers\AuthController;
use App\Controllers\UserController;
use App\Controllers\SubredditController;
use App\Controllers\SubscriptionController;
use App\Controllers\PostController;
use App\Controllers\CommentController;
use App\Controllers\AdminController;
use App\Controllers\ReportController;
use App\Controllers\SearchController;


return [

    // --- USERS & PROFILES ---
    // View a user's public profile
    ['method' => 'GET', 'pattern' => '~^/api/users/(\d+)$~', 'controller' => UserController::class, 'action' => 'show',   'auth' => 'guest'],
    // Edit profile
    ['method' => 'PUT', 'pattern' => '~^/api/users$~', 'controller' => UserController::class, 'action' => 'update', 'auth' => 'user'],
    //Update user avatar
    ['method' => 'POST', 'pattern' => '~^/api/users/avatar$~', 'controller' => UserController::class, 'action' => 'updateAvatar', 'auth' => 'user'],
    //Delete user avatar
    ['method' => 'DELETE', 'pattern' => '~^/api/users/avatar$~', 'controller' => UserController::class, 'action' => 'deleteAvatar', 'auth' => 'user'],
    //List posts by user ID
    ['method' => 'GET', 'pattern' => '~^/api/users/(\d+)/posts$~', 'controller' => UserController::class, 'action' => 'getUserPosts', 'auth' => 'guest'],
    //List subscriptions by user ID
    ['method' => 'GET', 'pattern' => '~^/api/users/me/subscriptions$~', 'controller' => SubscriptionController::class, 'action' => 'getMySubscriptions', 'auth' => 'user'],
    //List comments by user ID
    ['method' => 'GET', 'pattern' => '~^/api/users/(\d+)/comments$~', 'controller' => CommentController::class, 'action' => 'getUserComments', 'auth' => 'guest'],
    //Request deletion of own account
    ['method' => 'POST','pattern' => '~^/api/users/deletion-request$~','controller' => UserController::class,'action' => 'requestDeletion','auth' => 'user'],
    // --- SEARCH ---
    ['method' => 'GET', 'pattern' => '~^/api/search$~', 'controller' => SearchController::class, 'action' => 'search', 'auth' => 'guest'],

    // --- AUTH ---
    ['method' => 'POST', 'pattern' => '~^/api/auth/register$~', 'controller' => AuthController::class, 'action' => 'register', 'auth' => 'guest'],
    ['method' => 'POST', 'pattern' => '~^/api/auth/verify-email$~', 'controller' => AuthController::class, 'action' => 'verifyEmail', 'auth' => 'guest'],
    ['method' => 'POST', 'pattern' => '~^/api/auth/verify-email/resend$~', 'controller' => AuthController::class, 'action' => 'resendVerificationCode', 'auth' => 'guest'],
    ['method' => 'POST', 'pattern' => '~^/api/auth/refresh$~', 'controller' => AuthController::class, 'action' => 'refresh', 'auth' => 'guest'],
    ['method' => 'POST', 'pattern' => '~^/api/auth/login$~', 'controller' => AuthController::class, 'action' => 'login', 'auth' => 'guest'],
    ['method' => 'POST', 'pattern' => '~^/api/auth/logout$~', 'controller' => AuthController::class, 'action' => 'logout', 'auth' => 'user'],
    ['method' => 'POST', 'pattern' => '~^/api/auth/logout-all$~', 'controller' => AuthController::class, 'action' => 'logoutAll', 'auth' => 'user'],
    ['method' => 'GET',  'pattern' => '~^/api/auth/me$~',     'controller' => AuthController::class, 'action' => 'me',     'auth' => 'user'],
    ['method' => 'POST', 'pattern' => '~^/api/auth/forgot-password$~', 'controller' => AuthController::class, 'action' => 'forgotPassword', 'auth' => 'guest'],
    ['method' => 'POST', 'pattern' => '~^/api/auth/reset-password$~', 'controller' => AuthController::class, 'action' => 'resetPassword', 'auth' => 'guest'],
    ['method' => 'PATCH', 'pattern' => '~^/api/auth/change-password$~', 'controller' => AuthController::class, 'action' => 'changePassword', 'auth' => 'user'],

    // --- SUBREDDITS ---
    // Create subreddit
    ['method' => 'POST',   'pattern' => '~^/api/subreddits$~', 'controller' => SubredditController::class, 'action' => 'create', 'auth' => 'user'],
    // List all subreddits
    ['method' => 'GET', 'pattern' => '~^/api/subreddits$~', 'controller' => SubredditController::class, 'action' => 'index', 'auth' => 'guest'],
    // Get subreddit details
    ['method' => 'GET',    'pattern' => '~^/api/subreddits/(\d+)$~', 'controller' => SubredditController::class, 'action' => 'show', 'auth' => 'guest'],
    // Edit subreddit (author or admin)
    ['method' => 'PUT',    'pattern' => '~^/api/subreddits/(\d+)$~', 'controller' => SubredditController::class, 'action' => 'update', 'auth' => 'user'],
    // Delete subreddit (author or admin)
    ['method' => 'DELETE', 'pattern' => '~^/api/subreddits/(\d+)$~', 'controller' => SubredditController::class, 'action' => 'delete', 'auth' => 'user'],
    // Toggle subreddit subscription (subscribe/unsubscribe)
    ['method' => 'POST',   'pattern' => '~^/api/subreddits/(\d+)/toggle-sub$~', 'controller' => SubscriptionController::class, 'action' => 'toggleSub', 'auth' => 'user'],
    //Add or update community avatar
    ['method' => 'POST', 'pattern' => '~^/api/subreddits/(\d+)/icon$~', 'controller' => SubredditController::class, 'action' => 'updateIcon', 'auth' => 'user'],
    //Delete community avatar
    ['method' => 'DELETE', 'pattern' => '~^/api/subreddits/(\d+)/icon$~', 'controller' => SubredditController::class, 'action' => 'deleteIcon', 'auth' => 'user'],

    // --- POSTS ---
    // Personalized feed (subscribed posts first, then other posts)
    ['method' => 'GET',    'pattern' => '~^/api/posts/feed$~', 'controller' => PostController::class, 'action' => 'feed', 'auth' => 'user'],
    // General feed (all posts ordered by creation date)
    ['method' => 'GET',    'pattern' => '~^/api/posts$~', 'controller' => PostController::class, 'action' => 'index', 'auth' => 'guest'],
    // Create post
    ['method' => 'POST',   'pattern' => '~^/api/posts$~', 'controller' => PostController::class, 'action' => 'create', 'auth' => 'user'],
    // View a specific post
    ['method' => 'GET',    'pattern' => '~^/api/posts/(\d+)$~', 'controller' => PostController::class, 'action' => 'show', 'auth' => 'guest'],
    // Posts in a specific subreddit
    ['method' => 'GET',    'pattern' => '~^/api/subreddits/(\d+)/posts$~', 'controller' => PostController::class, 'action' => 'bySubreddit', 'auth' => 'guest'],
    // Edit post (author)
    ['method' => 'PUT',    'pattern' => '~^/api/posts/(\d+)$~', 'controller' => PostController::class, 'action' => 'update', 'auth' => 'user'],
    // Delete post (author or global admin)
    ['method' => 'DELETE', 'pattern' => '~^/api/posts/(\d+)$~', 'controller' => PostController::class, 'action' => 'delete', 'auth' => 'user'],
    // Lock/unlock post discussion (is_locked)
    ['method' => 'PATCH',  'pattern' => '~^/api/posts/(\d+)/lock$~', 'controller' => PostController::class, 'action' => 'toggleLock', 'auth' => 'user'],
    //Add or update post image
    ['method' => 'POST', 'pattern' => '~^/api/posts/(\d+)/image$~', 'controller' => PostController::class, 'action' => 'updateImage', 'auth' => 'user'],
    //Delete post image
    ['method' => 'DELETE', 'pattern' => '~^/api/posts/(\d+)/image$~', 'controller' => PostController::class, 'action' => 'deleteImage', 'auth' => 'user'],


    // --- COMMENTS ---
    //Create comment
    ['method' => 'POST', 'pattern' => '~^/api/posts/(\d+)/comments$~', 'controller' => CommentController::class, 'action' => 'create', 'auth' => 'user'],
    //View all comments by post ID
    ['method' => 'GET', 'pattern' => '~^/api/posts/(\d+)/comments$~', 'controller' => CommentController::class, 'action' => 'index', 'auth' => 'guest'],
    //Edit comment
    ['method' => 'PUT', 'pattern' => '~^/api/comments/(\d+)$~', 'controller' => CommentController::class, 'action' => 'update', 'auth' => 'user'],
    //Delete comment
    ['method' => 'DELETE', 'pattern' => '~^/api/comments/(\d+)$~', 'controller' => CommentController::class, 'action' => 'delete', 'auth' => 'user'],

    // --- ADMIN MAINTENANCE ---
    ['method' => 'GET', 'pattern' => '~^/api/admin/refresh-tokens/stats$~', 'controller' => AdminController::class, 'action' => 'refreshTokenStats', 'auth' => 'admin'],
    ['method' => 'POST', 'pattern' => '~^/api/admin/refresh-tokens/cleanup$~', 'controller' => AdminController::class, 'action' => 'cleanupRefreshTokens', 'auth' => 'admin'],

    // --- ADMIN USERS ---
    //View all users
    ['method' => 'GET', 'pattern' => '~^/api/admin/users$~', 'controller' => AdminController::class, 'action' => 'users', 'auth' => 'admin'],
    //View a specific user
    ['method' => 'GET', 'pattern' => '~^/api/admin/users/(\d+)$~', 'controller' => AdminController::class, 'action' => 'user', 'auth' => 'admin'],
    //Ban user
    ['method' => 'PATCH', 'pattern' => '~^/api/admin/users/(\d+)/ban$~', 'controller' => AdminController::class, 'action' => 'banUser', 'auth' => 'admin'],
    //Unban user
    ['method' => 'PATCH', 'pattern' => '~^/api/admin/users/(\d+)/unban$~', 'controller' => AdminController::class, 'action' => 'unbanUser', 'auth' => 'admin'],
    //Change user role
    ['method' => 'PATCH', 'pattern' => '~^/api/admin/users/(\d+)/role$~', 'controller' => AdminController::class, 'action' => 'changeUserRole', 'auth' => 'admin'],
    //Delete user
    ['method' => 'DELETE', 'pattern' => '~^/api/admin/users/(\d+)$~', 'controller' => AdminController::class, 'action' => 'deleteUser', 'auth' => 'admin'],

    // --- ADMIN POSTS ---
    //Retrieve post list
    ['method' => 'GET', 'pattern' => '~^/api/admin/posts$~', 'controller' => AdminController::class, 'action' => 'posts', 'auth' => 'admin'],
    //Retrieve a single post
    ['method' => 'GET', 'pattern' => '~^/api/admin/posts/(\d+)$~', 'controller' => AdminController::class, 'action' => 'post', 'auth' => 'admin'],
    //Close post discussion
    ['method' => 'PATCH', 'pattern' => '~^/api/admin/posts/(\d+)/lock$~', 'controller' => AdminController::class, 'action' => 'lockPost', 'auth' => 'admin'],
    //Open post discussion
    ['method' => 'PATCH', 'pattern' => '~^/api/admin/posts/(\d+)/unlock$~', 'controller' => AdminController::class, 'action' => 'unlockPost', 'auth' => 'admin'],
    //Delete post
    ['method' => 'DELETE', 'pattern' => '~^/api/admin/posts/(\d+)$~', 'controller' => AdminController::class, 'action' => 'deletePost', 'auth' => 'admin'],

    // --- ADMIN COMMENTS ---
    //Retrieve all comments
    ['method' => 'GET', 'pattern' => '~^/api/admin/comments$~', 'controller' => AdminController::class, 'action' => 'comments', 'auth' => 'admin'],
    //Retrieve a single comment
    ['method' => 'GET', 'pattern' => '~^/api/admin/comments/(\d+)$~', 'controller' => AdminController::class, 'action' => 'comment', 'auth' => 'admin'],
    //Delete comment
    ['method' => 'DELETE', 'pattern' => '~^/api/admin/comments/(\d+)$~', 'controller' => AdminController::class, 'action' => 'deleteComment', 'auth' => 'admin'],

    // --- ADMIN SUBREDDITS ---
    //Retrieve all subreddits
    ['method' => 'GET', 'pattern' => '~^/api/admin/subreddits$~', 'controller' => AdminController::class, 'action' => 'subreddits', 'auth' => 'admin'],
    //Retrieve a single subreddit
    ['method' => 'GET', 'pattern' => '~^/api/admin/subreddits/(\d+)$~', 'controller' => AdminController::class, 'action' => 'subreddit', 'auth' => 'admin'],
    //Block subreddit
    ['method' => 'PATCH', 'pattern' => '~^/api/admin/subreddits/(\d+)/lock$~', 'controller' => AdminController::class, 'action' => 'lockSubreddit', 'auth' => 'admin'],
    //Unblock subreddit
    ['method' => 'PATCH', 'pattern' => '~^/api/admin/subreddits/(\d+)/unlock$~', 'controller' => AdminController::class, 'action' => 'unlockSubreddit', 'auth' => 'admin'],
    //Delete subreddit
    ['method' => 'DELETE', 'pattern' => '~^/api/admin/subreddits/(\d+)$~', 'controller' => AdminController::class, 'action' => 'deleteSubreddit', 'auth' => 'admin'],

    // --- REPORTS ---
    //Create report
    ['method' => 'POST', 'pattern' => '~^/api/reports$~', 'controller' => ReportController::class, 'action' => 'create', 'auth' => 'user'],
    //Retrieve report categories
    ['method' => 'GET', 'pattern' => '~^/api/reports/categories$~', 'controller' => ReportController::class, 'action' => 'categories', 'auth' => 'user'],

    // --- ADMIN REPORTS ---
    //Retrieve report list
    ['method' => 'GET', 'pattern' => '~^/api/admin/reports$~', 'controller' => AdminController::class, 'action' => 'reports', 'auth' => 'admin'],
    //Retrieve a single report
    ['method' => 'GET', 'pattern' => '~^/api/admin/reports/(\d+)$~', 'controller' => AdminController::class, 'action' => 'report', 'auth' => 'admin'],
    //Approve report
    ['method' => 'PATCH', 'pattern' => '~^/api/admin/reports/(\d+)/resolve$~', 'controller' => AdminController::class, 'action' => 'resolveReport', 'auth' => 'admin'],
    //Reject report
    ['method' => 'PATCH', 'pattern' => '~^/api/admin/reports/(\d+)/reject$~', 'controller' => AdminController::class, 'action' => 'rejectReport', 'auth' => 'admin'],

    // --- ADMIN DASHBOARD ---
    ['method' => 'GET', 'pattern' => '~^/api/admin/dashboard$~', 'controller' => AdminController::class, 'action' => 'dashboard', 'auth' => 'admin'],

];
