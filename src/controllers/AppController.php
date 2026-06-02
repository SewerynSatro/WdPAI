<?php

require_once __DIR__ . '/../repositories/ProfilesRepository.php';
require_once __DIR__ . '/../repositories/ProviderAccountsRepository.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';

class AppController {
    protected function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    protected function isGet(): bool
    {
        return $_SERVER["REQUEST_METHOD"] === 'GET';
    }

    protected function isPost(): bool
    {
        return $_SERVER["REQUEST_METHOD"] === 'POST';
    }

    protected function redirect(string $path): void
    {
        $url = "http://$_SERVER[HTTP_HOST]";
        header("Location: {$url}{$path}");
        exit();
    }

    protected function requireLogin(): bool
    {
        $this->startSession();

        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/login');
        }

        return true;
    }

    protected function requireCompletedOnboarding(): bool
    {
        $this->requireLogin();

        if (!$this->hasCompletedOnboarding((int) $_SESSION['user_id'])) {
            $this->redirect('/onboarding');
        }

        return true;
    }

    protected function hasCompletedOnboarding(int $userId): bool
    {
        $profilesRepository = new ProfilesRepository();
        $providerAccountsRepository = new ProviderAccountsRepository();
        $usersRepository = new UsersRepository();
        $user = $usersRepository->getUserById($userId);
        $profile = $profilesRepository->getProfileByUserId($userId);

        if (!$user || !$profile) {
            return false;
        }

        $hasCompletedFlag = in_array($profile['onboarding_completed'], [true, 1, '1', 't', 'true'], true);
        $hasRequiredProfile = trim($user['display_name'] ?? '') !== ''
            && filter_var($user['email'] ?? '', FILTER_VALIDATE_EMAIL)
            && trim($profile['bio'] ?? '') !== ''
            && trim($profile['birth_date'] ?? '') !== ''
            && trim($profile['gender'] ?? '') !== ''
            && trim($profile['looking_for'] ?? '') !== '';
        $hasSocial = trim($profile['instagram_handle'] ?? '') !== ''
            || trim($profile['facebook_handle'] ?? '') !== ''
            || trim($profile['spotify_handle'] ?? '') !== '';
        $hasProvider = $providerAccountsRepository->isConnected($userId, 'spotify');

        return $hasCompletedFlag && $hasRequiredProfile && $hasSocial && $hasProvider;
    }
 
    protected function render(string $template = null, array $variables = [])
    {
        $templatePath = 'public/views/'. $template.'.html';
        $templatePath404 = 'public/views/404.html';
        $output = "";
                 
        if(file_exists($templatePath)){
            extract($variables);
            // ["tab_name" => $title]

            // $tab_name = $title

            ob_start();
            include $templatePath;
            $output = ob_get_clean();
        } else {
            ob_start();
            include $templatePath404;
            $output = ob_get_clean();
        }
        echo $output;
    }

}
