<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';
require_once __DIR__ . '/../repositories/ProfilesRepository.php';

class SecurityController extends AppController {

    private UsersRepository $usersRepository;
    private const AUTH_ERROR_MESSAGE = 'Nieprawidlowy email lub haslo.';
    private const REGISTER_ERROR_MESSAGE = 'Nie mozna utworzyc konta z podanymi danymi.';
    private const LOGIN_LOCK_MESSAGE = 'Zbyt wiele nieudanych prob logowania. Sprobuj ponownie za kilka minut.';
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOGIN_LOCK_SECONDS = 300;

    public function __construct() {
        $this->usersRepository = UsersRepository::getInstance();
    }

    public function login() {
        $this->requireHttps();

        if (!$this->isAllowedFormMethod()) {
            $this->rejectUnsupportedMethod();
        }

        if ($this->isPost()) {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (!$this->isValidCsrfToken('login', $_POST['csrf_token'] ?? null)) {
                return $this->renderLogin(self::AUTH_ERROR_MESSAGE, 403);
            }

            if ($this->isLoginLocked($email)) {
                return $this->renderLogin(self::LOGIN_LOCK_MESSAGE, 429);
            }

            if (strlen($email) > 255 || strlen($password) > 128) {
                $this->recordFailedLogin($email, 'input_length');
                return $this->renderLogin(self::AUTH_ERROR_MESSAGE, 401);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->recordFailedLogin($email, 'invalid_email_format');
                return $this->renderLogin(self::AUTH_ERROR_MESSAGE, 401);
            }

            $user = $this->usersRepository->getAuthUserByEmail($email);

            if (!$user || !password_verify($password, $user['password'])) {
                $this->recordFailedLogin($email, $user ? 'invalid_password' : 'unknown_email');
                return $this->renderLogin(self::AUTH_ERROR_MESSAGE, 401);
            }

            $this->startSession();
            session_regenerate_id(true);
            $this->clearFailedLogin($email);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];

            if ($this->hasCompletedOnboarding((int) $user['id'])) {
                $this->redirect('/discover');
            }

            $this->redirect('/onboarding');
        }

        return $this->renderLogin();
    }

    public function register() {
        $this->requireHttps();

        if (!$this->isAllowedFormMethod()) {
            $this->rejectUnsupportedMethod();
        }

        if ($this->isPost()) {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $password2 = $_POST['password2'] ?? '';
            $displayName = trim($_POST['displayName'] ?? '');

            if (!$this->isValidCsrfToken('register', $_POST['csrf_token'] ?? null)) {
                return $this->renderRegister(self::REGISTER_ERROR_MESSAGE, 403);
            }

            if (!$email || !$password || !$displayName) {
                return $this->renderRegister('Wypelnij wszystkie pola.', 400);
            }

            if (strlen($email) > 255 || strlen($password) > 128 || strlen($password2) > 128 || strlen($displayName) > 50) {
                return $this->renderRegister(self::REGISTER_ERROR_MESSAGE, 400);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->renderRegister(self::REGISTER_ERROR_MESSAGE, 400);
            }

            if ($password !== $password2) {
                return $this->renderRegister('Hasla nie sa identyczne.', 400);
            }

            if (strlen($password) < 8) {
                return $this->renderRegister('Haslo musi miec minimum 8 znakow, mala i wielka litere, cyfre oraz znak specjalny.', 400);
            }

            if (!$this->isStrongPassword($password)) {
                return $this->renderRegister('Haslo musi miec minimum 8 znakow, mala i wielka litere, cyfre oraz znak specjalny.', 400);
            }

            if ($this->usersRepository->getUserByEmail($email)) {
                return $this->renderRegister(self::REGISTER_ERROR_MESSAGE, 400);
            }

            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            $this->usersRepository->createUser(
                $email,
                $hashedPassword,
                $displayName
            );

            $user = $this->usersRepository->getUserByEmail($email);
            $profilesRepository = new ProfilesRepository();
            $profilesRepository->createForUser((int) $user['id']);

            $this->redirect('/login');
        }

        return $this->renderRegister();
    }

    public function logout() {
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

        $this->redirect('/login');
    }

    private function renderLogin(?string $message = null, int $statusCode = 200)
    {
        http_response_code($statusCode);

        return $this->render('login', [
            'messages' => $message,
            'csrfToken' => $this->csrfToken('login'),
        ]);
    }

    private function renderRegister(?string $message = null, int $statusCode = 200)
    {
        http_response_code($statusCode);

        return $this->render('register', [
            'messages' => $message,
            'csrfToken' => $this->csrfToken('register'),
        ]);
    }

    private function isStrongPassword(string $password): bool
    {
        return strlen($password) >= 8
            && strlen($password) <= 128
            && preg_match('/[a-z]/', $password)
            && preg_match('/[A-Z]/', $password)
            && preg_match('/\d/', $password)
            && preg_match('/[^a-zA-Z\d]/', $password);
    }

    private function isLoginLocked(string $email): bool
    {
        $this->startSession();
        $key = $this->loginThrottleKey($email);
        $attempt = $_SESSION['login_attempts'][$key] ?? null;

        if (!$attempt || empty($attempt['locked_until'])) {
            return false;
        }

        if ((int) $attempt['locked_until'] <= time()) {
            unset($_SESSION['login_attempts'][$key]);
            return false;
        }

        return true;
    }

    private function recordFailedLogin(string $email, string $reason): void
    {
        $this->startSession();
        $key = $this->loginThrottleKey($email);
        $attempt = $_SESSION['login_attempts'][$key] ?? ['count' => 0, 'locked_until' => 0];
        $attempt['count'] = ((int) ($attempt['count'] ?? 0)) + 1;

        if ($attempt['count'] >= self::MAX_LOGIN_ATTEMPTS) {
            $attempt['locked_until'] = time() + self::LOGIN_LOCK_SECONDS;
        }

        $_SESSION['login_attempts'][$key] = $attempt;
        $this->auditFailedLogin($email, $reason, (int) $attempt['count']);
    }

    private function clearFailedLogin(string $email): void
    {
        $this->startSession();
        unset($_SESSION['login_attempts'][$this->loginThrottleKey($email)]);
    }

    private function loginThrottleKey(string $email): string
    {
        $identity = strtolower(trim($email));
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        return hash('sha256', $identity . '|' . $ip);
    }

    private function auditFailedLogin(string $email, string $reason, int $attemptCount): void
    {
        $emailHash = hash('sha256', strtolower(trim($email)));
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        error_log(sprintf(
            'auth.failed_login email_hash=%s ip=%s reason=%s attempt=%d',
            $emailHash,
            $ip,
            $reason,
            $attemptCount
        ));
    }
}
