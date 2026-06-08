<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repositories/MatchesRepository.php';
require_once __DIR__ . '/../repositories/MusicRepository.php';
require_once __DIR__ . '/../services/MatchScoringService.php';

class MatchesController extends AppController {

    private MatchesRepository $matchesRepository;
    private MatchScoringService $matchScoringService;
    private MusicRepository $musicRepository;

    public function __construct() {
        $this->matchesRepository = new MatchesRepository();
        $this->matchScoringService = new MatchScoringService();
        $this->musicRepository = new MusicRepository();
    }

    public function index() {
        $this->requireCompletedOnboarding();

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
        $this->requireCompletedOnboarding();

        $userId = (int) $_SESSION['user_id'];
        $partner = $this->matchesRepository->getMatchPartner($userId, $id);

        if (!$partner) {
            include 'public/views/404.html';
            return;
        }

        $musicPreview = $this->musicRepository->getMusicPreviewForUser((int) $partner['partner_id']);
        $scores = $this->matchScoringService->compare($userId, (int) $partner['partner_id']);
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

}
