<?php

require_once 'Repository.php';

class UsersRepository extends Repository {

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
              AND (
                  current_profile.latitude IS NULL
                  OR current_profile.longitude IS NULL
                  OR (
                      up.latitude IS NOT NULL
                      AND up.longitude IS NOT NULL
                      AND (
                          6371 * ACOS(LEAST(1, GREATEST(-1,
                              COS(RADIANS(current_profile.latitude))
                              * COS(RADIANS(up.latitude))
                              * COS(RADIANS(up.longitude) - RADIANS(current_profile.longitude))
                              + SIN(RADIANS(current_profile.latitude))
                              * SIN(RADIANS(up.latitude))
                          )))
                      ) <= COALESCE(current_profile.max_distance_km, 50)
                  )
              )
            ORDER BY RANDOM()
            LIMIT 1
            "
        );
        $query->execute([
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
