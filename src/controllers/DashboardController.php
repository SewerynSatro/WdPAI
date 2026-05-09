<?php

require_once 'AppController.php';
require_once __DIR__.'/../repositories/UsersRepository.php';

class DashboardController extends AppController {

    public function index() {
        session_start();

        if (!isset($_SESSION['user_id'])) {
            $url = "http://$_SERVER[HTTP_HOST]";
            header("Location: {$url}/login");
            exit();
        }

        $title = "INDEX";
        $usersRepository = new UsersRepository();
        $users = $usersRepository->getUsers();

        return $this->render("dashboard", ["title" => $title, "users" => $users]);
    }
}