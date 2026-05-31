<?php

require_once 'Repository.php';

class ProviderAccountsRepository extends Repository {

    public function getByUserAndProvider(int $userId, string $providerKey)
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT * FROM provider_accounts
            WHERE user_id = :user_id AND provider_key = :provider_key
            "
        );
        $query->execute([
            'user_id' => $userId,
            'provider_key' => $providerKey,
        ]);

        return $query->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function isConnected(int $userId, string $providerKey): bool
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT 1 FROM provider_accounts
            WHERE user_id = :user_id AND provider_key = :provider_key
            "
        );
        $query->execute([
            'user_id' => $userId,
            'provider_key' => $providerKey,
        ]);

        return (bool) $query->fetchColumn();
    }

    public function upsert(
        int $userId,
        string $providerKey,
        string $accessToken,
        ?string $refreshToken,
        int $expiresIn
    ): void {
        $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

        $query = $this->database->connect()->prepare(
            "
            INSERT INTO provider_accounts (
                user_id,
                provider_key,
                access_token,
                refresh_token,
                token_expires_at
            )
            VALUES (
                :user_id,
                :provider_key,
                :access_token,
                :refresh_token,
                :token_expires_at
            )
            ON CONFLICT (user_id, provider_key) DO UPDATE SET
                access_token = EXCLUDED.access_token,
                refresh_token = EXCLUDED.refresh_token,
                token_expires_at = EXCLUDED.token_expires_at,
                updated_at = CURRENT_TIMESTAMP
            "
        );
        $query->execute([
            'user_id' => $userId,
            'provider_key' => $providerKey,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_expires_at' => $expiresAt,
        ]);
    }
}
