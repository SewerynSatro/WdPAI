<?php

require_once 'Repository.php';

class UsersRepository extends Repository {
    private static ?UsersRepository $instance = null;

    private function __construct()
    {
        parent::__construct();
    }

    public static function getInstance(): UsersRepository
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getUserById(int $id)
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT * FROM users WHERE id = :id
            "
        );
        $query->bindParam(':id', $id, PDO::PARAM_INT);
        $query->execute();

        return $query->fetch(PDO::FETCH_ASSOC);
    }

    public function getUsers(): ?array 
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT * FROM users;
            "
        );
        $query->execute();

        $users = $query->fetchAll(PDO::FETCH_ASSOC);
        return $users;
    }

    public function getDiscoverCandidateForUser(int $userId)
    {
        $query = $this->database->connect()->prepare(
            "
            WITH current_top_genres AS (
                SELECT genre
                FROM user_genres
                WHERE user_id = :current_genres_user_id
                ORDER BY weight DESC
                LIMIT 5
            ),
            current_top_artists AS (
                SELECT LOWER(artist_name) AS artist_name
                FROM user_artists
                WHERE user_id = :current_artists_user_id
                  AND time_range = 'medium_term'
                ORDER BY rank
                LIMIT 10
            ),
            current_top_tracks AS (
                SELECT LOWER(track_name) AS track_name
                FROM user_tracks
                WHERE user_id = :current_tracks_user_id
                  AND time_range = 'medium_term'
                ORDER BY rank
                LIMIT 10
            )
            SELECT
                id,
                email,
                display_name,
                bio,
                birth_date,
                gender,
                looking_for,
                instagram_handle,
                facebook_handle,
                spotify_handle,
                distance_km
            FROM (
                SELECT
                    u.id,
                    u.email,
                    COALESCE(u.display_name, u.firstname) AS display_name,
                    up.bio,
                    up.birth_date,
                    up.gender,
                    up.looking_for,
                    up.instagram_handle,
                    up.facebook_handle,
                    up.spotify_handle,
                    current_profile.looking_for AS current_looking_for,
                    current_profile.latitude AS current_latitude,
                    current_profile.longitude AS current_longitude,
                    COALESCE(current_profile.max_distance_km, 50) AS current_max_distance_km,
                    (
                        SELECT COUNT(*)
                        FROM (
                            SELECT genre
                            FROM user_genres
                            WHERE user_id = u.id
                            ORDER BY weight DESC
                            LIMIT 5
                        ) candidate_genres
                        WHERE candidate_genres.genre IN (SELECT genre FROM current_top_genres)
                    ) AS genre_overlap,
                    (
                        SELECT COUNT(*)
                        FROM (
                            SELECT LOWER(artist_name) AS artist_name
                            FROM user_artists
                            WHERE user_id = u.id
                              AND time_range = 'medium_term'
                            ORDER BY rank
                            LIMIT 10
                        ) candidate_artists
                        WHERE candidate_artists.artist_name IN (SELECT artist_name FROM current_top_artists)
                    ) AS artist_overlap,
                    (
                        SELECT COUNT(*)
                        FROM (
                            SELECT LOWER(track_name) AS track_name
                            FROM user_tracks
                            WHERE user_id = u.id
                              AND time_range = 'medium_term'
                            ORDER BY rank
                            LIMIT 10
                        ) candidate_tracks
                        WHERE candidate_tracks.track_name IN (SELECT track_name FROM current_top_tracks)
                    ) AS track_overlap,
                    CASE
                        WHEN current_profile.latitude IS NOT NULL
                         AND current_profile.longitude IS NOT NULL
                         AND up.latitude IS NOT NULL
                         AND up.longitude IS NOT NULL
                        THEN ROUND((
                            6371 * ACOS(LEAST(1, GREATEST(-1,
                                COS(RADIANS(current_profile.latitude))
                                * COS(RADIANS(up.latitude))
                                * COS(RADIANS(up.longitude) - RADIANS(current_profile.longitude))
                                + SIN(RADIANS(current_profile.latitude))
                                * SIN(RADIANS(up.latitude))
                            )))
                        )::numeric, 1)
                        ELSE NULL
                    END AS distance_km
                FROM users u
                LEFT JOIN user_profiles up ON up.user_id = u.id
                LEFT JOIN user_profiles current_profile ON current_profile.user_id = :current_user_id
                WHERE u.id != :user_id
                  AND u.is_active = TRUE
                  AND COALESCE(up.onboarding_completed, FALSE) = TRUE
                  AND u.id NOT IN (
                      SELECT target_id FROM swipes WHERE swiper_id = :swiper_id
                  )
            ) candidates
            WHERE (
                current_latitude IS NULL
                OR current_longitude IS NULL
                OR distance_km <= current_max_distance_km
            )
            AND (
                current_looking_for IS NULL
                OR current_looking_for = ''
                OR current_looking_for = 'everyone'
                OR gender = current_looking_for
            )
            ORDER BY
                ((artist_overlap * 4) + (genre_overlap * 3) + (track_overlap * 2)) DESC,
                distance_km ASC NULLS LAST,
                RANDOM()
            LIMIT 1
            "
        );
        $query->execute([
            'current_genres_user_id' => $userId,
            'current_artists_user_id' => $userId,
            'current_tracks_user_id' => $userId,
            'current_user_id' => $userId,
            'user_id' => $userId,
            'swiper_id' => $userId,
        ]);

        return $query->fetch(PDO::FETCH_ASSOC) ?: null;
    }

  public function getUserByEmail(string $email) {
        $query = $this->database->connect()->prepare(
            "
            SELECT * FROM users WHERE email = :email
            "
        );
        $query->bindParam(':email', $email);
        $query->execute();

        $user = $query->fetch(PDO::FETCH_ASSOC);
        return $user;
    }

    public function emailExistsForOtherUser(string $email, int $userId): bool
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT 1 FROM users
            WHERE email = :email AND id != :id
            "
        );
        $query->execute([
            'email' => $email,
            'id' => $userId,
        ]);

        return (bool) $query->fetchColumn();
    }

    public function createUser(
        string $email,
        string $hashedPassword,
        string $displayName,
    ) {
        $displayName = trim($displayName);

        $query = $this->database->connect()->prepare(
            "
            INSERT INTO users (firstname, email, password, display_name)
            VALUES (?, ?, ?, ?);
            "
        );
        $query->execute([
            $displayName,
            $email, 
            $hashedPassword,
            $displayName,
        ]);
    }

    public function updateAccount(
        int $userId,
        string $email,
        string $displayName,
        ?string $hashedPassword = null
    ): void {
        $displayName = trim($displayName);

        $params = [
            'id' => $userId,
            'email' => $email,
            'display_name' => $displayName,
            'firstname' => $displayName,
        ];
        $passwordSql = '';

        if ($hashedPassword !== null) {
            $passwordSql = ', password = :password';
            $params['password'] = $hashedPassword;
        }

        $query = $this->database->connect()->prepare(
            "
            UPDATE users
            SET email = :email,
                display_name = :display_name,
                firstname = :firstname,
                updated_at = CURRENT_TIMESTAMP
                {$passwordSql}
            WHERE id = :id
            "
        );
        $query->execute($params);
    }
}
