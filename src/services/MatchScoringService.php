<?php

require_once __DIR__ . '/../repositories/MusicRepository.php';

class MatchScoringService
{
    private const W_SHARED = 0.40;
    private const W_GENRE = 0.35;
    private const W_VIBE = 0.25;

    private const TIME_RANGE_WEIGHTS = [
        'short_term' => 1.5,
        'medium_term' => 1.0,
        'long_term' => 0.6,
    ];

    private MusicRepository $musicRepository;
    private array $allArtistsCache = [];
    private array $allTracksCache = [];
    private array $genresCache = [];
    private array $recentPlaysCache = [];
    private array $nowPlayingCache = [];

    public function __construct()
    {
        $this->musicRepository = new MusicRepository();
    }

    public function compare(int $userId, int $candidateId): array
    {
        $shared = $this->sharedTasteScore($userId, $candidateId);
        $genre = $this->genreMatchScore($userId, $candidateId);
        $vibe = $this->vibeMatchScore($userId, $candidateId);

        $musicSync = (int) round(
            ($shared['score'] * self::W_SHARED)
            + ($genre['score'] * self::W_GENRE)
            + ($vibe['score'] * self::W_VIBE)
        );

        return [
            'musicSync' => max(0, min(100, $musicSync)),
            'sharedTaste' => (int) round($shared['score']),
            'genreMatch' => (int) round($genre['score']),
            'vibeMatch' => (int) round($vibe['score']),
            'sharedArtists' => $shared['shared_artists'],
            'sharedTracks' => $shared['shared_tracks'],
            'sharedGenres' => $genre['shared_genres'],
        ];
    }

    private function sharedTasteScore(int $userId, int $candidateId): array
    {
        $artistScore = $this->weightedOverlap(
            $this->rankedItems($this->getAllArtistsForUser($userId), 'artist_id'),
            $this->rankedItems($this->getAllArtistsForUser($candidateId), 'artist_id')
        );
        $trackScore = $this->weightedOverlap(
            $this->rankedItems($this->getAllTracksForUser($userId), 'track_id'),
            $this->rankedItems($this->getAllTracksForUser($candidateId), 'track_id')
        );

        $score = ($artistScore['pct'] * 0.55) + ($trackScore['pct'] * 0.45);
        $sharedCount = count($artistScore['shared']) + count($trackScore['shared']);

        if ($sharedCount > 0 && $score < 15) {
            $score = 15 + min($sharedCount * 3, 20);
        }

        return [
            'score' => min(100, $score),
            'shared_artists' => $artistScore['shared'],
            'shared_tracks' => $trackScore['shared'],
        ];
    }

    private function genreMatchScore(int $userId, int $candidateId): array
    {
        $userGenres = $this->getGenresForUser($userId);
        $candidateGenres = $this->getGenresForUser($candidateId);

        if (empty($userGenres) || empty($candidateGenres)) {
            return ['score' => 0.0, 'shared_genres' => []];
        }

        $allGenres = array_unique(array_merge(array_keys($userGenres), array_keys($candidateGenres)));
        $dot = 0.0;
        $userMagnitude = 0.0;
        $candidateMagnitude = 0.0;
        $shared = [];

        foreach ($allGenres as $genre) {
            $userWeight = (float) ($userGenres[$genre] ?? 0);
            $candidateWeight = (float) ($candidateGenres[$genre] ?? 0);

            $dot += $userWeight * $candidateWeight;
            $userMagnitude += $userWeight * $userWeight;
            $candidateMagnitude += $candidateWeight * $candidateWeight;

            if ($userWeight > 0 && $candidateWeight > 0) {
                $shared[$genre] = $userWeight + $candidateWeight;
            }
        }

        $magnitude = sqrt($userMagnitude) * sqrt($candidateMagnitude);
        arsort($shared);

        return [
            'score' => $magnitude > 0 ? ($dot / $magnitude) * 100 : 0.0,
            'shared_genres' => array_slice(array_keys($shared), 0, 5),
        ];
    }

    private function vibeMatchScore(int $userId, int $candidateId): array
    {
        $userRecentPlays = $this->getRecentPlaysForUser($userId);
        $candidateRecentPlays = $this->getRecentPlaysForUser($candidateId);
        $recentTracks = $this->jaccardScaled(
            array_column($userRecentPlays, 'track_id'),
            array_column($candidateRecentPlays, 'track_id'),
            3.0
        );
        $recentArtists = $this->jaccardScaled(
            array_map('strtolower', array_column($userRecentPlays, 'artist_name')),
            array_map('strtolower', array_column($candidateRecentPlays, 'artist_name')),
            2.0
        );
        $nowBonus = $this->nowPlayingBonus($userId, $candidateId);

        return [
            'score' => min(100, ($recentTracks * 0.5) + ($recentArtists * 0.5) + $nowBonus),
        ];
    }

    private function weightedOverlap(array $left, array $right): array
    {
        if (empty($left) || empty($right)) {
            return ['pct' => 0.0, 'shared' => []];
        }

        $intersection = 0.0;
        $union = 0.0;
        $shared = [];
        $allIds = array_unique(array_merge(array_keys($left), array_keys($right)));

        foreach ($allIds as $id) {
            $leftWeight = $left[$id]['weight'] ?? 0.0;
            $rightWeight = $right[$id]['weight'] ?? 0.0;
            $intersection += min($leftWeight, $rightWeight);
            $union += max($leftWeight, $rightWeight);

            if ($leftWeight > 0 && $rightWeight > 0) {
                $shared[$left[$id]['label'] ?? $right[$id]['label'] ?? $id] = min($leftWeight, $rightWeight);
            }
        }

        arsort($shared);
        $raw = $union > 0 ? ($intersection / $union) * 100 : 0.0;

        return [
            'pct' => $this->scaleScore($raw, 2.5),
            'shared' => array_slice(array_keys($shared), 0, 5),
        ];
    }

    private function rankedItems(array $rows, string $idColumn): array
    {
        $items = [];

        foreach ($rows as $row) {
            $id = strtolower((string) ($row[$idColumn] ?? ''));
            if ($id === '') {
                continue;
            }

            $rank = max(1, (int) ($row['rank'] ?? 1));
            $timeRange = $row['time_range'] ?? 'medium_term';
            $timeWeight = self::TIME_RANGE_WEIGHTS[$timeRange] ?? 1.0;
            $weight = $timeWeight / sqrt($rank);
            $label = $row['artist_name'] ?? $row['track_name'] ?? $id;

            if (!isset($items[$id]) || $items[$id]['weight'] < $weight) {
                $items[$id] = [
                    'weight' => $weight,
                    'label' => $label,
                ];
            }
        }

        return $items;
    }

    private function jaccardScaled(array $left, array $right, float $factor): float
    {
        $left = array_unique(array_filter($left));
        $right = array_unique(array_filter($right));

        if (empty($left) || empty($right)) {
            return 0.0;
        }

        $intersection = count(array_intersect($left, $right));
        $union = count(array_unique(array_merge($left, $right)));
        $raw = $union > 0 ? ($intersection / $union) * 100 : 0.0;

        return $this->scaleScore($raw, $factor);
    }

    private function nowPlayingBonus(int $userId, int $candidateId): int
    {
        $userNow = $this->getNowPlayingForUser($userId);
        $candidateNow = $this->getNowPlayingForUser($candidateId);

        if (
            !$userNow
            || !$candidateNow
            || !$this->isTruthy($userNow['is_playing'])
            || !$this->isTruthy($candidateNow['is_playing'])
            || empty($userNow['track_id'])
            || empty($candidateNow['track_id'])
        ) {
            return 0;
        }

        return $userNow['track_id'] === $candidateNow['track_id'] ? 20 : 0;
    }

    private function isTruthy($value): bool
    {
        return in_array($value, [true, 1, '1', 't', 'true'], true);
    }

    private function getAllArtistsForUser(int $userId): array
    {
        return $this->allArtistsCache[$userId] ??= $this->musicRepository->getAllArtistsForUser($userId);
    }

    private function getAllTracksForUser(int $userId): array
    {
        return $this->allTracksCache[$userId] ??= $this->musicRepository->getAllTracksForUser($userId);
    }

    private function getGenresForUser(int $userId): array
    {
        return $this->genresCache[$userId] ??= $this->musicRepository->getGenresForUser($userId);
    }

    private function getRecentPlaysForUser(int $userId): array
    {
        return $this->recentPlaysCache[$userId] ??= $this->musicRepository->getRecentPlaysForUser($userId);
    }

    private function getNowPlayingForUser(int $userId)
    {
        if (!array_key_exists($userId, $this->nowPlayingCache)) {
            $this->nowPlayingCache[$userId] = $this->musicRepository->getNowPlayingForUser($userId);
        }

        return $this->nowPlayingCache[$userId];
    }

    private function scaleScore(float $raw, float $factor): float
    {
        $normalized = max(0, min(100, $raw)) / 100;
        return (1 - pow(1 - $normalized, $factor)) * 100;
    }
}
