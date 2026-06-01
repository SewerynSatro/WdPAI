<?php

require_once __DIR__ . '/../../config.php';

class SpotifyProvider {
    private const AUTH_URL = 'https://accounts.spotify.com/authorize';
    private const TOKEN_URL = 'https://accounts.spotify.com/api/token';
    private const API_BASE = 'https://api.spotify.com/v1';

    public function getAuthorizationUrl(string $state): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id' => SPOTIFY_CLIENT_ID,
            'response_type' => 'code',
            'redirect_uri' => SPOTIFY_REDIRECT_URI,
            'state' => $state,
            'scope' => 'user-top-read user-read-recently-played user-read-private user-library-read user-follow-read user-read-currently-playing user-read-playback-state',
            'show_dialog' => 'true',
        ]);
    }

    public function exchangeAuthorizationCode(string $code): array
    {
        $response = $this->httpPost(self::TOKEN_URL, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => SPOTIFY_REDIRECT_URI,
        ]);

        return [
            'access_token' => $response['access_token'] ?? '',
            'refresh_token' => $response['refresh_token'] ?? '',
            'expires_in' => (int) ($response['expires_in'] ?? 3600),
        ];
    }

    public function refreshAccessToken(string $refreshToken): array
    {
        $response = $this->httpPost(self::TOKEN_URL, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);

        return [
            'access_token' => $response['access_token'] ?? '',
            'expires_in' => (int) ($response['expires_in'] ?? 3600),
        ];
    }

    public function fetchTopArtists(string $accessToken, int $limit = 50, string $timeRange = 'medium_term'): array
    {
        $data = $this->httpGet(self::API_BASE . '/me/top/artists?' . http_build_query([
            'time_range' => $timeRange,
            'limit' => min($limit, 50),
        ]), $accessToken);

        $artists = [];
        foreach ($data['items'] ?? [] as $item) {
            $images = $item['images'] ?? [];
            $artists[] = [
                'id' => $item['id'] ?? '',
                'name' => $item['name'] ?? '',
                'genres' => $item['genres'] ?? [],
                'image' => $images[0]['url'] ?? '',
            ];
        }

        return $artists;
    }

    public function fetchTopTracks(string $accessToken, int $limit = 50, string $timeRange = 'medium_term'): array
    {
        $data = $this->httpGet(self::API_BASE . '/me/top/tracks?' . http_build_query([
            'time_range' => $timeRange,
            'limit' => min($limit, 50),
        ]), $accessToken);

        $tracks = [];
        foreach ($data['items'] ?? [] as $item) {
            $artists = array_map(fn($artist) => $artist['name'] ?? '', $item['artists'] ?? []);
            $images = $item['album']['images'] ?? [];
            $tracks[] = [
                'id' => $item['id'] ?? '',
                'name' => $item['name'] ?? '',
                'artist' => implode(', ', array_filter($artists)),
                'album' => $item['album']['name'] ?? '',
                'album_image' => $images[0]['url'] ?? '',
                'duration_ms' => (int) ($item['duration_ms'] ?? 0),
                'spotify_url' => $item['external_urls']['spotify'] ?? '',
            ];
        }

        return $tracks;
    }

    public function fetchTopGenres(string $accessToken): array
    {
        $artists = $this->fetchTopArtists($accessToken, 50, 'medium_term');
        $genres = [];

        foreach ($artists as $artist) {
            foreach ($artist['genres'] as $genre) {
                $genres[$genre] = ($genres[$genre] ?? 0) + 1;
            }
        }

        arsort($genres);
        return array_slice($genres, 0, 20, true);
    }

    public function fetchRecentlyPlayed(string $accessToken, int $limit = 50): array
    {
        $data = $this->httpGet(self::API_BASE . '/me/player/recently-played?' . http_build_query([
            'limit' => min($limit, 50),
        ]), $accessToken);

        $tracks = [];
        foreach ($data['items'] ?? [] as $item) {
            $track = $item['track'] ?? [];
            $artists = array_map(fn($artist) => $artist['name'] ?? '', $track['artists'] ?? []);
            $images = $track['album']['images'] ?? [];
            $tracks[] = [
                'id' => $track['id'] ?? '',
                'name' => $track['name'] ?? '',
                'artist' => implode(', ', array_filter($artists)),
                'album' => $track['album']['name'] ?? '',
                'album_image' => $images[0]['url'] ?? '',
                'played_at' => $item['played_at'] ?? '',
            ];
        }

        return $tracks;
    }

    public function fetchCurrentlyPlaying(string $accessToken): ?array
    {
        $data = $this->httpGet(self::API_BASE . '/me/player/currently-playing', $accessToken);

        if (empty($data['item'])) {
            return null;
        }

        $track = $data['item'];
        $artists = array_map(fn($artist) => $artist['name'] ?? '', $track['artists'] ?? []);
        $images = $track['album']['images'] ?? [];

        return [
            'is_playing' => (bool) ($data['is_playing'] ?? false),
            'track_id' => $track['id'] ?? '',
            'track_name' => $track['name'] ?? '',
            'artist_name' => implode(', ', array_filter($artists)),
            'album_image' => $images[0]['url'] ?? '',
            'spotify_url' => $track['external_urls']['spotify'] ?? '',
        ];
    }

    private function httpPost(string $url, array $data): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Authorization: Basic ' . base64_encode(SPOTIFY_CLIENT_ID . ':' . SPOTIFY_CLIENT_SECRET),
                    'Content-Type: application/x-www-form-urlencoded',
                ],
                'content' => http_build_query($data),
                'ignore_errors' => true,
            ],
        ]);

        $response = file_get_contents($url, false, $context);
        return json_decode((string) $response, true) ?: [];
    }

    private function httpGet(string $url, string $accessToken): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => ['Authorization: Bearer ' . $accessToken],
                'ignore_errors' => true,
            ],
        ]);

        $response = file_get_contents($url, false, $context);
        return json_decode((string) $response, true) ?: [];
    }
}
