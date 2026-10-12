<?php

namespace App\Services;

use App\DTOs\Input\RegisterDTO;
use App\DTOs\Output\UserResponseDTO;
use App\DTOs\Input\VerifyEmailDTO;
use App\DTOs\Input\ResendVerificationCodeDTO;
use App\DTOs\Input\LoginDTO;
use App\DTOs\Input\ForgotPasswordDTO;
use App\DTOs\Input\ResetPasswordDTO;
use App\DTOs\Input\ChangePasswordDTO;
use App\Models\UserModel;
use App\Repositories\UserRepository;
use App\Services\JWTService;
use App\Services\MailService;

use Exception;

class AuthService
{
    private UserRepository $userRepository;
    private JWTService $jwtService;
    private MailService $mailService;
    private RefreshTokenService $refreshTokenService;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
        $this->jwtService = new JWTService();
        $this->mailService = new MailService();
        $this->refreshTokenService = new RefreshTokenService();
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

    public function register(RegisterDTO $dto): UserResponseDTO
    {
        $existingUser = $this->userRepository->findByEmailOrLogin($dto->email, $dto->login);
        if ($existingUser) {
            throw new \InvalidArgumentException("A user with this email or login already exists");
        }

        $hashedPassword = password_hash($dto->password, PASSWORD_BCRYPT);

        $newUserModel = new UserModel(
            id: null,
            login: $dto->login,
            email: $dto->email,
            password: $hashedPassword
        );

        $savedUser = $this->userRepository->save($newUserModel);

        $this->sendVerificationCode($savedUser);

        return $this->buildPrivateProfile($savedUser);;
    }

    public function login(LoginDTO $dto): array
    {

        $existingUser = $this->userRepository->findByEmailOrLogin($dto->loginOrEmail);

        if (!$existingUser || !password_verify($dto->password, $existingUser->password)) {
            throw new \InvalidArgumentException("Invalid login or password");
        }

        if ($existingUser->isBanned) {
            throw new \InvalidArgumentException("Your account has been banned");
        }

        if ($existingUser->emailVerifiedAt === null) {
            throw new \InvalidArgumentException("EMAIL_NOT_VERIFIED");
        }

        $profile = $this->buildPrivateProfile($existingUser);
        $session = $this->refreshTokenService->generateToken((int)$existingUser->id);
        $token = $this->jwtService->generateToken($existingUser, $session['family_id']);
        $refreshToken = $session['refresh_token'];

        return [
            'token' => $token,
            'refresh_token' => $refreshToken,
            'user' => $profile
        ];
    }
    public function refresh(string $refreshToken): array
    {
        $session = $this->refreshTokenService->refresh($refreshToken);
        if ($session === null) {
            throw new \InvalidArgumentException('Invalid or expired refresh token');
        }
        $user = $this->userRepository->findById($session['user_id']);
        if (!$user || $user->isBanned || $user->emailVerifiedAt === null) {
            throw new \InvalidArgumentException('Invalid or expired refresh token');
        }
        return [
            'token' => $this->jwtService->generateToken($user, $session['family_id']),
            'refresh_token' => $session['refresh_token']
        ];
    }

    public function logout(int $userId, string $familyId, string $refreshToken): void
    {
        $this->refreshTokenService->logout($userId, $familyId, $refreshToken);
    }

    public function logoutAll(int $userId): void
    {
        $this->refreshTokenService->logoutAll($userId);
    }

    private function sendVerificationCode(UserModel $user): void
    {
        if ($user->id === null) {
            throw new \InvalidArgumentException("Unable to identify user");
        }

        $code = (string)random_int(100000, 999999);

        $codeHash = password_hash($code, PASSWORD_DEFAULT);

        $expiresAt = date(
            'Y-m-d H:i:s',
            time() + 15 * 60
        );

        $this->userRepository->saveEmailVerificationCode(
            $user->id,
            $codeHash,
            $expiresAt
        );

        $this->mailService->sendVerificationCode(
            $user->email,
            $code
        );
    }

    public function verifyEmail(VerifyEmailDTO $dto): void
    {
        $verificationData = $this->userRepository
            ->getEmailVerificationData($dto->email);

        if (!$verificationData) {
            throw new \InvalidArgumentException("Invalid verification code");
        }

        if ($verificationData['email_verified_at'] !== null) {
            throw new \InvalidArgumentException("Email is already verified");
        }

        $codeHash = $verificationData['email_verification_code_hash'];
        $expiresAt = $verificationData['email_verification_expires_at'];

        if ($codeHash === null || $expiresAt === null) {
            throw new \InvalidArgumentException("Verification code is missing or expired");
        }

        if (strtotime($expiresAt) < time()) {
            throw new \InvalidArgumentException("Verification code has expired");
        }

        if (!password_verify($dto->code, $codeHash)) {
            throw new \InvalidArgumentException("Invalid verification code");
        }

        $this->userRepository->verifyEmail(
            (int)$verificationData['id']
        );
    }

    public function resendVerificationCode(ResendVerificationCodeDTO $dto): void
    {
        $user = $this->userRepository->findByEmailOrLogin($dto->email);

        if (!$user) {
            return;
        }

        if ($user->emailVerifiedAt !== null) {
            return;
        }

        $this->sendVerificationCode($user);
    }

    public function forgotPassword(ForgotPasswordDTO $dto): void
    {
        $user = $this->userRepository
            ->findByEmailOrLogin($dto->email);

        if (!$user || $user->email !== $dto->email) {
            return;
        }

        $code = (string)random_int(100000, 999999);

        $codeHash = password_hash(
            $code,
            PASSWORD_DEFAULT
        );

        $expiresAt = date(
            'Y-m-d H:i:s',
            time() + 15 * 60
        );

        $saved = $this->userRepository
            ->savePasswordResetCode(
                $user->id,
                $codeHash,
                $expiresAt
            );

        if (!$saved) {
            throw new Exception(
                "Unable to create password reset code"
            );
        }

        $this->mailService->sendPasswordResetCode(
            $user->email,
            $code
        );
    }


    public function resetPassword(ResetPasswordDTO $dto): void
    {
        $resetData = $this->userRepository->getPasswordResetData($dto->email);

        if (!$resetData) {
            throw new \InvalidArgumentException(
                "Invalid or expired reset code"
            );
        }

        $codeHash = $resetData['password_reset_code_hash'];

        $expiresAt = $resetData['password_reset_expires_at'];

        if ($codeHash === null || $expiresAt === null) {
            throw new \InvalidArgumentException(
                "Invalid or expired reset code"
            );
        }

        if (strtotime($expiresAt) < time()) {
            throw new \InvalidArgumentException(
                "Invalid or expired reset code"
            );
        }

        if (!password_verify(
            $dto->code,
            $codeHash
        )) {
            throw new \InvalidArgumentException(
                "Invalid or expired reset code"
            );
        }

        $passwordHash = password_hash(
            $dto->newPassword,
            PASSWORD_BCRYPT
        );

        $updated = $this->userRepository->resetPassword(
            (int)$resetData['id'],
            $passwordHash
        );

        if (!$updated) {
            throw new Exception(
                "Unable to change password"
            );
        }
        $this->refreshTokenService->logoutAll((int)$resetData['id']);
    }

    public function changePassword(int $userId, ChangePasswordDTO $dto): void
    {
        $user = $this->userRepository->findById($userId);

        if (!$user) {
            throw new \InvalidArgumentException(
                "User not found"
            );
        }

        if ($user->isBanned) {
            throw new \InvalidArgumentException(
                "Your account has been banned"
            );
        }

        if (!password_verify(
            $dto->currentPassword,
            $user->password
        )) {
            throw new \InvalidArgumentException(
                "Invalid current password"
            );
        }

        $passwordHash = password_hash(
            $dto->newPassword,
            PASSWORD_BCRYPT
        );

        $updated = $this->userRepository->updatePassword(
            $userId,
            $passwordHash
        );

        if (!$updated) {
            throw new Exception(
                "Unable to change password"
            );
        }
        $this->refreshTokenService->logoutAll($userId);
    }
}
