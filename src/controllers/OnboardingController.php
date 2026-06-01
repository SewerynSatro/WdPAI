<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repositories/ProfilesRepository.php';
require_once __DIR__ . '/../repositories/ProviderAccountsRepository.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';

class OnboardingController extends AppController {

    private ProfilesRepository $profilesRepository;
    private ProviderAccountsRepository $providerAccountsRepository;
    private UsersRepository $usersRepository;

    public function __construct() {
        $this->profilesRepository = new ProfilesRepository();
        $this->providerAccountsRepository = new ProviderAccountsRepository();
        $this->usersRepository = new UsersRepository();
    }

    public function index() {
        $this->requireLogin();

        $userId = (int) $_SESSION['user_id'];
        $profile = $this->profilesRepository->getProfileByUserId($userId);

        if ($this->hasCompletedOnboarding($userId)) {
            $this->redirect('/discover');
        }

        return $this->render('onboarding', [
            'userEmail' => $_SESSION['user_email'],
            'user' => $this->usersRepository->getUserById($userId),
            'profile' => $profile,
            'spotifyConnected' => $this->providerAccountsRepository->isConnected($userId, 'spotify'),
        ]);
    }

    public function save() {
        $this->requireLogin();

        $userId = (int) $_SESSION['user_id'];
        $user = $this->usersRepository->getUserById($userId);
        $displayName = trim($_POST['display_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $birthDate = trim($_POST['birth_date'] ?? '');
        $bio = $this->nullablePostValue('bio');
        $city = $this->nullablePostValue('city');
        $gender = $this->nullablePostValue('gender');
        $lookingFor = $this->nullablePostValue('looking_for');
        $instagram = $this->nullablePostValue('instagram_handle');
        $facebook = $this->nullablePostValue('facebook_handle');
        $spotify = $this->nullablePostValue('spotify_handle');
        $spotifyConnected = $this->providerAccountsRepository->isConnected($userId, 'spotify');
        $missing = $this->missingRequiredFields([
            'displayName' => $displayName,
            'email' => $email,
            'bio' => $bio,
            'city' => $city,
            'birthDate' => $birthDate,
            'gender' => $gender,
            'lookingFor' => $lookingFor,
            'hasSocial' => $instagram || $facebook || $spotify,
            'hasMusicProvider' => $spotifyConnected,
        ]);

        if ($displayName !== '' || $email !== '') {
            $currentEmail = $email !== '' ? $email : ($user['email'] ?? $_SESSION['user_email']);
            $currentDisplayName = $displayName !== ''
                ? $displayName
                : ($user['display_name'] ?? trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')));

            if (filter_var($currentEmail, FILTER_VALIDATE_EMAIL)) {
                $this->usersRepository->updateAccount($userId, $currentEmail, $currentDisplayName);
                $_SESSION['user_email'] = $currentEmail;
            }
        }

        $this->profilesRepository->updateProfile($userId, [
            'bio' => $bio,
            'city' => $city,
            'birth_date' => $birthDate ?: null,
            'gender' => $gender,
            'looking_for' => $lookingFor,
            'instagram_handle' => $instagram,
            'facebook_handle' => $facebook,
            'spotify_handle' => $spotify,
            'latitude' => $this->nullableFloatPostValue('latitude'),
            'longitude' => $this->nullableFloatPostValue('longitude'),
            'onboarding_completed' => empty($missing),
        ]);

        if (!empty($missing)) {
            return $this->render('onboarding', [
                'messages' => 'Brakuje danych, żeby kogoś poznać: ' . implode(', ', $missing) . '.',
                'userEmail' => $_SESSION['user_email'],
                'user' => $this->usersRepository->getUserById($userId),
                'profile' => $this->profilesRepository->getProfileByUserId($userId),
                'spotifyConnected' => $spotifyConnected,
            ]);
        }

        $this->redirect('/discover');
    }

    private function nullablePostValue(string $key): ?string {
        $value = trim($_POST[$key] ?? '');
        return $value === '' ? null : $value;
    }

    private function nullableFloatPostValue(string $key): ?float {
        $value = trim($_POST[$key] ?? '');
        return $value === '' || !is_numeric($value) ? null : (float) $value;
    }

    private function missingRequiredFields(array $data): array {
        $missing = [];

        if ($data['displayName'] === '') {
            $missing[] = 'display name';
        }
        if ($data['email'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $missing[] = 'valid email';
        }
        if (!$data['bio']) {
            $missing[] = 'bio';
        }
        if (!$data['city']) {
            $missing[] = 'city';
        }
        if ($data['birthDate'] === '') {
            $missing[] = 'date of birth';
        }
        if (!$data['gender']) {
            $missing[] = 'gender';
        }
        if (!$data['lookingFor']) {
            $missing[] = 'looking for';
        }
        if (!$data['hasSocial']) {
            $missing[] = 'at least one social handle';
        }
        if (!$data['hasMusicProvider']) {
            $missing[] = 'music provider connection';
        }

        return $missing;
    }
}
