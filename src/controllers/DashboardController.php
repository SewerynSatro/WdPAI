<?php

require_once 'AppController.php';
require_once __DIR__.'/../repositories/UsersRepository.php';

class DashboardController extends AppController {

    public function index() {
        $this->requireCompletedOnboarding();

        $title = "INDEX";
        $usersRepository = new UsersRepository();
        $users = $usersRepository->getUsers();

        $this->redirect('/discover');
    }
}
