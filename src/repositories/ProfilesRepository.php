<?php

require_once 'Repository.php';

class ProfilesRepository extends Repository {

    public function getProfileByUserId(int $userId)
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT * FROM user_profiles WHERE user_id = :user_id
            "
        );
        $query->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $query->execute();

        return $query->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function createForUser(int $userId): void
    {
        $query = $this->database->connect()->prepare(
            "
            INSERT INTO user_profiles (user_id)
            VALUES (:user_id)
            ON CONFLICT (user_id) DO NOTHING
            "
        );
        $query->execute(['user_id' => $userId]);
    }

    public function updateProfile(int $userId, array $fields): void
    {
        $allowedFields = [
            'bio',
            'city',
            'birth_date',
            'gender',
            'looking_for',
            'avatar_path',
            'instagram_handle',
            'facebook_handle',
            'spotify_handle',
            'latitude',
            'longitude',
            'onboarding_completed',
        ];

        $setParts = [];
        $params = ['user_id' => $userId];

        foreach ($fields as $field => $value) {
            if (!in_array($field, $allowedFields, true)) {
                continue;
            }

            $setParts[] = "{$field} = :{$field}";
            $params[$field] = $field === 'onboarding_completed'
                ? ($value ? 'true' : 'false')
                : $value;
        }

        if (empty($setParts)) {
            return;
        }

        $this->createForUser($userId);

        $query = $this->database->connect()->prepare(
            "
            UPDATE user_profiles
            SET " . implode(', ', $setParts) . ",
                updated_at = CURRENT_TIMESTAMP
            WHERE user_id = :user_id
            "
        );
        $query->execute($params);
    }
}
