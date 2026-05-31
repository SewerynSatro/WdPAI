<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repositories/ProfilesRepository.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';

class OnboardingController extends AppController {

    private ProfilesRepository $profilesRepository;
    private UsersRepository $usersRepository;

    public function __construct() {
        $this->profilesRepository = new ProfilesRepository();
        $this->usersRepository = new UsersRepository();
    }

    public function index() {
        session_start();

        if (!isset($_SESSION['user_id'])) {
            $url = "http://$_SERVER[HTTP_HOST]";
            header("Location: {$url}/login");
            exit();
        }

        $userId = (int) $_SESSION['user_id'];
        $profile = $this->profilesRepository->getProfileByUserId($userId);

        if ($profile && $this->isCompleted($profile['onboarding_completed'])) {
            $url = "http://$_SERVER[HTTP_HOST]";
            header("Location: {$url}/discover");
            exit();
        }

        return $this->render('onboarding', [
            'userEmail' => $_SESSION['user_email'],
            'user' => $this->usersRepository->getUserById($userId),
            'profile' => $profile,
        ]);
    }

    public function save() {
        session_start();

        if (!isset($_SESSION['user_id'])) {
            $url = "http://$_SERVER[HTTP_HOST]";
            header("Location: {$url}/login");
            exit();
        }

        $userId = (int) $_SESSION['user_id'];
        $user = $this->usersRepository->getUserById($userId);
        $birthDate = trim($_POST['birth_date'] ?? '');

        if ($birthDate === '') {
            return $this->render('onboarding', [
                'messages' => 'Date of birth is required.',
                'userEmail' => $_SESSION['user_email'],
                'user' => $user,
                'profile' => $this->profilesRepository->getProfileByUserId($userId),
            ]);
        }

        $displayName = trim($_POST['display_name'] ?? '');
        $email = trim($_POST['email'] ?? '');

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
            'bio' => $this->nullablePostValue('bio'),
            'city' => $this->nullablePostValue('city'),
            'birth_date' => $birthDate,
            'gender' => $this->nullablePostValue('gender'),
            'looking_for' => $this->nullablePostValue('looking_for') ?? 'everyone',
            'instagram_handle' => $this->nullablePostValue('instagram_handle'),
            'facebook_handle' => $this->nullablePostValue('facebook_handle'),
            'spotify_handle' => $this->nullablePostValue('spotify_handle'),
            'latitude' => $this->nullableFloatPostValue('latitude'),
            'longitude' => $this->nullableFloatPostValue('longitude'),
            'onboarding_completed' => true,
        ]);

        $url = "http://$_SERVER[HTTP_HOST]";
        header("Location: {$url}/settings");
        exit();
    }

    private function nullablePostValue(string $key): ?string {
        $value = trim($_POST[$key] ?? '');
        return $value === '' ? null : $value;
    }

    private function nullableFloatPostValue(string $key): ?float {
        $value = trim($_POST[$key] ?? '');
        return $value === '' || !is_numeric($value) ? null : (float) $value;
    }

    private function isCompleted($value): bool {
        return in_array($value, [true, 1, '1', 't', 'true'], true);
    }
}
