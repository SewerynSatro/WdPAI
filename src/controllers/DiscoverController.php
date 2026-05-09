<?php

require_once 'AppController.php';

class DiscoverController extends AppController {

    public function index() {
        session_start();

        if (!isset($_SESSION['user_id'])) {
            $url = "http://$_SERVER[HTTP_HOST]";
            header("Location: {$url}/login");
            exit();
        }

        return $this->render('discover', [
            'userEmail'  => $_SESSION['user_email'],
            'activePage' => 'discover'
        ]);
    }
}