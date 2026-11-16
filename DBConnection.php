<?php

if (!function_exists('get_db_env')) {
    function get_db_env($key, $default = null) {
        $search = strtolower($key);

        // Check $_ENV
        if (!empty($_ENV)) {
            foreach ($_ENV as $k => $v) {
                if (strtolower($k) === $search && $v !== null && $v !== '') {
                    return $v;
                }
            }
        }

        // Check $_SERVER
        if (!empty($_SERVER)) {
            foreach ($_SERVER as $k => $v) {
                if (strtolower($k) === $search && $v !== null && $v !== '') {
                    return $v;
                }
            }
        }

        // Check getenv() array
        $all = getenv();
        if (is_array($all)) {
            foreach ($all as $k => $v) {
                if (strtolower($k) === $search && $v !== null && $v !== '') {
                    return $v;
                }
            }
        }

        // Fallback standard getenv
        $val = getenv($key);
        if ($val !== false && $val !== '') {
            return $val;
        }

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
        // 1. Check for database connection string (URL format)
        $db_url = get_db_env('DATABASE_URL') 
            ?: get_db_env('MYSQL_URL') 
            ?: get_db_env('SERVICE_URI');

        // 2. Individual database parameters
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
            ?: get_db_env('DB_PASS')
            ?: get_db_env('DB_PASSWD')
            ?: get_db_env('MYSQL_PASSWORD') 
            ?: get_db_env('MYSQL_PASS') 
            ?: get_db_env('MYSQLPASSWORD') 
            ?: get_db_env('MYSQLPASS') 
            ?: get_db_env('MYSQL_PWD')
            ?: get_db_env('AIVEN_PASSWORD')
            ?: get_db_env('AIVEN_PASS')
            ?: get_db_env('DATABASE_PASSWORD')
            ?: get_db_env('DATABASE_PASS')
            ?: get_db_env('PASSWORD')
            ?: get_db_env('PASS');

        $dbname = get_db_env('DB_NAME') 
            ?: get_db_env('MYSQL_DATABASE') 
            ?: get_db_env('MYSQLDATABASE') 
            ?: get_db_env('AIVEN_DATABASE');

        // Parse Database URL if provided (e.g. Aiven Service URI)
        if (!empty($db_url)) {
            // Regex parse handles passwords with special characters (#, @, ?, etc.)
            if (preg_match('/^[a-z0-9+.-]+:\/\/([^:]+):(.*)@([^:\/?#]+)(?::([0-9]+))?(?:\/([^?#]*))?/i', $db_url, $m)) {
                $user = urldecode($m[1]);
                $pass = urldecode($m[2]);
                $host = $m[3];
                if (!empty($m[4])) $port = $m[4];
                if (!empty($m[5])) $dbname = $m[5];
            } else {
                $parsed = parse_url($db_url);
                if ($parsed) {
                    if (!empty($parsed['host'])) $host = $parsed['host'];
                    if (!empty($parsed['port'])) $port = $parsed['port'];
                    if (!empty($parsed['user'])) $user = urldecode($parsed['user']);
                    if (isset($parsed['pass'])) $pass = urldecode($parsed['pass']);
                    if (!empty($parsed['path'])) $dbname = ltrim($parsed['path'], '/');
                }
            }
        }

        // Clean up accidental quotes from password
        if ($pass !== null && $pass !== '') {
            $pass = trim($pass);
            if ((str_starts_with($pass, '"') && str_ends_with($pass, '"')) ||
                (str_starts_with($pass, "'") && str_ends_with($pass, "'"))) {
                $pass = substr($pass, 1, -1);
            }
        }

        // 3. Connect to MySQL if host is present
        if (!empty($host)) {
            $this->db_type = 'mysql';
            $port = !empty($port) ? (int)$port : 3306;
            $user = !empty($user) ? $user : 'avnadmin';
            $dbname = !empty($dbname) ? $dbname : 'defaultdb';

            // Check if password is missing
            if ($pass === null || $pass === '') {
                $this->renderConnectionError(
                    "Database Password Missing",
                    "MySQL host was found ('{$host}') and user is '{$user}', but <strong>DB_PASSWORD</strong> is not set or empty in Render Environment Variables.",
                    $host, $port, $user, $dbname, false
                );
            }

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_BOTH,
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            ];

            // Aiven MySQL requires SSL
            $options[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            if (file_exists(__DIR__ . '/ca.pem')) {
                $options[\PDO::MYSQL_ATTR_SSL_CA] = __DIR__ . '/ca.pem';
            }

            try {
                $this->pdo = new \PDO($dsn, $user, $pass, $options);
                $this->initializeMySQLTables();
            } catch (\PDOException $e) {
                $this->renderConnectionError(
                    "MySQL Connection Failed",
                    $e->getMessage(),
                    $host, $port, $user, $dbname, true, strlen($pass)
                );
            }
        } else {
            // 4. Fallback to SQLite (local development)
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

    private function renderConnectionError($title, $msg, $host, $port, $user, $dbname, $has_pass, $pass_len = 0) {
        http_response_code(500);
        $pass_status = $has_pass 
            ? "<span style='color:green;'>Received ({$pass_len} characters)</span>" 
            : "<span style='color:red;'>NOT FOUND / EMPTY</span>";

        echo "<!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Database Connection Error</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #f8fafc; color: #1e293b; padding: 40px 20px; }
                .card { max-width: 650px; margin: 0 auto; background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1); overflow: hidden; border: 1px solid #e2e8f0; }
                .header { background: #ef4444; color: white; padding: 20px 24px; }
                .header h2 { margin: 0; font-size: 20px; }
                .body { padding: 24px; }
                .error-box { background: #fee2e2; border-left: 4px solid #ef4444; padding: 12px 16px; border-radius: 4px; margin-bottom: 20px; font-family: monospace; font-size: 14px; word-break: break-all; color: #991b1b; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
                td.label { font-weight: bold; width: 140px; color: #64748b; }
                .help { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 16px; font-size: 14px; }
                .help h4 { margin: 0 0 8px 0; color: #166534; }
                code { background: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-size: 13px; }
            </style>
        </head>
        <body>
            <div class='card'>
                <div class='header'>
                    <h2>⚠️ {$title}</h2>
                </div>
                <div class='body'>
                    <div class='error-box'>{$msg}</div>
                    <table>
                        <tr><td class='label'>DB_HOST</td><td>{$host}</td></tr>
                        <tr><td class='label'>DB_PORT</td><td>{$port}</td></tr>
                        <tr><td class='label'>DB_USER</td><td>{$user}</td></tr>
                        <tr><td class='label'>DB_NAME</td><td>{$dbname}</td></tr>
                        <tr><td class='label'>DB_PASSWORD</td><td>{$pass_status}</td></tr>
                    </table>
                    <div class='help'>
                        <h4>💡 Solution for Render:</h4>
                        <p>Render Dashboard &gt; Service &gt; <strong>Environment</strong> me jayein aur check karein:</p>
                        <ul>
                            <li><strong>DB_PASSWORD</strong> ka naam sahi ho aur value me Aiven password paste kiya ho.</li>
                            <li>Ya <strong>DATABASE_URL</strong> me pura Service URI (with password) set karein.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </body>
        </html>";
        exit;
    }

    public function query($sql) {
        $this->last_error = '';
        try {
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
