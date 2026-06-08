<?php

require_once __DIR__ . '/../repositories/ProfilesRepository.php';
require_once __DIR__ . '/../repositories/ProviderAccountsRepository.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';

class AppController {
    protected function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $this->configureSessionCookie();
            session_start();
        }
    }

    private function configureSessionCookie(): void
    {
        $params = session_get_cookie_params();

        session_set_cookie_params([
            'lifetime' => $params['lifetime'],
            'path' => $params['path'] ?: '/',
            'domain' => $params['domain'],
            'secure' => !$this->isLocalRequest(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    protected function isGet(): bool
    {
        return $_SERVER["REQUEST_METHOD"] === 'GET';
    }

    protected function isPost(): bool
    {
        return $_SERVER["REQUEST_METHOD"] === 'POST';
    }

    protected function isAllowedFormMethod(): bool
    {
        return $this->isGet() || $this->isPost();
    }

    protected function rejectUnsupportedMethod(): void
    {
        http_response_code(405);
        header('Allow: GET, POST');
        exit();
    }

    protected function redirect(string $path): void
    {
        $scheme = $this->isHttpsRequest() ? 'https' : 'http';
        $url = "{$scheme}://$_SERVER[HTTP_HOST]";
        header("Location: {$url}{$path}");
        exit();
    }

    protected function requireHttps(): void
    {
        if ($this->isLocalRequest() || $this->isHttpsRequest()) {
            return;
        }

        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        header("Location: https://{$host}{$uri}", true, 301);
        exit();
    }

    private function isHttpsRequest(): bool
    {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));

        return $https === 'on'
            || $https === '1'
            || $forwardedProto === 'https';
    }

    private function isLocalRequest(): bool
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $host = explode(':', $host)[0];

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    protected function csrfToken(string $scope): string
    {
        $this->startSession();
        $key = "csrf_{$scope}";

        if (empty($_SESSION[$key])) {
            $_SESSION[$key] = bin2hex(random_bytes(32));
        }

        return $_SESSION[$key];
    }

    protected function isValidCsrfToken(string $scope, ?string $token): bool
    {
        $this->startSession();
        $expected = $_SESSION["csrf_{$scope}"] ?? '';

        return is_string($token) && $expected !== '' && hash_equals($expected, $token);
    }

    protected function requireValidCsrfToken(string $scope = 'app', bool $json = false): void
    {
        if ($this->isValidCsrfToken($scope, $_POST['csrf_token'] ?? null)) {
            return;
        }

        http_response_code(403);

        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'invalid_csrf']);
        }

        exit();
    }

    protected function requireLogin(): bool
    {
        $this->startSession();

        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/login');
        }

        $usersRepository = UsersRepository::getInstance();
        $user = $usersRepository->getUserById((int) $_SESSION['user_id']);

        if (!$user || empty($user['is_active'])) {
            $this->destroyCurrentSession();
            $this->redirect('/login?blocked=1');
        }

        return true;
    }

    protected function destroyCurrentSession(): void
    {
        $this->startSession();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => (bool) $params['secure'],
                    'httponly' => (bool) $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }

        session_destroy();
    }

    protected function requireCompletedOnboarding(): bool
    {
        $this->requireLogin();

        if (!$this->hasCompletedOnboarding((int) $_SESSION['user_id'])) {
            $this->redirect('/onboarding');
        }

        return true;
    }

    protected function requireAdmin(): bool
    {
        $this->requireLogin();

        if (!$this->isCurrentUserAdmin()) {
            http_response_code(403);
            include 'public/views/403.html';
            exit();
        }

        return true;
    }

    protected function isCurrentUserAdmin(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION['user_id'])) {
            return false;
        }

        $usersRepository = UsersRepository::getInstance();
        $user = $usersRepository->getUserById((int) $_SESSION['user_id']);

        return !empty($user['is_active']) && strtoupper((string) ($user['role'] ?? '')) === 'ADMIN';
    }

    protected function hasCompletedOnboarding(int $userId): bool
    {
        $profilesRepository = new ProfilesRepository();
        $providerAccountsRepository = new ProviderAccountsRepository();
        $usersRepository = UsersRepository::getInstance();
        $user = $usersRepository->getUserById($userId);
        $profile = $profilesRepository->getProfileByUserId($userId);

        if (!$user || !$profile) {
            return false;
        }

        $hasCompletedFlag = in_array($profile['onboarding_completed'], [true, 1, '1', 't', 'true'], true);
        $hasRequiredProfile = trim($user['display_name'] ?? '') !== ''
            && filter_var($user['email'] ?? '', FILTER_VALIDATE_EMAIL)
            && trim($profile['bio'] ?? '') !== ''
            && trim($profile['birth_date'] ?? '') !== ''
            && trim($profile['gender'] ?? '') !== ''
            && trim($profile['looking_for'] ?? '') !== '';
        $hasSocial = trim($profile['instagram_handle'] ?? '') !== ''
            || trim($profile['facebook_handle'] ?? '') !== ''
            || trim($profile['spotify_handle'] ?? '') !== '';
        $hasProvider = $providerAccountsRepository->isConnected($userId, 'spotify');

        return $hasCompletedFlag && $hasRequiredProfile && $hasSocial && $hasProvider;
    }
 
    protected function render(string $template = null, array $variables = [])
    {
        $templatePath = 'public/views/'. $template.'.html';
        $templatePath404 = 'public/views/404.html';
        $output = "";
                 
        if(file_exists($templatePath)){
            $variables['isAdmin'] ??= $this->isCurrentUserAdmin();
            $variables['appCsrfToken'] ??= $this->csrfToken('app');
            extract($variables);
            // ["tab_name" => $title]

            // $tab_name = $title

            ob_start();
            include $templatePath;
            $output = ob_get_clean();
        } else {
            ob_start();
            include $templatePath404;
            $output = ob_get_clean();
        }
        echo $output;
    }

}
