<?php
class Database
{
    private $host = "localhost";
    private $port = "3306";
    private $db_name = "lost_found_db";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection(): PDO // ? point direct object for mariadb or other supported databases
    {
        $this->conn = null; // * '$' sign is used for variables declartion & this reserved word is for calling the object in PHP 
        try {
            // MariaDB connection with character set and options
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=utf8mb4";     // dsn = data source name , utf8mb4 to support all characters including emojis

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $exception) {
            die("MariaDB Connection error: " . $exception->getMessage());
        }
        return $this->conn;
    }

    public function testConnection(): mixed
    {
        try {
            $conn = $this->getConnection();
            $stmt = $conn->query("SELECT VERSION() as version");        // ! stmt = execute the same or similar to mysqli_query statement
            $result = $stmt->fetch();
            return $result;
        } catch (Exception $e) {
            return false;
        }
    }
}
