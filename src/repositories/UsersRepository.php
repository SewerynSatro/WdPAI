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
