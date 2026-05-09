<?php

require_once 'AppController.php';

class OnboardingController extends AppController {

    public function index() {
        session_start();

        if (!isset($_SESSION['user_id'])) {
            $url = "http://$_SERVER[HTTP_HOST]";
            header("Location: {$url}/login");
            exit();
        }

        return $this->render('onboarding', [
            'userEmail' => $_SESSION['user_email']
        ]);
    }
}