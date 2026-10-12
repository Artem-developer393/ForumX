<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\DTOs\Input\RegisterDTO;
use App\DTOs\Input\LoginDTO;
use App\DTOs\Input\VerifyEmailDTO;
use App\DTOs\Input\ResendVerificationCodeDTO;
use App\DTOs\Input\ForgotPasswordDTO;
use App\DTOs\Input\ResetPasswordDTO;
use App\DTOs\Input\ChangePasswordDTO;
use App\Validators\VerifyEmailValidator;
use App\Validators\ResendVerificationCodeValidator;
use App\Validators\RegisterValidator;
use App\Validators\LoginValidator;
use App\Validators\ForgotPasswordValidator;
use App\Validators\ResetPasswordValidator;
use App\Validators\ChangePasswordValidator;
use App\Services\AuthService;
use App\Services\UserService;


class AuthController
{
    private AuthService $authService;
    private UserService $userService;
    private RegisterValidator $registerValidator;
    private LoginValidator $loginValidator;
    private VerifyEmailValidator $verifyEmailValidator;
    private ResendVerificationCodeValidator $resendVerificationCodeValidator;
    private ForgotPasswordValidator $forgotPasswordValidator;
    private ResetPasswordValidator $resetPasswordValidator;
    private ChangePasswordValidator $changePasswordValidator;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->userService = new UserService();
        $this->registerValidator = new RegisterValidator();
        $this->loginValidator = new LoginValidator();
        $this->verifyEmailValidator = new VerifyEmailValidator();
        $this->resendVerificationCodeValidator = new ResendVerificationCodeValidator();
        $this->forgotPasswordValidator = new ForgotPasswordValidator();
        $this->resetPasswordValidator = new ResetPasswordValidator();
        $this->changePasswordValidator = new ChangePasswordValidator();
    }

    public function register(Request $request): void
    {
        $requestData = $request->body ?? [];
        $dto = RegisterDTO::fromArray($requestData);

        $errors = $this->registerValidator->validate($dto);
        if (!empty($errors)) {
            Response::validationError($errors);
            return;
        }

        try {
            $user = $this->authService->register($dto);

            Response::success([
                'message' => 'User registered successfully',
                'user' => $user
            ], 201);
        } catch (\Exception $e) {
            if (!$e instanceof \InvalidArgumentException) {
                Response::error($e->getMessage(), 500);
                return;
            }
            Response::error($e->getMessage(), 400);
        }
    }

    public function login(Request $request): void
    {
        $requestData = $request->body ?? [];
        $dto = LoginDTO::fromArray($requestData);

        $errors = $this->loginValidator->validate($dto);

        if (!empty($errors)) {
            Response::validationError($errors);
            return;
        }

        try {
            $result = $this->authService->login($dto);

            Response::success([
                'message' => 'Login successful',
                'token' => $result['token'],
                'refresh_token' => $result['refresh_token'],
                'user' => $result['user']
            ], 200);
        } catch (\Exception $e) {
            if (!$e instanceof \InvalidArgumentException) {
                Response::error($e->getMessage(), 500);
                return;
            }
            if ($e->getMessage() === 'EMAIL_NOT_VERIFIED') {
                Response::error(
                    'Email is not verified',
                    403,
                    'EMAIL_NOT_VERIFIED'
                );
                return;
            }

            Response::error($e->getMessage(), 401);
        }
    }

    public function refresh(Request $request): void
    {
        $refreshToken = $request->body['refresh_token'] ?? null;
        if (!is_string($refreshToken) || !preg_match('/^[a-f0-9]{64}$/D', $refreshToken)) {
            Response::validationError(['refresh_token' => 'Invalid refresh token format']);
            return;
        }
        try {
            $result = $this->authService->refresh($refreshToken);
            Response::success([
                'message' => 'Token refreshed successfully',
                'token' => $result['token'],
                'refresh_token' => $result['refresh_token']
            ], 200);
        } catch (\InvalidArgumentException $e) {
            Response::error('Invalid or expired refresh token', 401);
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    public function logout(Request $request): void
    {
        $userId = (int)($request->user->userId ?? $request->user->id ?? 0);
        $refreshToken = $request->body['refresh_token'] ?? null;
        if ($userId <= 0) {
            Response::error('User is not authenticated', 401);
            return;
        }
        if (!is_string($refreshToken) || !preg_match('/^[a-f0-9]{64}$/D', $refreshToken)) {
            Response::validationError(['refresh_token' => 'Invalid refresh token format']);
            return;
        }
        try {
            $this->authService->logout($userId, (string)$request->sessionFamilyId, $refreshToken);
            Response::success(['message' => 'Logout successful'], 200);
        } catch (\InvalidArgumentException $e) {
            Response::error('Invalid session refresh token', 401);
        } catch (\Throwable $e) {
            Response::error('Logout failed', 500);
        }
    }

    public function logoutAll(Request $request): void
    {
        $userId = (int)($request->user->userId ?? $request->user->id ?? 0);
        if ($userId <= 0) {
            Response::error('User is not authenticated', 401);
            return;
        }
        try {
            $this->authService->logoutAll($userId);
            Response::success(['message' => 'All sessions logged out'], 200);
        } catch (\Throwable $e) {
            Response::error('Logout failed', 500);
        }
    }

    public function me(Request $request): void
    {
        try {
            $userData = $request->user ?? null;
            $userId = (int)($userData->userId ?? $userData->id ?? 0);

            if ($userId === 0) {
                Response::error("User is not authenticated", 401);
                return;
            }
            $data = $this->userService->getCurrentUser($userId, true);
            Response::success(
                [
                    'user' => $data
                ],
                200
            );
        } catch (\Exception $e) {
            if (!$e instanceof \InvalidArgumentException) {
                Response::error($e->getMessage(), 500);
                return;
            }
            Response::error($e->getMessage(), 400);
        }
    }

    public function verifyEmail(Request $request): void
    {
        $requestData = $request->body ?? [];
        $dto = VerifyEmailDTO::fromArray($requestData);

        $errors = $this->verifyEmailValidator->validate($dto);

        if (!empty($errors)) {
            Response::validationError($errors);
            return;
        }

        try {
            $this->authService->verifyEmail($dto);

            Response::success([
                'message' => 'Email verified successfully'
            ], 200);
        } catch (\Exception $e) {
            if (!$e instanceof \InvalidArgumentException) {
                Response::error($e->getMessage(), 500);
                return;
            }
            Response::error($e->getMessage(), 400);
        }
    }

    public function resendVerificationCode(Request $request): void
    {
        $requestData = $request->body ?? [];
        $dto = ResendVerificationCodeDTO::fromArray($requestData);

        $errors = $this->resendVerificationCodeValidator->validate($dto);

        if (!empty($errors)) {
            Response::validationError($errors);
            return;
        }

        try {
            $this->authService->resendVerificationCode($dto);

            Response::success([
                'message' => 'If the account exists and the email is not verified, a new verification code has been sent'
            ], 200);
        } catch (\Exception $e) {
            if (!$e instanceof \InvalidArgumentException) {
                Response::error($e->getMessage(), 500);
                return;
            }
            Response::error($e->getMessage(), 400);
        }
    }

    public function forgotPassword(Request $request): void
    {
        $requestData = $request->body ?? [];

        $dto = ForgotPasswordDTO::fromArray(
            $requestData
        );

        $errors = $this->forgotPasswordValidator->validate($dto);

        if (!empty($errors)) {
            Response::validationError($errors);
            return;
        }

        try {
            $this->authService->forgotPassword($dto);

            Response::success([
                'message' =>
                'If an account with this email exists, a password reset code has been sent'
            ], 200);
        } catch (\Exception $e) {
            Response::error(
                'Unable to process password reset request',
                500
            );
        }
    }

    public function resetPassword(Request $request): void
    {
        $requestData = $request->body ?? [];

        $dto = ResetPasswordDTO::fromArray(
            $requestData
        );

        $errors = $this->resetPasswordValidator
            ->validate($dto);

        if (!empty($errors)) {
            Response::validationError($errors);

            return;
        }

        try {
            $this->authService->resetPassword($dto);

            Response::success([
                'message' => 'Password changed successfully'
            ], 200);
        } catch (\Exception $e) {
            if (!$e instanceof \InvalidArgumentException) {
                Response::error($e->getMessage(), 500);
                return;
            }
            Response::error(
                $e->getMessage(),
                400
            );
        }
    }

    public function changePassword(Request $request): void
    {
        $requestData = $request->body ?? [];
        $userData = $request->user ?? null;
        $userId = (int)($userData->userId ?? $userData->id ?? 0);

        if ($userId === 0) {
            Response::error("Unauthorized: Invalid user in token", 401);
            return;
        }

        $dto = ChangePasswordDTO::fromArray(
            $requestData
        );

        $errors = $this->changePasswordValidator
            ->validate($dto);

        if (!empty($errors)) {
            Response::validationError($errors);

            return;
        }

        if ($userId === 0) {
            Response::error(
                "User is not authenticated",
                401
            );
            return;
        }

        try {
            $this->authService->changePassword(
                $userId,
                $dto
            );

            Response::success([
                'message' => 'Password changed successfully'
            ], 200);
        } catch (\Exception $e) {
            if (!$e instanceof \InvalidArgumentException) {
                Response::error($e->getMessage(), 500);
                return;
            }
            Response::error(
                $e->getMessage(),
                400
            );
        }
    }
}
