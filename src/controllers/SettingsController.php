<?php


require_once 'AppController.php';

class SettingsController extends AppController
{

    public function index()
    {
        session_start();

        if (!isset($_SESSION['user_id'])) {
            $url = "http://$_SERVER[HTTP_HOST]";
            header("Location: {$url}/login");
            exit();
        }

        return $this->render('settings', [
            'userEmail' => $_SESSION['user_email'],
            'activePage' => 'settings'
        ]);
    }
}