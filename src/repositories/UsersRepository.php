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
                COALESCE(u.display_name, CONCAT(u.firstname, ' ', u.lastname)) AS display_name,
                up.bio,
                up.city,
                up.birth_date,
                up.gender,
                up.looking_for,
                up.instagram_handle,
                up.facebook_handle,
                up.spotify_handle
            FROM users u
            LEFT JOIN user_profiles up ON up.user_id = u.id
            WHERE u.id != :user_id
              AND u.is_active = TRUE
              AND COALESCE(up.onboarding_completed, FALSE) = TRUE
              AND u.id NOT IN (
                  SELECT target_id FROM swipes WHERE swiper_id = :swiper_id
              )
            ORDER BY RANDOM()
            LIMIT 1
            "
        );
        $query->execute([
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
        string $firstname,
        string $lastname,
    ) {
        $displayName = trim($firstname . ' ' . $lastname);

        $query = $this->database->connect()->prepare(
            "
            INSERT INTO users (firstname, lastname, email, password, display_name)
            VALUES (?, ?, ?, ?, ?);
            "
        );
        $query->execute([
            $firstname,
            $lastname,
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
        $parts = preg_split('/\s+/', trim($displayName), 2);
        $firstname = $parts[0] ?? $displayName;
        $lastname = $parts[1] ?? '';

        $params = [
            'id' => $userId,
            'email' => $email,
            'display_name' => $displayName,
            'firstname' => $firstname,
            'lastname' => $lastname,
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
                lastname = :lastname,
                updated_at = CURRENT_TIMESTAMP
                {$passwordSql}
            WHERE id = :id
            "
        );
        $query->execute($params);
    }
}
