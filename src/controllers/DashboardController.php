<?php

require_once 'AppController.php';
require_once __DIR__.'/../repositories/UsersRepository.php';

class DashboardController extends AppController {

    public function index() {
        $this->requireCompletedOnboarding();

        $title = "INDEX";
        $usersRepository = UsersRepository::getInstance();
        $users = $usersRepository->getUsers();

        $this->redirect('/discover');
    }
}
