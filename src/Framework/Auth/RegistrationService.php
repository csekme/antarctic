<?php

declare(strict_types=1);

namespace Framework\Auth;

use Application\Dto\RegisterRequest;
use Framework\Models\User;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Creates a new (inactive) user account, fires the activation email, and
 * decides whether to expose the verification link in the response payload
 * (dev-mode flag `APP_EXPOSE_VERIFICATION_LINK=1`).
 *
 * The service is HTTP-mentes: it does not know about Response objects, it
 * returns a value object the controller maps to a 201/409/500 response.
 */
final class RegistrationService
{
    /** @var callable(string $to, string $subject, string $html): bool|string */
    private $mailer;

    public function __construct(
        private readonly LoggerInterface $logger,
        ?callable $mailer = null,
    ) {
        $this->mailer = $mailer ?? static function (string $to, string $subject, string $html): bool|string {
            return \Framework\Mail::sendHtmlMessage($to, $subject, $html);
        };
    }

    /**
     * @param callable(string, string, string): (bool|string) $mailer
     */
    public function setMailer(callable $mailer): void
    {
        $this->mailer = $mailer;
    }

    /**
     * @param array<string, mixed> $auditContext  IP + UA + anything else the
     *                                            caller wants to log alongside.
     */
    public function register(RegisterRequest $body, array $auditContext = []): RegistrationResult
    {
        if (User::findByEmail($body->email) !== false) {
            return new RegistrationResult(RegistrationStatus::EmailTaken);
        }
        if (User::findByUsername($body->username) !== false) {
            return new RegistrationResult(RegistrationStatus::UsernameTaken);
        }

        // The User::save() runs User::validate() internally, which checks both
        // uniqueness (already done above) AND password_confirm equality — we
        // must set password_confirm too, even though the DTO already validated.
        $user = new User();
        $user->email = $body->email;
        $user->username = $body->username;
        $user->password = $body->password;
        $user->password_confirm = $body->password;
        $user->firstname = $body->firstname;
        $user->lastname = $body->lastname;

        try {
            $saved = $user->save();
        } catch (Throwable $e) {
            $this->logger->error('auth.register.save_failed', ['error' => $e->getMessage()]);
            return new RegistrationResult(RegistrationStatus::Failed);
        }
        if (!$saved) {
            $this->logger->error('auth.register.save_failed', ['errors' => $user->errors ?? []]);
            return new RegistrationResult(RegistrationStatus::Failed);
        }

        $userId = $this->resolveUserId($user, $body->email);
        $rawToken = (string) ($user->activation_token ?? '');
        $verificationLink = $this->buildVerificationLink($rawToken);

        if ($verificationLink !== null) {
            $this->sendVerificationMail($body->email, $body->username, $verificationLink);
        }

        $this->logger->info('auth.register', array_merge(['user_id' => $userId], $auditContext));

        $exposeLink = filter_var(getenv('APP_EXPOSE_VERIFICATION_LINK') ?: '0', FILTER_VALIDATE_BOOL);

        return new RegistrationResult(
            RegistrationStatus::Created,
            userId: $userId,
            verificationLink: $exposeLink ? $verificationLink : null,
        );
    }

    private function resolveUserId(User $user, string $email): int
    {
        // Prefer the value set by the PDO insert (lastInsertId is captured
        // by AbstractUser::save() implicitly into $this->id on most drivers).
        // Fall back to a SELECT only if the in-memory id is missing — this
        // is a race-safer path than always re-fetching.
        if (isset($user->id) && (int) $user->id > 0) {
            return (int) $user->id;
        }
        $persisted = User::findByEmail($email);
        return $persisted instanceof User ? (int) $persisted->id : 0;
    }

    private function buildVerificationLink(string $rawToken): ?string
    {
        if ($rawToken === '') {
            return null;
        }
        $base = (string) (getenv('APP_VERIFY_EMAIL_URL') ?: '');
        if ($base === '') {
            return null;
        }
        $separator = str_contains($base, '?') ? '&' : '?';
        return $base . $separator . 'token=' . $rawToken;
    }

    private function sendVerificationMail(string $to, string $username, string $link): void
    {
        try {
            $html = sprintf(
                '<p>Hi %s,</p><p>Click the link below to verify your email:</p><p><a href="%s">%s</a></p>',
                htmlspecialchars($username, ENT_QUOTES | ENT_HTML5),
                htmlspecialchars($link, ENT_QUOTES | ENT_HTML5),
                htmlspecialchars($link, ENT_QUOTES | ENT_HTML5),
            );
            ($this->mailer)($to, 'Verify your Antarctic account', $html);
        } catch (Throwable $e) {
            $this->logger->warning('auth.register.mail_failed', ['error' => $e->getMessage()]);
        }
    }
}
