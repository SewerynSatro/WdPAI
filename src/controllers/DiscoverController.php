<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repositories/MatchesRepository.php';
require_once __DIR__ . '/../repositories/MusicRepository.php';
require_once __DIR__ . '/../repositories/SwipesRepository.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';
require_once __DIR__ . '/../services/MatchScoringService.php';

class DiscoverController extends AppController {

    private MatchesRepository $matchesRepository;
    private MusicRepository $musicRepository;
    private MatchScoringService $matchScoringService;
    private SwipesRepository $swipesRepository;
    private UsersRepository $usersRepository;

    public function __construct() {
        $this->matchesRepository = new MatchesRepository();
        $this->musicRepository = new MusicRepository();
        $this->matchScoringService = new MatchScoringService();
        $this->swipesRepository = new SwipesRepository();
        $this->usersRepository = UsersRepository::getInstance();
    }

    public function index() {
        $this->requireCompletedOnboarding();

        $userId = (int) $_SESSION['user_id'];
        $bestMatch = $this->bestCandidateForUser($userId);
        $candidate = $bestMatch['candidate'];
        $musicPreview = ['top_artists' => [], 'top_tracks' => [], 'top_genres' => []];
        $scores = $bestMatch['scores'] ?? [
            'musicSync' => 0,
            'sharedTaste' => 0,
            'genreMatch' => 0,
            'vibeMatch' => 0,
        ];

        if ($candidate) {
            $candidate['age'] = $this->ageFromBirthDate($candidate['birth_date'] ?? null);
            $musicPreview = $this->musicRepository->getMusicPreviewForUser((int) $candidate['id']);
        }

        return $this->render('discover', [
            'userEmail'  => $_SESSION['user_email'],
            'activePage' => 'discover',
            'candidate' => $candidate,
            'musicPreview' => $musicPreview,
            'musicSync' => $scores['musicSync'],
            'sharedTaste' => $scores['sharedTaste'],
            'genreMatch' => $scores['genreMatch'],
            'vibeMatch' => $scores['vibeMatch'],
        ]);
    }

    public function swipe() {
        $this->requireCompletedOnboarding();
        $this->requireValidCsrfToken();

        $targetId = (int) ($_POST['target_id'] ?? 0);
        $direction = strtoupper(trim($_POST['direction'] ?? ''));

        if ($targetId > 0 && in_array($direction, ['LIKE', 'PASS'], true)) {
            $userId = (int) $_SESSION['user_id'];
            $this->swipesRepository->createSwipe($userId, $targetId, $direction);

            if ($direction === 'LIKE' && $this->swipesRepository->isMutualLike($userId, $targetId)) {
                $this->matchesRepository->createMatch($userId, $targetId);
            }
        }

        $this->redirect('/discover');
    }

    private function ageFromBirthDate(?string $birthDate): ?int {
        if (!$birthDate) {
            return null;
        }

        try {
            return (int) date_diff(date_create($birthDate), date_create('today'))->y;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function bestCandidateForUser(int $userId)
    {
        $candidates = $this->usersRepository->getDiscoverCandidatesForUser($userId, 50);
        $bestCandidate = null;
        $bestScores = null;
        $bestScore = -1;

        foreach ($candidates as $candidate) {
            $scores = $this->matchScoringService->compare($userId, (int) $candidate['id']);
            $score = $scores['musicSync'];

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestCandidate = $candidate;
                $bestScores = $scores;
            }
        }

        return [
            'candidate' => $bestCandidate,
            'scores' => $bestScores,
        ];
    }
}
