<?php

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repositories/MatchesRepository.php';
require_once __DIR__ . '/../repositories/MusicRepository.php';
require_once __DIR__ . '/../repositories/ProviderAccountsRepository.php';
require_once __DIR__ . '/../repositories/SwipesRepository.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';

class AdminController extends AppController {
    private UsersRepository $usersRepository;
    private MatchesRepository $matchesRepository;
    private SwipesRepository $swipesRepository;
    private ProviderAccountsRepository $providerAccountsRepository;
    private MusicRepository $musicRepository;

    public function __construct()
    {
        $this->usersRepository = UsersRepository::getInstance();
        $this->matchesRepository = new MatchesRepository();
        $this->swipesRepository = new SwipesRepository();
        $this->providerAccountsRepository = new ProviderAccountsRepository();
        $this->musicRepository = new MusicRepository();
    }

    public function index(): void
    {
        $this->requireAdmin();

        $this->render('admin', [
            'pageTitle' => 'Admin | HeartBeat',
            'activePage' => 'admin',
            'userEmail' => $_SESSION['user_email'] ?? '',
            'stats' => [
                'users' => $this->usersRepository->countAll(),
                'activeUsers' => $this->usersRepository->countActive(),
                'matches' => $this->matchesRepository->countAll(),
                'swipes' => $this->swipesRepository->countAll(),
                'spotifyAccounts' => $this->providerAccountsRepository->countConnectedAccounts('spotify'),
            ],
            'latestUsers' => $this->usersRepository->getLatestUsers(),
            'topArtist' => $this->musicRepository->getMostPopularArtist(),
        ]);
    }
}
