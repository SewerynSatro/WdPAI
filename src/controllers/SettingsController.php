<?php


require_once 'AppController.php';
require_once __DIR__ . '/../repositories/ProfilesRepository.php';
require_once __DIR__ . '/../repositories/ProviderAccountsRepository.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';
require_once __DIR__ . '/../providers/SpotifyProvider.php';
require_once __DIR__ . '/../services/MusicSyncService.php';
require_once __DIR__ . '/../services/PasswordPolicy.php';

class SettingsController extends AppController
{
    private ProfilesRepository $profilesRepository;
    private ProviderAccountsRepository $providerAccountsRepository;
    private SpotifyProvider $spotifyProvider;
    private MusicSyncService $musicSyncService;
    private UsersRepository $usersRepository;

    public function __construct()
    {
        $this->profilesRepository = new ProfilesRepository();
        $this->providerAccountsRepository = new ProviderAccountsRepository();
        $this->spotifyProvider = new SpotifyProvider();
        $this->musicSyncService = new MusicSyncService();
        $this->usersRepository = UsersRepository::getInstance();
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
        $this->requireValidCsrfToken();

        $userId = (int) $_SESSION['user_id'];
        $email = trim($_POST['email'] ?? '');
        $displayName = trim($_POST['display_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        if (
            $displayName === ''
            || strlen($displayName) > 50
            || strlen($email) > 255
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            $this->redirect('/settings');
        }

        if ($this->usersRepository->emailExistsForOtherUser($email, $userId)) {
            $this->redirect('/settings');
        }

        $hashedPassword = null;
        if ($password !== '' || $passwordConfirm !== '') {
            if (
                $password !== $passwordConfirm
                || !PasswordPolicy::isStrong($password)
            ) {
                $this->redirect('/settings');
            }

            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        }

        $this->usersRepository->updateAccount($userId, $email, $displayName, $hashedPassword);
        $_SESSION['user_email'] = $email;

        $this->profilesRepository->updateProfile($userId, [
            'bio' => $this->nullablePostValue('bio'),
            'birth_date' => $this->nullablePostValue('birth_date'),
            'gender' => $this->nullablePostValue('gender'),
            'looking_for' => $this->nullablePostValue('looking_for'),
            'instagram_handle' => $this->nullablePostValue('instagram_handle'),
            'facebook_handle' => $this->nullablePostValue('facebook_handle'),
            'spotify_handle' => $this->nullablePostValue('spotify_handle'),
            'latitude' => $this->nullableFloatPostValue('latitude'),
            'longitude' => $this->nullableFloatPostValue('longitude'),
            'max_distance_km' => $this->boundedIntPostValue('max_distance_km', 5, 500, 50),
        ]);

        $this->redirect('/settings');
    }

    public function updateLocation()
    {
        $this->requireLogin();
        $this->requireValidCsrfToken('app', true);

        $userId = (int) $_SESSION['user_id'];
        $latitude = $this->nullableFloatPostValue('latitude');
        $longitude = $this->nullableFloatPostValue('longitude');

        if ($latitude === null || $longitude === null) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false]);
            return;
        }

        $this->profilesRepository->updateProfile($userId, [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);

        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
    }

    public function updateDistance()
    {
        $this->requireLogin();
        $this->requireValidCsrfToken('app', true);

        $userId = (int) $_SESSION['user_id'];
        $maxDistanceKm = $this->boundedIntPostValue('max_distance_km', 5, 500, 50);

        $this->profilesRepository->updateProfile($userId, [
            'max_distance_km' => $maxDistanceKm,
        ]);

        header('Content-Type: application/json');
        echo json_encode([
            'ok' => true,
            'max_distance_km' => $maxDistanceKm,
        ]);
    }

    public function connectProvider(string $provider)
    {
        $this->requireLogin();

        if ($provider !== 'spotify') {
            $this->redirect('/onboarding');
        }

        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;
        $_SESSION['oauth_provider'] = 'spotify';

        header('Location: ' . $this->spotifyProvider->getAuthorizationUrl($state));
        exit();
    }

    public function providerCallback(string $provider)
    {
        $this->requireLogin();

        if ($provider !== 'spotify') {
            $this->redirect('/onboarding');
        }

        $error = $_GET['error'] ?? null;
        if ($error) {
            $this->redirect('/onboarding');
        }

        $code = trim($_GET['code'] ?? '');
        $state = trim($_GET['state'] ?? '');
        $expectedState = $_SESSION['oauth_state'] ?? '';
        $expectedProvider = $_SESSION['oauth_provider'] ?? '';

        unset($_SESSION['oauth_state'], $_SESSION['oauth_provider']);

        if ($code === '' || $state === '' || !hash_equals($expectedState, $state) || $expectedProvider !== 'spotify') {
            $this->redirect('/onboarding');
        }

        $tokens = $this->spotifyProvider->exchangeAuthorizationCode($code);

        if (empty($tokens['access_token'])) {
            $this->redirect('/onboarding');
        }

        $userId = (int) $_SESSION['user_id'];
        $this->providerAccountsRepository->upsert(
            $userId,
            'spotify',
            $tokens['access_token'],
            $tokens['refresh_token'] ?? null,
            (int) ($tokens['expires_in'] ?? 3600)
        );

        try {
            $this->musicSyncService->syncAllForUser($userId, 'spotify');
        } catch (Throwable $e) {
            // Provider connection is still valid even if an immediate sync fails.
        }

        if (!$this->hasCompletedOnboarding((int) $_SESSION['user_id'])) {
            $this->redirect('/onboarding');
        }

        $this->redirect('/profile');
    }

    public function syncMusic()
    {
        $this->requireCompletedOnboarding();
        $this->requireValidCsrfToken();

        try {
            $this->musicSyncService->syncAllForUser((int) $_SESSION['user_id'], 'spotify');
        } catch (Throwable $e) {
            $this->redirect('/profile');
        }

        $this->redirect('/profile');
    }

}
