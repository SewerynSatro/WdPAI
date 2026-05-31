<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repositories/MatchesRepository.php';
require_once __DIR__ . '/../repositories/MusicRepository.php';

class MatchesController extends AppController {

    private MatchesRepository $matchesRepository;
    private MusicRepository $musicRepository;

    public function __construct() {
        $this->matchesRepository = new MatchesRepository();
        $this->musicRepository = new MusicRepository();
    }

    public function index() {
        session_start();

        if (!isset($_SESSION['user_id'])) {
            $url = "http://$_SERVER[HTTP_HOST]";
            header("Location: {$url}/login");
            exit();
        }

        $userId = (int) $_SESSION['user_id'];
        $matches = $this->matchesRepository->getMatchesByUserId($userId);

        foreach ($matches as &$match) {
            $match['music'] = $this->musicRepository->getMusicPreviewForUser((int) $match['partner_id']);
            $match['partner_age'] = $this->ageFromBirthDate($match['partner_birth_date'] ?? null);
        }

        return $this->render('matches', [
            'userEmail'  => $_SESSION['user_email'],
            'activePage' => 'matches',
            'matches' => $matches,
        ]);
    }

    public function show(int $id) {
        session_start();

        if (!isset($_SESSION['user_id'])) {
            $url = "http://$_SERVER[HTTP_HOST]";
            header("Location: {$url}/login");
            exit();
        }

        $userId = (int) $_SESSION['user_id'];
        $partner = $this->matchesRepository->getMatchPartner($userId, $id);

        if (!$partner) {
            include 'public/views/404.html';
            return;
        }

        $musicPreview = $this->musicRepository->getMusicPreviewForUser((int) $partner['partner_id']);
        $scores = $this->calculateScores($userId, (int) $partner['partner_id']);
        $partner['partner_age'] = $this->ageFromBirthDate($partner['partner_birth_date'] ?? null);

        return $this->render('matches-view', [
            'userEmail'  => $_SESSION['user_email'],
            'activePage' => 'matches',
            'partnerId'  => $id,
            'partner' => $partner,
            'musicPreview' => $musicPreview,
            'musicSync' => $scores['musicSync'],
            'sharedTaste' => $scores['sharedTaste'],
            'genreMatch' => $scores['genreMatch'],
            'vibeMatch' => $scores['vibeMatch'],
        ]);
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

    private function calculateScores(int $userId, int $partnerId): array {
        $userArtists = array_column($this->musicRepository->getArtistsForUser($userId), 'artist_name');
        $partnerArtists = array_column($this->musicRepository->getArtistsForUser($partnerId), 'artist_name');
        $userTracks = array_column($this->musicRepository->getTopTracksForUser($userId), 'track_name');
        $partnerTracks = array_column($this->musicRepository->getTopTracksForUser($partnerId), 'track_name');
        $userGenres = array_keys($this->musicRepository->getGenresForUser($userId));
        $partnerGenres = array_keys($this->musicRepository->getGenresForUser($partnerId));

        $sharedTaste = $this->overlapPercent($userArtists, $partnerArtists);
        $genreMatch = $this->overlapPercent($userGenres, $partnerGenres);
        $vibeMatch = $this->overlapPercent($userTracks, $partnerTracks);
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
