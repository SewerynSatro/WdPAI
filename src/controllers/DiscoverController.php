<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repositories/MatchesRepository.php';
require_once __DIR__ . '/../repositories/MusicRepository.php';
require_once __DIR__ . '/../repositories/SwipesRepository.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';

class DiscoverController extends AppController {

    private MatchesRepository $matchesRepository;
    private MusicRepository $musicRepository;
    private SwipesRepository $swipesRepository;
    private UsersRepository $usersRepository;

    public function __construct() {
        $this->matchesRepository = new MatchesRepository();
        $this->musicRepository = new MusicRepository();
        $this->swipesRepository = new SwipesRepository();
        $this->usersRepository = new UsersRepository();
    }

    public function index() {
        $this->requireCompletedOnboarding();

        $userId = (int) $_SESSION['user_id'];
        $candidate = $this->usersRepository->getDiscoverCandidateForUser($userId);
        $musicPreview = ['top_artists' => [], 'top_tracks' => [], 'top_genres' => []];
        $scores = [
            'musicSync' => 0,
            'sharedTaste' => 0,
            'genreMatch' => 0,
            'vibeMatch' => 0,
        ];

        if ($candidate) {
            $candidate['age'] = $this->ageFromBirthDate($candidate['birth_date'] ?? null);
            $musicPreview = $this->musicRepository->getMusicPreviewForUser((int) $candidate['id']);
            $scores = $this->calculateScores($userId, (int) $candidate['id']);
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

    private function calculateScores(int $userId, int $candidateId): array {
        $userArtists = array_column($this->musicRepository->getArtistsForUser($userId), 'artist_name');
        $candidateArtists = array_column($this->musicRepository->getArtistsForUser($candidateId), 'artist_name');
        $userTracks = array_column($this->musicRepository->getTopTracksForUser($userId), 'track_name');
        $candidateTracks = array_column($this->musicRepository->getTopTracksForUser($candidateId), 'track_name');
        $userGenres = array_slice(array_keys($this->musicRepository->getGenresForUser($userId)), 0, 5);
        $candidateGenres = array_slice(array_keys($this->musicRepository->getGenresForUser($candidateId)), 0, 5);

        $sharedTaste = $this->overlapPercent($userArtists, $candidateArtists);
        $genreMatch = $this->overlapPercent($userGenres, $candidateGenres);
        $vibeMatch = $this->overlapPercent($userTracks, $candidateTracks);
        $musicSync = (int) round(($sharedTaste * 0.4) + ($genreMatch * 0.35) + ($vibeMatch * 0.25));

        return [
            'musicSync' => $musicSync,
            'sharedTaste' => $sharedTaste,
            'genreMatch' => $genreMatch,
            'vibeMatch' => $vibeMatch,
        ];
    }

    private function overlapPercent(array $left, array $right): int {
        $left = array_unique(array_filter(array_map('strtolower', $left)));
        $right = array_unique(array_filter(array_map('strtolower', $right)));

        if (empty($left) || empty($right)) {
            return 0;
        }

        $shared = array_intersect($left, $right);
        $total = array_unique(array_merge($left, $right));

        return (int) round((count($shared) / count($total)) * 100);
    }
}
