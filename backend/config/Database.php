<?php
require_once __DIR__ . '/Tenant.php';

class Database {
    private $host = "localhost";
    private $db_name = "accademics";
    private $username = "root";
    private $password = "";
    private $conn;

    public function __construct() {
        // A server's own login lives in database.local.php (gitignored), e.g. on live:
        // <?php return ['host' => 'localhost', 'db_name' => '…', 'username' => '…', 'password' => '…'];
        $local = __DIR__ . '/database.local.php';
        if (is_file($local)) {
            foreach ((array)require $local as $key => $value) {
                if (in_array($key, ['host', 'db_name', 'username', 'password'], true)) {
                    $this->$key = (string)$value;
                }
            }
        }
    }

    public function getConnection() {
        $this->conn = null;
        try {
            // One database per school; Tenant picks this request's school (see Tenant.php).
            $dbName = Tenant::databaseFor($this->db_name, fn(string $db) => $this->connect($db));
            $this->conn = $this->connect($dbName);
        } catch(PDOException $e) {
            echo "Connection Error: " . $e->getMessage();
        }
        return $this->conn;
    }

    private function connect(string $dbName): PDO {
        $conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $dbName, $this->username, $this->password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $conn->exec("set names utf8mb4");
        return $conn;
    }
}
?>
