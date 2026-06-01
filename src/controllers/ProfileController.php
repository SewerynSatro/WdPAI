<?php

require_once 'AppController.php';

class ProfileController extends AppController {

    public function index() {
        $this->requireCompletedOnboarding();

        return $this->render('profile', [
            'userEmail'  => $_SESSION['user_email'],
            'activePage' => 'profile'
        ]);
    }
}
