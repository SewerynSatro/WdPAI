<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';
require_once __DIR__ . '/../repositories/ProfilesRepository.php';

class SecurityController extends AppController {

    private UsersRepository $usersRepository;
    private const AUTH_ERROR_MESSAGE = 'Nieprawidlowy email lub haslo.';
    private const REGISTER_ERROR_MESSAGE = 'Nie mozna utworzyc konta z podanymi danymi.';

    public function __construct() {
        $this->usersRepository = UsersRepository::getInstance();
    }

    public function login() {
        $this->requireHttps();

        if ($this->isPost()) {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->render('login', [
                    'messages' => self::AUTH_ERROR_MESSAGE,
                ]);
            }

            $user = $this->usersRepository->getUserByEmail($email);

            if (!$user || !password_verify($password, $user['password'])) {
                return $this->render('login', [
                    'messages' => self::AUTH_ERROR_MESSAGE,
                ]);
            }

            $this->startSession();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];

            if ($this->hasCompletedOnboarding((int) $user['id'])) {
                $this->redirect('/discover');
            }

            $this->redirect('/onboarding');
        }

        return $this->render('login');
    }

    public function register() {
        $this->requireHttps();

        if ($this->isPost()) {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $password2 = $_POST['password2'] ?? '';
            $displayName = trim($_POST['displayName'] ?? '');

            if (!$email || !$password || !$displayName) {
                return $this->render('register', [
                    'messages' => 'Wypelnij wszystkie pola.',
                ]);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->render('register', [
                    'messages' => self::REGISTER_ERROR_MESSAGE,
                ]);
            }

            if ($password !== $password2) {
                return $this->render('register', [
                    'messages' => 'Hasla nie sa identyczne.',
                ]);
            }

            if (strlen($password) < 8) {
                return $this->render('register', [
                    'messages' => 'Haslo musi miec minimum 8 znakow.',
                ]);
            }

            if ($this->usersRepository->getUserByEmail($email)) {
                return $this->render('register', [
                    'messages' => self::REGISTER_ERROR_MESSAGE,
                ]);
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

        return $this->render('register');
    }

    public function logout() {
        $this->startSession();
        session_destroy();

        $this->redirect('/login');
    }
}
