<?php


require_once 'AppController.php';
require_once __DIR__ . '/../repositories/ProfilesRepository.php';
require_once __DIR__ . '/../repositories/ProviderAccountsRepository.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';

class SettingsController extends AppController
{
    private ProfilesRepository $profilesRepository;
    private ProviderAccountsRepository $providerAccountsRepository;
    private UsersRepository $usersRepository;

    public function __construct()
    {
        $this->profilesRepository = new ProfilesRepository();
        $this->providerAccountsRepository = new ProviderAccountsRepository();
        $this->usersRepository = new UsersRepository();
    }

    public function index()
    {
        $this->requireCompletedOnboarding();

        $userId = (int) $_SESSION['user_id'];

        return $this->render('settings', [
            'userEmail' => $_SESSION['user_email'],
            'activePage' => 'settings',
            'user' => $this->usersRepository->getUserById($userId),
            'profile' => $this->profilesRepository->getProfileByUserId($userId),
            'spotifyConnected' => $this->providerAccountsRepository->isConnected($userId, 'spotify'),
        ]);
    }

    public function updateAccount()
    {
        $this->requireCompletedOnboarding();

        $userId = (int) $_SESSION['user_id'];
        $email = trim($_POST['email'] ?? '');
        $displayName = trim($_POST['display_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        if ($displayName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $url = "http://$_SERVER[HTTP_HOST]";
            header("Location: {$url}/settings");
            exit();
        }

        if ($this->usersRepository->emailExistsForOtherUser($email, $userId)) {
            $url = "http://$_SERVER[HTTP_HOST]";
            header("Location: {$url}/settings");
            exit();
        }

        $hashedPassword = null;
        if ($password !== '' || $passwordConfirm !== '') {
            if ($password !== $passwordConfirm || strlen($password) < 8) {
                $url = "http://$_SERVER[HTTP_HOST]";
                header("Location: {$url}/settings");
                exit();
            }

            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        }

        $this->usersRepository->updateAccount($userId, $email, $displayName, $hashedPassword);
        $_SESSION['user_email'] = $email;

        $this->profilesRepository->updateProfile($userId, [
            'bio' => $this->nullablePostValue('bio'),
            'city' => $this->nullablePostValue('city'),
            'birth_date' => $this->nullablePostValue('birth_date'),
            'gender' => $this->nullablePostValue('gender'),
            'looking_for' => $this->nullablePostValue('looking_for'),
            'instagram_handle' => $this->nullablePostValue('instagram_handle'),
            'facebook_handle' => $this->nullablePostValue('facebook_handle'),
            'spotify_handle' => $this->nullablePostValue('spotify_handle'),
        ]);

        $url = "http://$_SERVER[HTTP_HOST]";
        header("Location: {$url}/settings");
        exit();
    }

    public function connectProvider(string $provider)
    {
        $this->requireLogin();

        if ($provider !== 'spotify') {
            $this->redirect('/onboarding');
        }

        $this->providerAccountsRepository->upsert(
            (int) $_SESSION['user_id'],
            'spotify',
            'pending-oauth-token',
            null,
            3600
        );

        if (!$this->hasCompletedOnboarding((int) $_SESSION['user_id'])) {
            $this->redirect('/onboarding');
        }

        $this->redirect('/settings');
    }

    public function providerCallback(string $provider)
    {
        $this->requireLogin();

        if (!$this->hasCompletedOnboarding((int) $_SESSION['user_id'])) {
            $this->redirect('/onboarding');
        }

        $this->redirect('/settings');
    }

    public function syncMusic()
    {
        $this->requireCompletedOnboarding();

        $url = "http://$_SERVER[HTTP_HOST]";
        header("Location: {$url}/settings");
        exit();
    }

    private function nullablePostValue(string $key): ?string
    {
        $value = trim($_POST[$key] ?? '');
        return $value === '' ? null : $value;
    }
}
