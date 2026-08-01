<?php

declare(strict_types=1);

namespace WhatstheUp\Services;

use DateTimeImmutable;
use PDO;
use Throwable;
use WhatstheUp\Security\Jwt;
use WhatstheUp\Support\Env;
use WhatstheUp\Support\HttpException;
use WhatstheUp\Support\Request;
use WhatstheUp\Support\Uuid;

final class AuthenticationService
{
    public function __construct(private readonly PDO $db, private readonly AuditService $audit)
    {
    }

    public function login(string $email, string $password, Request $request): array
    {
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            throw new HttpException(422, 'Enter a valid email and password.', 'validation_failed');
        }
        $this->guardRateLimit($email, $request->ip);
        $statement = $this->db->prepare('SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1');
        $statement->execute([$email]);
        $user = $statement->fetch();
        $valid = $user && ($user['status'] === 'active') && password_verify($password, $user['password_hash']);
        if (!$valid) {
            $this->recordFailedLogin($email, $request->ip, $user['id'] ?? null);
            throw new HttpException(401, 'The email or password is incorrect.', 'invalid_credentials');
        }
        if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
            throw new HttpException(423, 'This account is temporarily locked. Try again later.', 'account_locked');
        }
        $this->db->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$user['id']]);
        return $this->createSession($user['id'], $request);
    }

    public function refresh(string $rawToken, Request $request): array
    {
        if ($rawToken === '') {
            throw new HttpException(401, 'Authentication is required.', 'unauthenticated');
        }
        $hash = hash('sha256', $rawToken);
        $statement = $this->db->prepare('SELECT * FROM user_sessions WHERE refresh_token_hash = ? LIMIT 1');
        $statement->execute([$hash]);
        $session = $statement->fetch();
        if (!$session || $session['revoked_at'] !== null || strtotime($session['expires_at']) <= time()) {
            if ($session && $session['revoked_at'] !== null) {
                $this->db->prepare('UPDATE user_sessions SET revoked_at = COALESCE(revoked_at, UTC_TIMESTAMP()) WHERE token_family = ?')->execute([$session['token_family']]);
            }
            throw new HttpException(401, 'The session has expired.', 'session_expired');
        }
        $newRaw = $this->randomToken();
        $this->db->beginTransaction();
        try {
            $rotation = $this->db->prepare('UPDATE user_sessions SET refresh_token_hash = ?, last_used_at = UTC_TIMESTAMP(), ip_address = ?, user_agent = ? WHERE id = ? AND refresh_token_hash = ? AND revoked_at IS NULL');
            $rotation->execute([hash('sha256', $newRaw), $request->ip, $request->userAgent, $session['id'], $hash]);
            if ($rotation->rowCount() !== 1) {
                throw new HttpException(401, 'The session has expired.', 'session_expired');
            }
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
        $identity = $this->identity($session['user_id']);
        return $this->payload($identity, $session['id'], $newRaw);
    }

    public function authenticate(string $token): array
    {
        $claims = Jwt::verify($token);
        $statement = $this->db->prepare('SELECT revoked_at, expires_at FROM user_sessions WHERE id = ? AND user_id = ? LIMIT 1');
        $statement->execute([$claims['sid'] ?? '', $claims['sub'] ?? '']);
        $session = $statement->fetch();
        if (!$session || $session['revoked_at'] !== null || strtotime($session['expires_at']) <= time()) {
            throw new HttpException(401, 'Authentication is required.', 'unauthenticated');
        }
        return $this->identity((string) $claims['sub']);
    }

    public function logout(string $rawToken): void
    {
        if ($rawToken !== '') {
            $this->db->prepare('UPDATE user_sessions SET revoked_at = UTC_TIMESTAMP() WHERE refresh_token_hash = ? AND revoked_at IS NULL')->execute([hash('sha256', $rawToken)]);
        }
    }

    public function logoutAll(string $userId): void
    {
        $this->db->prepare('UPDATE user_sessions SET revoked_at = UTC_TIMESTAMP() WHERE user_id = ? AND revoked_at IS NULL')->execute([$userId]);
        $this->audit->record(null, $userId, 'auth.sessions.revoked_all', 'user', $userId);
    }

    private function createSession(string $userId, Request $request): array
    {
        $sessionId = Uuid::v4();
        $family = Uuid::v4();
        $raw = $this->randomToken();
        $expires = (new DateTimeImmutable())->modify('+' . Env::int('REFRESH_TOKEN_TTL', 2592000) . ' seconds')->format('Y-m-d H:i:s');
        $statement = $this->db->prepare('INSERT INTO user_sessions (id, user_id, token_family, refresh_token_hash, ip_address, user_agent, expires_at, created_at, last_used_at) VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())');
        $statement->execute([$sessionId, $userId, $family, hash('sha256', $raw), $request->ip, $request->userAgent, $expires]);
        $identity = $this->identity($userId);
        $this->audit->record($identity['business']['id'] ?? null, $userId, 'auth.login', 'user_session', $sessionId, ['ip' => $request->ip, 'scope' => $identity['scope']]);
        return $this->payload($identity, $sessionId, $raw);
    }

    private function identity(string $userId): array
    {
        $platform = $this->db->prepare("SELECT u.id, u.name, u.email, u.email_verified_at FROM users u JOIN user_platform_roles upr ON upr.user_id = u.id JOIN roles r ON r.id = upr.role_id AND r.scope = 'platform' WHERE u.id = ? AND u.status = 'active' AND u.deleted_at IS NULL LIMIT 1");
        $platform->execute([$userId]);
        $platformUser = $platform->fetch();
        if ($platformUser) {
            $permissions = $this->db->prepare("SELECT DISTINCT p.name FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id JOIN user_platform_roles upr ON upr.role_id = rp.role_id WHERE upr.user_id = ? ORDER BY p.name");
            $permissions->execute([$userId]);
            $roles = $this->db->prepare("SELECT DISTINCT r.name FROM roles r JOIN user_platform_roles upr ON upr.role_id = r.id WHERE upr.user_id = ? ORDER BY r.name");
            $roles->execute([$userId]);
            return [
                'id' => $platformUser['id'], 'name' => $platformUser['name'], 'email' => $platformUser['email'],
                'emailVerified' => $platformUser['email_verified_at'] !== null, 'scope' => 'platform', 'business' => null,
                'roles' => array_column($roles->fetchAll(), 'name'), 'permissions' => array_column($permissions->fetchAll(), 'name'),
            ];
        }
        $statement = $this->db->prepare("SELECT u.id, u.name, u.email, u.email_verified_at, b.id business_id, b.name business_name, b.slug business_slug, b.timezone, b.status business_status FROM users u JOIN business_users bu ON bu.user_id = u.id AND bu.status = 'active' JOIN businesses b ON b.id = bu.business_id AND b.status = 'active' WHERE u.id = ? AND u.status = 'active' AND u.deleted_at IS NULL ORDER BY bu.is_primary DESC, bu.created_at ASC LIMIT 1");
        $statement->execute([$userId]);
        $row = $statement->fetch();
        if (!$row) {
            throw new HttpException(403, 'No active business workspace is available.', 'workspace_unavailable');
        }
        $permissions = $this->db->prepare('SELECT DISTINCT p.name FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id JOIN user_roles ur ON ur.role_id = rp.role_id WHERE ur.user_id = ? AND ur.business_id = ? ORDER BY p.name');
        $permissions->execute([$userId, $row['business_id']]);
        $roles = $this->db->prepare('SELECT DISTINCT r.name FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ? AND ur.business_id = ? ORDER BY r.name');
        $roles->execute([$userId, $row['business_id']]);
        return [
            'id' => $row['id'], 'name' => $row['name'], 'email' => $row['email'], 'emailVerified' => $row['email_verified_at'] !== null,
            'scope' => 'business',
            'business' => ['id' => $row['business_id'], 'name' => $row['business_name'], 'slug' => $row['business_slug'], 'timezone' => $row['timezone'], 'status' => $row['business_status']],
            'roles' => array_column($roles->fetchAll(), 'name'), 'permissions' => array_column($permissions->fetchAll(), 'name'),
        ];
    }

    private function payload(array $identity, string $sessionId, string $refreshToken): array
    {
        $claims = ['sub' => $identity['id'], 'sid' => $sessionId, 'scope' => $identity['scope']];
        if ($identity['business'] !== null) {
            $claims['bid'] = $identity['business']['id'];
        }
        return ['accessToken' => Jwt::issue($claims), 'expiresIn' => Env::int('JWT_ACCESS_TTL', 900), 'user' => $identity, '_refreshToken' => $refreshToken];
    }

    private function guardRateLimit(string $email, string $ip): void
    {
        $statement = $this->db->prepare("SELECT COUNT(*) FROM security_logs WHERE event_type = 'login.failed' AND (identifier = ? OR ip_address = ?) AND created_at >= UTC_TIMESTAMP() - INTERVAL 15 MINUTE");
        $statement->execute([$email, $ip]);
        if ((int) $statement->fetchColumn() >= Env::int('LOGIN_MAX_ATTEMPTS', 5) * 2) {
            throw new HttpException(429, 'Too many sign-in attempts. Try again later.', 'rate_limited');
        }
    }

    private function recordFailedLogin(string $email, string $ip, ?string $userId): void
    {
        $this->db->prepare('INSERT INTO security_logs (id, user_id, event_type, identifier, ip_address, metadata, created_at) VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())')->execute([Uuid::v4(), $userId, 'login.failed', $email, $ip, '{}']);
        if ($userId !== null) {
            $max = Env::int('LOGIN_MAX_ATTEMPTS', 5);
            $seconds = Env::int('LOGIN_LOCKOUT_SECONDS', 900);
            $this->db->prepare('UPDATE users SET failed_login_attempts = failed_login_attempts + 1, locked_until = IF(failed_login_attempts + 1 >= ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND), locked_until) WHERE id = ?')->execute([$max, $seconds, $userId]);
        }
    }

    private function randomToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    }
}
