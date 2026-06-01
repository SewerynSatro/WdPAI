<?php

require_once __DIR__ . '/../../Database.php';
require_once __DIR__ . '/../providers/SpotifyProvider.php';
require_once __DIR__ . '/../repositories/ProviderAccountsRepository.php';

class MusicSyncService {
    private const TIME_RANGES = ['short_term', 'medium_term', 'long_term'];

    private Database $database;
    private ProviderAccountsRepository $providerAccountsRepository;
    private SpotifyProvider $spotifyProvider;

    public function __construct()
    {
        $this->database = new Database();
        $this->providerAccountsRepository = new ProviderAccountsRepository();
        $this->spotifyProvider = new SpotifyProvider();
    }

    public function syncAllForUser(int $userId, string $providerKey = 'spotify'): array
    {
        if ($providerKey !== 'spotify') {
            return ['artists_synced' => 0, 'tracks_synced' => 0, 'genres_synced' => 0, 'recent_synced' => 0];
        }

        $account = $this->providerAccountsRepository->getByUserAndProvider($userId, $providerKey);
        if (!$account) {
            return ['artists_synced' => 0, 'tracks_synced' => 0, 'genres_synced' => 0, 'recent_synced' => 0];
        }

        $accessToken = $account['access_token'];
        $connection = $this->database->connect();
        $artistsSynced = 0;
        $tracksSynced = 0;
        $genresSynced = 0;
        $recentSynced = 0;

        $connection->beginTransaction();

        try {
            foreach (self::TIME_RANGES as $timeRange) {
                $artists = $this->spotifyProvider->fetchTopArtists($accessToken, 50, $timeRange);
                $tracks = $this->spotifyProvider->fetchTopTracks($accessToken, 50, $timeRange);

                $this->replaceArtists($connection, $userId, $providerKey, $timeRange, $artists);
                $this->replaceTracks($connection, $userId, $providerKey, $timeRange, $tracks);

                $artistsSynced += count($artists);
                $tracksSynced += count($tracks);
            }

            $genres = $this->spotifyProvider->fetchTopGenres($accessToken);
            if (empty($genres)) {
                $genres = $this->inferGenresFromLocalData($connection, $userId);
            }
            if (empty($genres)) {
                $genres = $this->inferGenresFromSyncedArtists($connection, $userId);
            }
            $genresSynced = $this->replaceGenres($connection, $userId, $providerKey, $genres);

            $recent = $this->spotifyProvider->fetchRecentlyPlayed($accessToken);
            $recentSynced = $this->replaceRecentPlays($connection, $userId, $providerKey, $recent);

            $this->replaceNowPlaying($connection, $userId, $this->spotifyProvider->fetchCurrentlyPlaying($accessToken));

            $connection->commit();
        } catch (Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        return [
            'artists_synced' => $artistsSynced,
            'tracks_synced' => $tracksSynced,
            'genres_synced' => $genresSynced,
            'recent_synced' => $recentSynced,
        ];
    }

    private function replaceArtists(PDO $connection, int $userId, string $providerKey, string $timeRange, array $artists): void
    {
        $connection->prepare(
            'DELETE FROM user_artists WHERE user_id = :user_id AND provider_key = :provider_key AND time_range = :time_range'
        )->execute(['user_id' => $userId, 'provider_key' => $providerKey, 'time_range' => $timeRange]);

        $insert = $connection->prepare(
            'INSERT INTO user_artists (user_id, provider_key, time_range, artist_id, artist_name, artist_image_url, rank)
             VALUES (:user_id, :provider_key, :time_range, :artist_id, :artist_name, :artist_image_url, :rank)'
        );

        foreach ($artists as $rank => $artist) {
            if (empty($artist['id']) || empty($artist['name'])) {
                continue;
            }

            $insert->execute([
                'user_id' => $userId,
                'provider_key' => $providerKey,
                'time_range' => $timeRange,
                'artist_id' => $artist['id'],
                'artist_name' => $artist['name'],
                'artist_image_url' => $artist['image'] ?? '',
                'rank' => $rank + 1,
            ]);
        }
    }

    private function replaceTracks(PDO $connection, int $userId, string $providerKey, string $timeRange, array $tracks): void
    {
        $connection->prepare(
            'DELETE FROM user_tracks WHERE user_id = :user_id AND provider_key = :provider_key AND time_range = :time_range'
        )->execute(['user_id' => $userId, 'provider_key' => $providerKey, 'time_range' => $timeRange]);

        $insert = $connection->prepare(
            'INSERT INTO user_tracks (user_id, provider_key, time_range, track_id, track_name, artist_name, album_name, album_image_url, duration_ms, spotify_url, rank)
             VALUES (:user_id, :provider_key, :time_range, :track_id, :track_name, :artist_name, :album_name, :album_image_url, :duration_ms, :spotify_url, :rank)'
        );

        foreach ($tracks as $rank => $track) {
            if (empty($track['id']) || empty($track['name'])) {
                continue;
            }

            $insert->execute([
                'user_id' => $userId,
                'provider_key' => $providerKey,
                'time_range' => $timeRange,
                'track_id' => $track['id'],
                'track_name' => $track['name'],
                'artist_name' => $track['artist'] ?? '',
                'album_name' => $track['album'] ?? '',
                'album_image_url' => $track['album_image'] ?? '',
                'duration_ms' => $track['duration_ms'] ?? 0,
                'spotify_url' => $track['spotify_url'] ?? '',
                'rank' => $rank + 1,
            ]);
        }
    }

    private function replaceGenres(PDO $connection, int $userId, string $providerKey, array $genres): int
    {
        $connection->prepare(
            'DELETE FROM user_genres WHERE user_id = :user_id AND provider_key = :provider_key'
        )->execute(['user_id' => $userId, 'provider_key' => $providerKey]);

        $maxWeight = !empty($genres) ? max(array_map('intval', array_values($genres))) : 0;

        $insert = $connection->prepare(
            'INSERT INTO user_genres (user_id, provider_key, genre, weight)
             VALUES (:user_id, :provider_key, :genre, :weight)'
        );

        foreach ($genres as $genre => $weight) {
            $normalizedWeight = $maxWeight > 0
                ? max(1, (int) round(((int) $weight / $maxWeight) * 10))
                : 1;

            $insert->execute([
                'user_id' => $userId,
                'provider_key' => $providerKey,
                'genre' => $genre,
                'weight' => $normalizedWeight,
            ]);
        }

        return count($genres);
    }

    private function inferGenresFromLocalData(PDO $connection, int $userId): array
    {
        $query = $connection->prepare(
            "
            SELECT ug.genre, SUM(ug.weight) AS score
            FROM user_artists current_artists
            JOIN user_artists known_artists
              ON LOWER(known_artists.artist_name) = LOWER(current_artists.artist_name)
             AND known_artists.user_id != current_artists.user_id
            JOIN user_genres ug ON ug.user_id = known_artists.user_id
            WHERE current_artists.user_id = :user_id
            GROUP BY ug.genre
            ORDER BY score DESC
            LIMIT 20
            "
        );
        $query->execute(['user_id' => $userId]);

        $genres = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $genres[$row['genre']] = max(1, (int) round((float) $row['score']));
        }

        return $genres;
    }

    private function inferGenresFromSyncedArtists(PDO $connection, int $userId): array
    {
        $query = $connection->prepare(
            "
            SELECT artist_name, time_range, rank
            FROM user_artists
            WHERE user_id = :user_id
            ORDER BY
                CASE time_range
                    WHEN 'short_term' THEN 1
                    WHEN 'medium_term' THEN 2
                    ELSE 3
                END,
                rank
            "
        );
        $query->execute(['user_id' => $userId]);

        $scores = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $artist) {
            foreach ($this->genresForArtistName($artist['artist_name'] ?? '') as $genre) {
                $rank = max(1, (int) ($artist['rank'] ?? 1));
                $weight = max(1, 12 - min($rank, 10));
                $scores[$genre] = ($scores[$genre] ?? 0) + $weight;
            }
        }

        arsort($scores);
        return array_slice($scores, 0, 12, true);
    }

    private function genresForArtistName(string $artistName): array
    {
        $name = strtolower($artistName);
        $knownArtists = [
            'arctic monkeys' => ['indie rock', 'alternative rock'],
            'tame impala' => ['psychedelic pop', 'indie rock'],
            'radiohead' => ['alternative rock', 'art rock'],
            'the strokes' => ['indie rock', 'garage rock'],
            'the killers' => ['alternative rock', 'indie rock'],
            'vampire weekend' => ['indie pop', 'indie rock'],
            'mgmt' => ['indie pop', 'psychedelic pop'],
            'frank ocean' => ['r&b', 'neo soul'],
            'kendrick lamar' => ['hip hop', 'rap'],
            'drake' => ['hip hop', 'rap'],
            'kanye west' => ['hip hop', 'rap'],
            'taylor swift' => ['pop', 'singer-songwriter'],
            'billie eilish' => ['pop', 'alt pop'],
            'dua lipa' => ['pop', 'dance pop'],
            'the weeknd' => ['pop', 'r&b'],
            'daft punk' => ['electronic', 'house'],
            'calvin harris' => ['edm', 'dance pop'],
            'avicii' => ['edm', 'progressive house'],
            'metallica' => ['metal', 'hard rock'],
            'nirvana' => ['grunge', 'alternative rock'],
            'queen' => ['classic rock', 'rock'],
            'pink floyd' => ['progressive rock', 'classic rock'],
            'the beatles' => ['classic rock', 'rock'],
            'miles davis' => ['jazz'],
        ];

        foreach ($knownArtists as $artist => $genres) {
            if (str_contains($name, $artist)) {
                return $genres;
            }
        }

        $patterns = [
            'dj ' => ['electronic'],
            'orchestra' => ['classical'],
            'quartet' => ['jazz'],
            'band' => ['rock'],
            'choir' => ['classical'],
        ];

        foreach ($patterns as $pattern => $genres) {
            if (str_contains($name, $pattern)) {
                return $genres;
            }
        }

        return ['pop'];
    }

    private function replaceRecentPlays(PDO $connection, int $userId, string $providerKey, array $tracks): int
    {
        $connection->prepare(
            'DELETE FROM user_recent_plays WHERE user_id = :user_id AND provider_key = :provider_key'
        )->execute(['user_id' => $userId, 'provider_key' => $providerKey]);

        $insert = $connection->prepare(
            'INSERT INTO user_recent_plays (user_id, provider_key, track_id, track_name, artist_name, album_name, album_image_url, played_at)
             VALUES (:user_id, :provider_key, :track_id, :track_name, :artist_name, :album_name, :album_image_url, :played_at)'
        );

        $count = 0;
        foreach ($tracks as $track) {
            if (empty($track['id']) || empty($track['played_at'])) {
                continue;
            }

            $insert->execute([
                'user_id' => $userId,
                'provider_key' => $providerKey,
                'track_id' => $track['id'],
                'track_name' => $track['name'] ?? '',
                'artist_name' => $track['artist'] ?? '',
                'album_name' => $track['album'] ?? '',
                'album_image_url' => $track['album_image'] ?? '',
                'played_at' => $track['played_at'],
            ]);
            $count++;
        }

        return $count;
    }

    private function replaceNowPlaying(PDO $connection, int $userId, ?array $track): void
    {
        $connection->prepare('DELETE FROM user_now_playing WHERE user_id = :user_id')
            ->execute(['user_id' => $userId]);

        if (!$track) {
            return;
        }

        $connection->prepare(
            'INSERT INTO user_now_playing (user_id, track_id, track_name, artist_name, album_image_url, spotify_url, is_playing)
             VALUES (:user_id, :track_id, :track_name, :artist_name, :album_image_url, :spotify_url, :is_playing)'
        )->execute([
            'user_id' => $userId,
            'track_id' => $track['track_id'] ?? '',
            'track_name' => $track['track_name'] ?? '',
            'artist_name' => $track['artist_name'] ?? '',
            'album_image_url' => $track['album_image'] ?? '',
            'spotify_url' => $track['spotify_url'] ?? '',
            'is_playing' => !empty($track['is_playing']) ? 'true' : 'false',
        ]);
    }
}
