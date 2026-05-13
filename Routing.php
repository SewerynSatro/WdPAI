<?php

require_once 'src/controllers/SecurityController.php';
require_once 'src/controllers/DashboardController.php';
require_once 'src/controllers/DiscoverController.php';
require_once 'src/controllers/MatchesController.php';
require_once 'src/controllers/ProfileController.php';
require_once 'src/controllers/SettingsController.php';
require_once 'src/controllers/OnboardingController.php';


// TODO musimy zapewnic, ze utworzony 
// obiekt kontrollera ma tylko jedna instancję - SINGLETON

// TODO 2 /dashboard -- wszystkei dnae
// /dashboard/12234 -- wyciagnie nam jakis elemtn o wskaznaym ID 12234
// REGEX
class Routing {

    public static $routes = [
        "login" => [
            "controller" => "SecurityController",
            "action" => "login"
        ],
        "dashboard" => [
            "controller" => "DashboardController",
            "action" => "index"
        ],
        "" => [
            "controller" => "SecurityController",
            "action" => "login"
        ],
        "register" => [
            "controller" => "SecurityController",
            "action" => "register"
        ],
    ];

    public static function run(string $path) {
        if (preg_match('/^matches\/(\d+)$/', $path, $m)) {
            $controller = new MatchesController();
            $controller->show((int)$m[1]);
            return;
        }
        switch($path) {
            case '':
                include 'public/views/landing.html';
                break;
            case 'dashboard':
                $controller = new DashboardController();
                $controller->index();
                break;
            case 'login':
            case 'register':
                $controller = Routing::$routes[$path]["controller"];
                $action = Routing::$routes[$path]["action"];
                $controllerObj = new $controller;
                $id = null;
                $controllerObj->$action($id);
                break;
            case 'logout':
                $controller = new SecurityController();
                $controller->logout();
                break;
            case 'discover':
                $controller = new DiscoverController();
                $controller->index();
                break;
            case 'matches':
                $controller = new MatchesController();
                $controller->index();
                break;
            case 'profile':
                $controller = new ProfileController();
                $controller->index();
                break;
            case 'settings':
                $controller = new SettingsController();
                $controller->index();
                break;
            case 'onboarding':
                $controller = new OnboardingController();
                $controller->index();
                break;
            default:
                include 'public/views/404.html';
                break;
        }
    }
}