<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repositories/MusicRepository.php';
require_once __DIR__ . '/../repositories/ProfilesRepository.php';
require_once __DIR__ . '/../repositories/ProviderAccountsRepository.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';

class ProfileController extends AppController {

    private MusicRepository $musicRepository;
    private ProfilesRepository $profilesRepository;
    private ProviderAccountsRepository $providerAccountsRepository;
    private UsersRepository $usersRepository;

    public function __construct() {
        $this->musicRepository = new MusicRepository();
        $this->profilesRepository = new ProfilesRepository();
        $this->providerAccountsRepository = new ProviderAccountsRepository();
        $this->usersRepository = UsersRepository::getInstance();
    }

    public function index() {
        $this->requireCompletedOnboarding();

        $userId = (int) $_SESSION['user_id'];
        $range = $_GET['range'] ?? 'medium_term';

        if (!in_array($range, ['short_term', 'medium_term', 'long_term'], true)) {
            $range = 'medium_term';
        }

        return $this->render('profile', [
            'userEmail'  => $_SESSION['user_email'],
            'activePage' => 'profile',
            'user' => $this->usersRepository->getUserById($userId),
            'profile' => $this->profilesRepository->getProfileByUserId($userId),
            'spotifyConnected' => $this->providerAccountsRepository->isConnected($userId, 'spotify'),
            'artists' => $this->musicRepository->getArtistsForUser($userId, $range),
            'tracks' => $this->musicRepository->getTopTracksForUser($userId, $range),
            'genres' => $this->musicRepository->getGenresForUser($userId),
            'recentPlays' => $this->musicRepository->getRecentPlaysForUser($userId),
            'currentRange' => $range,
        ]);
    }
}
