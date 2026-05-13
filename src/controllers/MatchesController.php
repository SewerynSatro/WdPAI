<?php

require_once 'AppController.php';

class MatchesController extends AppController {

    public function index() {
        session_start();

        if (!isset($_SESSION['user_id'])) {
            $url = "http://$_SERVER[HTTP_HOST]";
            header("Location: {$url}/login");
            exit();
        }

        return $this->render('matches', [
            'userEmail'  => $_SESSION['user_email'],
            'activePage' => 'matches'
        ]);
    }
    public function show(int $id) {
        session_start();

        if (!isset($_SESSION['user_id'])) {
            $url = "http://$_SERVER[HTTP_HOST]";
            header("Location: {$url}/login");
            exit();
        }

        return $this->render('matches-view', [
            'userEmail'  => $_SESSION['user_email'],
            'activePage' => 'matches',
            'partnerId'  => $id
        ]);
    }
}
