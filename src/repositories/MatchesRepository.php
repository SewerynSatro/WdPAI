<?php

require_once 'Repository.php';

class MatchesRepository extends Repository {

    public function createMatch(int $userA, int $userB): int
    {
        $firstUserId = min($userA, $userB);
        $secondUserId = max($userA, $userB);

        $query = $this->database->connect()->prepare(
            "
            INSERT INTO matches (user_a_id, user_b_id)
            VALUES (:user_a_id, :user_b_id)
            ON CONFLICT (user_a_id, user_b_id) DO NOTHING
            RETURNING id
            "
        );
        $query->execute([
            'user_a_id' => $firstUserId,
            'user_b_id' => $secondUserId,
        ]);

        return (int) ($query->fetchColumn() ?: 0);
    }

    public function getMatchesByUserId(int $userId): array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT
                m.id AS match_id,
                m.matched_at,
                CASE
                    WHEN m.user_a_id = :user_id_1 THEN m.user_b_id
                    ELSE m.user_a_id
                END AS partner_id,
                COALESCE(u.display_name, u.firstname) AS partner_name,
                up.city AS partner_city,
                up.birth_date AS partner_birth_date,
                up.bio AS partner_bio,
                up.instagram_handle,
                up.facebook_handle,
                up.spotify_handle
            FROM matches m
            JOIN users u ON u.id = CASE
                WHEN m.user_a_id = :user_id_2 THEN m.user_b_id
                ELSE m.user_a_id
            END
            LEFT JOIN user_profiles up ON up.user_id = u.id
            WHERE m.user_a_id = :user_id_3 OR m.user_b_id = :user_id_4
            ORDER BY m.matched_at DESC
            "
        );
        $query->execute([
            'user_id_1' => $userId,
            'user_id_2' => $userId,
            'user_id_3' => $userId,
            'user_id_4' => $userId,
        ]);

        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMatchPartner(int $userId, int $partnerId)
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT
                m.id AS match_id,
                m.matched_at,
                u.id AS partner_id,
                COALESCE(u.display_name, u.firstname) AS partner_name,
                u.email AS partner_email,
                up.bio AS partner_bio,
                up.city AS partner_city,
                up.birth_date AS partner_birth_date,
                up.gender,
                up.looking_for,
                up.instagram_handle,
                up.facebook_handle,
                up.spotify_handle
            FROM matches m
            JOIN users u ON u.id = :partner_id
            LEFT JOIN user_profiles up ON up.user_id = u.id
            WHERE (
                (m.user_a_id = :user_id_1 AND m.user_b_id = :partner_id_1)
                OR
                (m.user_a_id = :partner_id_2 AND m.user_b_id = :user_id_2)
            )
            LIMIT 1
            "
        );
        $query->execute([
            'partner_id' => $partnerId,
            'user_id_1' => $userId,
            'partner_id_1' => $partnerId,
            'partner_id_2' => $partnerId,
            'user_id_2' => $userId,
        ]);

        return $query->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function areMatched(int $userId, int $partnerId): bool
    {
        $firstUserId = min($userId, $partnerId);
        $secondUserId = max($userId, $partnerId);

        $query = $this->database->connect()->prepare(
            "
            SELECT 1 FROM matches
            WHERE user_a_id = :user_a_id AND user_b_id = :user_b_id
            "
        );
        $query->execute([
            'user_a_id' => $firstUserId,
            'user_b_id' => $secondUserId,
        ]);

        return (bool) $query->fetchColumn();
    }
}
