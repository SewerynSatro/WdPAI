<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';
require_once __DIR__ . '/../repositories/ProfilesRepository.php';

class SecurityController extends AppController {

    private UsersRepository $usersRepository;

    public function __construct() {
        $this->usersRepository = new UsersRepository();
    }

    public function login() {
        if ($this->isPost()) {
            $email    = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';

            $usersRepository = new UsersRepository();
            $user = $usersRepository->getUserByEmail($email);

            if (!$user || !password_verify($password, $user['password'])) {
                return $this->render('login', [
                    'messages' => 'Nieprawidłowy email lub hasło.'
                ]);
            }

            $this->startSession();
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_email'] = $user['email'];

            if ($this->hasCompletedOnboarding((int) $user['id'])) {
                $this->redirect('/discover');
            }

            $this->redirect('/onboarding');
        }

        return $this->render('login');
    }

    public function register() {
        if ($this->isPost()) {
            $email      = trim($_POST['email'] ?? '');
            $password   = $_POST['password'] ?? '';
            $password2  = $_POST['password2'] ?? '';
            $firstName  = trim($_POST['firstName'] ?? '');
            $lastName   = trim($_POST['lastName'] ?? '');

            // Walidacja
            if (!$email || !$password || !$firstName || !$lastName) {
                return $this->render('register', [
                    'messages' => 'Wypełnij wszystkie pola.'
                ]);
            }

            if ($password !== $password2) {
                return $this->render('register', [
                    'messages' => 'Hasła nie są identyczne.'
                ]);
            }

            if (strlen($password) < 8) {
                return $this->render('register', [
                    'messages' => 'Hasło musi mieć minimum 8 znaków.'
                ]);
            }

            if ($this->usersRepository->getUserByEmail($email)) {
                return $this->render('register', [
                    'messages' => 'Konto z tym emailem już istnieje.'
                ]);
            }

            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            $this->usersRepository->createUser(
                $email,
                $hashedPassword,
                $firstName,
                $lastName
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
