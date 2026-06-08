<?php

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repositories/MatchesRepository.php';
require_once __DIR__ . '/../repositories/MusicRepository.php';
require_once __DIR__ . '/../repositories/ProviderAccountsRepository.php';
require_once __DIR__ . '/../repositories/ReportsRepository.php';
require_once __DIR__ . '/../repositories/SwipesRepository.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';

class AdminController extends AppController {
    private UsersRepository $usersRepository;
    private MatchesRepository $matchesRepository;
    private SwipesRepository $swipesRepository;
    private ProviderAccountsRepository $providerAccountsRepository;
    private MusicRepository $musicRepository;
    private ReportsRepository $reportsRepository;

    public function __construct()
    {
        $this->usersRepository = UsersRepository::getInstance();
        $this->matchesRepository = new MatchesRepository();
        $this->swipesRepository = new SwipesRepository();
        $this->providerAccountsRepository = new ProviderAccountsRepository();
        $this->musicRepository = new MusicRepository();
        $this->reportsRepository = new ReportsRepository();
    }

    public function index(): void
    {
        $this->requireAdmin();
        $reportFilter = $this->reportFilter($_GET['filter'] ?? 'open');

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
                'reportedUsers' => $this->reportsRepository->countOpen(),
                'resolvedReports' => $this->reportsRepository->countResolved(),
                'bannedReports' => $this->reportsRepository->countBanned(),
            ],
            'latestUsers' => $this->usersRepository->getLatestUsers(),
            'reportedUsers' => $this->reportsRepository->getReportedUsers($reportFilter),
            'reportFilter' => $reportFilter,
            'topArtist' => $this->musicRepository->getMostPopularArtist(),
        ]);
    }

    public function reportedUser(int $userId): void
    {
        $this->requireAdmin();

        $profile = $this->usersRepository->getAdminUserDetails($userId);

        if (!$profile) {
            include 'public/views/404.html';
            return;
        }

        $profile['age'] = $this->ageFromBirthDate($profile['birth_date'] ?? null);

        $this->render('admin-report-profile', [
            'pageTitle' => 'Reported Profile | HeartBeat',
            'activePage' => 'admin',
            'userEmail' => $_SESSION['user_email'] ?? '',
            'reportedProfile' => $profile,
            'reports' => $this->reportsRepository->getReportsForUser($userId),
            'hasOpenReports' => $this->reportsRepository->hasOpenReportsForUser($userId),
            'musicPreview' => $this->musicRepository->getMusicPreviewForUser($userId),
        ]);
    }

    public function banUser(int $userId): void
    {
        $this->requireAdmin();

        if (!$this->isPost()) {
            $this->rejectUnsupportedMethod();
        }

        $this->requireValidCsrfToken();

        $currentAdminId = (int) $_SESSION['user_id'];
        $profile = $this->usersRepository->getUserById($userId);

        if (!$profile) {
            include 'public/views/404.html';
            return;
        }

        if ($userId !== $currentAdminId && strtoupper((string) ($profile['role'] ?? '')) !== 'ADMIN') {
            $this->usersRepository->deactivateUser($userId);
            $this->reportsRepository->resolveOpenReportsForUser($userId, $currentAdminId, 'User banned by admin');
        }

        $this->redirect('/admin/reports/' . $userId);
    }

    public function unbanUser(int $userId): void
    {
        $this->requireAdmin();

        if (!$this->isPost()) {
            $this->rejectUnsupportedMethod();
        }

        $this->requireValidCsrfToken();

        $profile = $this->usersRepository->getUserById($userId);

        if (!$profile) {
            include 'public/views/404.html';
            return;
        }

        if (strtoupper((string) ($profile['role'] ?? '')) !== 'ADMIN') {
            $this->usersRepository->activateUser($userId);
            $this->reportsRepository->resolveOpenReportsForUser(
                $userId,
                (int) $_SESSION['user_id'],
                'User unbanned by admin'
            );
        }

        $this->redirect('/admin/reports/' . $userId);
    }

    public function dismissReports(int $userId): void
    {
        $this->requireAdmin();

        if (!$this->isPost()) {
            $this->rejectUnsupportedMethod();
        }

        $this->requireValidCsrfToken();

        if (!$this->usersRepository->getUserById($userId)) {
            include 'public/views/404.html';
            return;
        }

        $this->reportsRepository->resolveOpenReportsForUser(
            $userId,
            (int) $_SESSION['user_id'],
            'Reports dismissed by admin'
        );

        $this->redirect('/admin/reports/' . $userId);
    }

    private function reportFilter(string $filter): string
    {
        return in_array($filter, ['open', 'resolved', 'banned'], true) ? $filter : 'open';
    }

}
