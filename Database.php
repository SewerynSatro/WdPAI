<?php
// .env 
require_once "config.php";

// singleton 
class Database {
    private $username;
    private $password;
    private $host;
    private $database;
    private static ?PDO $conn = null;

    public function __construct()
    {
        $this->username = USERNAME;
        $this->password = PASSWORD;
        $this->host = HOST;
        $this->database = DATABASE;
    }

    public function connect()
    {
        if (self::$conn instanceof PDO) {
            return self::$conn;
        }

        try {
            self::$conn = new PDO(
                "pgsql:host=$this->host;port=5432;dbname=$this->database",
                $this->username,
                $this->password,
                ["sslmode"  => "prefer"]
            );

            // set the PDO error mode to exception
            self::$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return self::$conn;
        }
        catch(PDOException $e) {
            error_log('Database connection failed.');
            http_response_code(500);
            die('Database connection failed.');
        }
    }

    public function disconnect() {
        self::$conn = null;
    }
}
