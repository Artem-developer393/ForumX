<?php

namespace App\Controllers;

use App\DTOs\Input\CreateReportDTO;
use App\Http\Request;
use App\Http\Response;
use App\Services\ReportService;
use App\Validators\ReportValidator;

class ReportController
{
    private ReportService $reportService;
    private ReportValidator $reportValidator;

    public function __construct()
    {
        $this->reportService = new ReportService();
        $this->reportValidator = new ReportValidator();
    }

    public function categories(Request $request): void
    {
        try {
            $categories = $this->reportService->getCategories();

            Response::success([
                'categories' => $categories
            ], 200);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    public function create(Request $request): void
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

        $requestData = $request->body ?? [];

        $dto = CreateReportDTO::fromArray(
            $requestData,
            $userId
        );

        $errors = $this->reportValidator->validateCreate($dto);

        if (!empty($errors)) {
            Response::validationError($errors);
            return;
        }

        try {
            $report = $this->reportService->createReport($dto);

            Response::success([
                'message' => 'Report submitted successfully',
                'report' => $report
            ], 201);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }
}
