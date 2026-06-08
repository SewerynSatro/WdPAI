<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repositories/ReportsRepository.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';

class ReportsController extends AppController {
    private ReportsRepository $reportsRepository;
    private UsersRepository $usersRepository;

    public function __construct()
    {
        $this->reportsRepository = new ReportsRepository();
        $this->usersRepository = UsersRepository::getInstance();
    }

    public function create(): void
    {
        $this->requireCompletedOnboarding();

        if (!$this->isPost()) {
            $this->rejectUnsupportedMethod();
        }

        $this->requireValidCsrfToken();

        $reporterId = (int) $_SESSION['user_id'];
        $reportedUserId = (int) ($_POST['reported_user_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'Reported from profile');
        $redirectTo = $_POST['redirect_to'] ?? '/discover';

        if (
            $reportedUserId > 0
            && $reportedUserId !== $reporterId
            && $this->usersRepository->getUserById($reportedUserId)
        ) {
            $this->reportsRepository->createReport($reporterId, $reportedUserId, $reason);
        }

        $this->redirect($this->safeRedirectPath($redirectTo));
    }

    private function safeRedirectPath(string $path): string
    {
        if (substr($path, 0, 1) !== '/' || substr($path, 0, 2) === '//') {
            return '/discover';
        }

        return $path;
    }
}
