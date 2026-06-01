<?php

require_once 'Repository.php';

class MusicRepository extends Repository {

    public function getArtistsForUser(int $userId, string $timeRange = 'medium_term'): array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT artist_name, artist_image_url, artist_id, rank
            FROM user_artists
            WHERE user_id = :user_id AND time_range = :time_range
            ORDER BY rank
            "
        );
        $query->execute([
            'user_id' => $userId,
            'time_range' => $timeRange,
        ]);

        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTopTracksForUser(int $userId, string $timeRange = 'medium_term'): array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT
                track_name,
                artist_name,
                album_name,
                album_image_url,
                duration_ms,
                spotify_url,
                track_id,
                rank
            FROM user_tracks
            WHERE user_id = :user_id AND time_range = :time_range
            ORDER BY rank
            "
        );
        $query->execute([
            'user_id' => $userId,
            'time_range' => $timeRange,
        ]);

        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getGenresForUser(int $userId): array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT genre, weight
            FROM user_genres
            WHERE user_id = :user_id
            ORDER BY weight DESC
            "
        );
        $query->execute(['user_id' => $userId]);

        $genres = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $genres[$row['genre']] = (int) $row['weight'];
        }

        return $genres;
    }

    public function getRecentPlaysForUser(int $userId): array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT track_name, artist_name, album_name, album_image_url, played_at
            FROM user_recent_plays
            WHERE user_id = :user_id
            ORDER BY played_at DESC
            LIMIT 30
            "
        );
        $query->execute(['user_id' => $userId]);

        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getNowPlayingForUser(int $userId)
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT * FROM user_now_playing
            WHERE user_id = :user_id
            "
        );
        $query->execute(['user_id' => $userId]);

        return $query->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getMusicPreviewForUser(int $userId): array
    {
        $genres = $this->getGenresForUser($userId);

        return [
            'top_artists' => array_slice($this->getArtistsForUser($userId, 'medium_term'), 0, 5),
            'top_tracks' => array_slice($this->getTopTracksForUser($userId, 'medium_term'), 0, 3),
            'short_term_tracks' => array_slice($this->getTopTracksForUser($userId, 'short_term'), 0, 3),
            'medium_term_tracks' => array_slice($this->getTopTracksForUser($userId, 'medium_term'), 0, 3),
            'long_term_tracks' => array_slice($this->getTopTracksForUser($userId, 'long_term'), 0, 3),
            'recent_plays' => array_slice($this->getRecentPlaysForUser($userId), 0, 3),
            'top_genres' => array_slice(array_keys($genres), 0, 3),
        ];
    }
}
