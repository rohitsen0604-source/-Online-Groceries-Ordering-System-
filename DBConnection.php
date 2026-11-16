<?php

if (!function_exists('get_db_env')) {
    function get_db_env($key, $default = null) {
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
        $val = getenv($key);
        if ($val !== false && $val !== '') return $val;
        return $default;
    }
}

if (!function_exists('my_udf_md5')) {
    function my_udf_md5($string) {
        return md5($string);
    }
}

class DBResult {
    private $stmt;
    public $num_rows = 0;
    private $rows = null;
    private $cursor = 0;

    public function __construct($stmt) {
        $this->stmt = $stmt;
        if ($stmt instanceof \PDOStatement) {
            if ($stmt->columnCount() > 0) {
                try {
                    $this->rows = $stmt->fetchAll(\PDO::FETCH_BOTH);
                    $this->num_rows = count($this->rows);
                } catch (\Exception $e) {
                    $this->rows = [];
                    $this->num_rows = 0;
                }
            }
        }
    }

    public function fetchArray($mode = null) {
        if ($this->rows !== null) {
            if ($this->cursor < count($this->rows)) {
                return $this->rows[$this->cursor++];
            }
            return false;
        }
        if (!$this->stmt) return false;
        try {
            $row = $this->stmt->fetch(\PDO::FETCH_BOTH);
            return $row !== false ? $row : false;
        } catch (\Exception $e) {
            return false;
        }
    }
}

class DBConnection {
    protected $pdo;
    protected $db_type = 'mysql';
    protected $last_error = '';

    public function __construct() {
        // 1. Check for database connection string or individual environment variables
        $db_url = get_db_env('DATABASE_URL') 
            ?: get_db_env('MYSQL_URL') 
            ?: get_db_env('SERVICE_URI');

        $host = get_db_env('DB_HOST') 
            ?: get_db_env('MYSQL_HOST') 
            ?: get_db_env('MYSQLHOST') 
            ?: get_db_env('AIVEN_HOST');

        $port = get_db_env('DB_PORT') 
            ?: get_db_env('MYSQL_PORT') 
            ?: get_db_env('MYSQLPORT') 
            ?: get_db_env('AIVEN_PORT');

        $user = get_db_env('DB_USER') 
            ?: get_db_env('MYSQL_USER') 
            ?: get_db_env('MYSQLUSER') 
            ?: get_db_env('AIVEN_USER');

        $pass = get_db_env('DB_PASSWORD') 
            ?: get_db_env('MYSQL_PASSWORD') 
            ?: get_db_env('MYSQLPASSWORD') 
            ?: get_db_env('AIVEN_PASSWORD');

        $dbname = get_db_env('DB_NAME') 
            ?: get_db_env('MYSQL_DATABASE') 
            ?: get_db_env('MYSQLDATABASE') 
            ?: get_db_env('AIVEN_DATABASE');

        // Parse Database URL if provided (e.g. Aiven Service URI)
        if (!empty($db_url)) {
            $parsed = parse_url($db_url);
            if ($parsed) {
                if (!empty($parsed['host'])) $host = $parsed['host'];
                if (!empty($parsed['port'])) $port = $parsed['port'];
                if (!empty($parsed['user'])) $user = urldecode($parsed['user']);
                if (isset($parsed['pass'])) $pass = urldecode($parsed['pass']);
                if (!empty($parsed['path'])) $dbname = ltrim($parsed['path'], '/');
            }
        }

        // 2. If MySQL parameters are present, connect to MySQL
        if (!empty($host)) {
            $this->db_type = 'mysql';
            $port = !empty($port) ? (int)$port : 3306;
            $user = !empty($user) ? $user : 'avnadmin';
            $pass = !empty($pass) ? $pass : '';
            $dbname = !empty($dbname) ? $dbname : 'defaultdb';

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_BOTH,
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            ];

            // Aiven MySQL requires SSL. Setting VERIFY_SERVER_CERT to false enables SSL transport
            // without requiring custom CA certificate bundles.
            $options[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            if (file_exists(__DIR__ . '/ca.pem')) {
                $options[\PDO::MYSQL_ATTR_SSL_CA] = __DIR__ . '/ca.pem';
            }

            try {
                $this->pdo = new \PDO($dsn, $user, $pass, $options);
                $this->initializeMySQLTables();
            } catch (\PDOException $e) {
                $this->last_error = "MySQL Connection failed: " . $e->getMessage();
                die($this->last_error);
            }
        } else {
            // 3. Fallback to SQLite (local development)
            $this->db_type = 'sqlite';
            $db_dir = __DIR__ . '/db';
            if (!is_dir($db_dir)) {
                mkdir($db_dir, 0777, true);
            }
            $db_file = $db_dir . '/grocery_db.db';

            try {
                $this->pdo = new \PDO("sqlite:" . $db_file);
                $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_BOTH);
                if (method_exists($this->pdo, 'sqliteCreateFunction')) {
                    $this->pdo->sqliteCreateFunction('md5', 'my_udf_md5');
                }
                $this->pdo->exec("PRAGMA foreign_keys = ON;");
                $this->initializeSQLiteTables();
            } catch (\PDOException $e) {
                $this->last_error = "SQLite Connection failed: " . $e->getMessage();
                die($this->last_error);
            }
        }
    }

    public function query($sql) {
        $this->last_error = '';
        try {
            // Translate SQLite last_insert_rowid() if running on MySQL
            if ($this->db_type === 'mysql') {
                $sql = preg_replace('/last_insert_rowid\(\)/i', 'LAST_INSERT_ID()', $sql);
            }
            $stmt = $this->pdo->query($sql);
            if ($stmt === false) {
                $err = $this->pdo->errorInfo();
                $this->last_error = $err[2] ?? 'Query execution failed.';
                return false;
            }
            return new DBResult($stmt);
        } catch (\PDOException $e) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    public function exec($sql) {
        $this->last_error = '';
        try {
            return $this->pdo->exec($sql);
        } catch (\PDOException $e) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    public function lastInsertRowID() {
        if ($this->pdo) {
            return (int)$this->pdo->lastInsertId();
        }
        return 0;
    }

    public function lastErrorMsg() {
        return $this->last_error;
    }

    public function escapeString($str) {
        if ($this->pdo) {
            $quoted = $this->pdo->quote($str);
            return substr($quoted, 1, -1);
        }
        return addslashes($str);
    }

    public function close() {
        $this->pdo = null;
    }

    public function __destruct() {
        $this->close();
    }

    private function initializeMySQLTables() {
        $tables = [
            "CREATE TABLE IF NOT EXISTS `admin_list` (
                `admin_id` INT NOT NULL AUTO_INCREMENT,
                `fullname` VARCHAR(255) NOT NULL,
                `username` VARCHAR(255) NOT NULL,
                `password` VARCHAR(255) NOT NULL,
                `status` TINYINT NOT NULL DEFAULT 1,
                `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`admin_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS `customer_list` (
                `customer_id` INT NOT NULL AUTO_INCREMENT,
                `fullname` VARCHAR(255) NOT NULL,
                `username` VARCHAR(255) NOT NULL,
                `password` VARCHAR(255) NOT NULL,
                `email` VARCHAR(255) NOT NULL,
                `contact` VARCHAR(50) NOT NULL,
                `address` TEXT NOT NULL,
                `status` TINYINT NOT NULL DEFAULT 1,
                `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `date_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`customer_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS `category_list` (
                `category_id` INT NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `status` TINYINT NOT NULL DEFAULT 1,
                `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`category_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS `fees_list` (
                `fee_id` INT NOT NULL AUTO_INCREMENT,
                `location` VARCHAR(255) NOT NULL,
                `amount` DOUBLE NOT NULL DEFAULT 0,
                `status` TINYINT NOT NULL DEFAULT 1,
                `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`fee_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS `product_list` (
                `product_id` INT NOT NULL AUTO_INCREMENT,
                `category_id` INT NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `description` TEXT NOT NULL,
                `price` DOUBLE NOT NULL DEFAULT 0,
                `status` TINYINT NOT NULL DEFAULT 1,
                `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `date_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS `inventory_list` (
                `inventory_id` INT NOT NULL AUTO_INCREMENT,
                `product_id` INT NOT NULL,
                `quantity` DOUBLE NOT NULL,
                `type` TINYINT NOT NULL DEFAULT 1,
                `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`inventory_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS `cart_list` (
                `customer_id` INT NOT NULL,
                `product_id` INT NOT NULL,
                `quantity` DOUBLE NOT NULL,
                `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS `order_list` (
                `order_id` INT NOT NULL AUTO_INCREMENT,
                `customer_id` INT NOT NULL,
                `transaction_code` VARCHAR(100) NOT NULL,
                `fee_id` INT NOT NULL,
                `delivery_address` TEXT NOT NULL,
                `total_amount` DOUBLE NOT NULL,
                `status` TINYINT NOT NULL DEFAULT 0,
                `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`order_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS `order_items` (
                `order_id` INT NOT NULL,
                `inventory_id` INT NOT NULL,
                `product_id` INT NOT NULL,
                `quantity` DOUBLE NOT NULL,
                `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "INSERT IGNORE INTO `admin_list` (`admin_id`, `fullname`, `username`, `password`, `status`, `date_created`)
             VALUES (1, 'Administrator', 'admin', MD5('admin123'), 1, CURRENT_TIMESTAMP);"
        ];

        foreach ($tables as $t) {
            try {
                $this->pdo->exec($t);
            } catch (\Exception $e) {
                // Ignore if table/entry already exists
            }
        }

        // Seed default categories if empty
        try {
            $cat_count = $this->pdo->query("SELECT count(*) FROM `category_list`")->fetchColumn();
            if ($cat_count == 0) {
                $this->pdo->exec("INSERT INTO `category_list` (`category_id`, `name`, `status`) VALUES
                    (1, 'Snacks', 1),
                    (2, 'Beverages', 1),
                    (3, 'Canned Goods', 1)");
            }
        } catch (\Exception $e) {}

        // Seed default delivery fees if empty
        try {
            $fee_count = $this->pdo->query("SELECT count(*) FROM `fees_list`")->fetchColumn();
            if ($fee_count == 0) {
                $this->pdo->exec("INSERT INTO `fees_list` (`fee_id`, `location`, `amount`, `status`) VALUES
                    (1, 'Location 101', 50, 1),
                    (2, 'Location 102', 75, 1),
                    (3, 'Location 103', 100, 1)");
            }
        } catch (\Exception $e) {}
    }

    private function initializeSQLiteTables() {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `admin_list` (
            `admin_id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
            `fullname` TEXT NOT NULL,
            `username` TEXT NOT NULL,
            `password` TEXT NOT NULL,
            `status` INTEGER NOT NULL Default 1,
            `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `customer_list` (
            `customer_id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
            `fullname` TEXT NOT NULL,
            `username` TEXT NOT NULL,
            `password` TEXT NOT NULL,
            `email` TEXT NOT NULL,
            `contact` TEXT NOT NULL,
            `address` TEXT NOT NULL,
            `status` INTEGER NOT NULL Default 1,
            `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `date_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `category_list` (
            `category_id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
            `name` TEXT NOT NULL,
            `status` INTEGER NOT NULL Default 1,
            `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `fees_list` (
            `fee_id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
            `location` TEXT NOT NULL,
            `amount` REAL NOT NULL,
            `status` INTEGER NOT NULL DEFAULT 1,
            `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `product_list` (
            `product_id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
            `category_id` INTEGER NOT NULL,
            `name` TEXT NOT NULL,
            `description` TEXT NOT NULL,
            `price` REAL NOT NULL,
            `status` INTEGER NOT NULL Default 1,
            `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `date_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `inventory_list` (
            `inventory_id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
            `product_id` INTEGER NOT NULL,
            `quantity` INTEGER NOT NULL,
            `type` INTEGER NOT NULL Default 1,
            `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `cart_list` (
            `customer_id` INTEGER NOT NULL,
            `product_id` INTEGER NOT NULL,
            `quantity` REAL NOT NULL,
            `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `order_list` (
            `order_id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
            `customer_id` INTEGER NOT NULL,
            `transaction_code` TEXT NOT NULL,
            `fee_id` INTEGER NOT NULL,
            `delivery_address` TEXT NOT NULL,
            `total_amount` REAL NOT NULL,
            `status` INTEGER NOT NULL Default 0,
            `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `order_items` (
            `order_id` INTEGER NOT NULL,
            `inventory_id` INTEGER NOT NULL,
            `product_id` INTEGER NOT NULL,
            `quantity` REAL NOT NULL,
            `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("INSERT or IGNORE INTO `admin_list` VALUES (1,'Administrator','admin',md5('admin123'),1, CURRENT_TIMESTAMP)");
    }
}

$conn = new DBConnection();
