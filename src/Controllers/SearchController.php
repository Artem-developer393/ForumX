<?php

namespace App\Controllers;

use App\Services\SearchService;
use App\Http\Request;
use App\Http\Response;
use InvalidArgumentException;

class SearchController
{
    private SearchService $searchService;

    public function __construct()
    {
        $this->searchService = new SearchService();
    }

    public function search(Request $request): void
    {
        $query = trim(
            (string)($request->params['q'] ?? '')
        );

        $type = trim(
            (string)($request->params['type'] ?? '')
        );

        $page = (int)(
            $request->params['page'] ?? 1
        );

        $limit = (int)(
            $request->params['limit'] ?? 20
        );

        if ($query === '') {
            Response::error(
                'The q parameter is required',
                422
            );
            return;
        }

        if (!in_array(
            $type,
            ['posts', 'subreddits', 'users'],
            true
        )) {
            Response::error(
                'The type parameter must be posts, subreddits, or users',
                422
            );
            return;
        }

        $page = max(1, $page);
        $limit = max(1, min(100, $limit));

        try {
            $result = $this->searchService->search($query, $type, $page, $limit);
            Response::success($result, 200);
        } catch (InvalidArgumentException $e) {
            Response::error(
                $e->getMessage(),
                422
            );
        } catch (\Throwable $e) {
            Response::error(
                'Search failed: '
                    . $e->getMessage(),
                500
            );
        }
    }
}
