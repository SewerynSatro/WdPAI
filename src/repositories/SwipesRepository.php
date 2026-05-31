<?php

require_once 'Repository.php';

class SwipesRepository extends Repository {

    public function createSwipe(int $swiperId, int $targetId, string $direction): void
    {
        $query = $this->database->connect()->prepare(
            "
            INSERT INTO swipes (swiper_id, target_id, direction)
            VALUES (:swiper_id, :target_id, :direction)
            ON CONFLICT (swiper_id, target_id) DO UPDATE SET
                direction = EXCLUDED.direction,
                swiped_at = CURRENT_TIMESTAMP
            "
        );
        $query->execute([
            'swiper_id' => $swiperId,
            'target_id' => $targetId,
            'direction' => strtoupper($direction),
        ]);
    }

    public function hasSwipedOn(int $swiperId, int $targetId): bool
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT 1 FROM swipes
            WHERE swiper_id = :swiper_id AND target_id = :target_id
            "
        );
        $query->execute([
            'swiper_id' => $swiperId,
            'target_id' => $targetId,
        ]);

        return (bool) $query->fetchColumn();
    }

    public function isMutualLike(int $userA, int $userB): bool
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT COUNT(*) FROM swipes
            WHERE direction = 'LIKE'
              AND (
                (swiper_id = :user_a_1 AND target_id = :user_b_1)
                OR
                (swiper_id = :user_b_2 AND target_id = :user_a_2)
              )
            "
        );
        $query->execute([
            'user_a_1' => $userA,
            'user_b_1' => $userB,
            'user_b_2' => $userB,
            'user_a_2' => $userA,
        ]);

        return (int) $query->fetchColumn() === 2;
    }
}
