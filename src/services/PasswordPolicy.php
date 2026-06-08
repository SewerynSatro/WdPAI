<?php

class PasswordPolicy
{
    public static function isStrong(string $password): bool
    {
        return strlen($password) >= 8
            && strlen($password) <= 128
            && preg_match('/[a-z]/', $password)
            && preg_match('/[A-Z]/', $password)
            && preg_match('/\d/', $password)
            && preg_match('/[^a-zA-Z\d]/', $password);
    }
}
