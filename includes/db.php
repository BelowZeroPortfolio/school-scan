<?php
/**
 * Database Connection and Query Functions
 * Provides PDO connection and prepared statement helpers
 */

// Ensure config is loaded
if (!isset($GLOBALS['config'])) {
    require_once __DIR__ . '/../config/config.php';
}

// Global PDO connection instance
$GLOBALS['db_connection'] = null;

/**
 * Get PDO database connection (singleton pattern)
 * 
 * @return PDO Database connection
 * @throws PDOException If connection fails
 */
function getDbConnection() {
    if ($GLOBALS['db_connection'] !== null) {
        return $GLOBALS['db_connection'];
    }
    
    try {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            config('db_host'),
            config('db_name'),
            config('db_charset', 'utf8mb4')
        );
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $GLOBALS['db_connection'] = new PDO(
            $dsn,
            config('db_user'),
            config('db_pass'),
            $options
        );
        
        return $GLOBALS['db_connection'];
    } catch (PDOException $e) {
        // Log error if logger is available
        if (function_exists('logError')) {
            logError('Database connection failed: ' . $e->getMessage());
        }
        
        // In production, show generic error
        if (!config('debug', false)) {
            die('Database connection failed. Please contact administrator.');
        }
        
        throw $e;
    }
}

/**
 * Execute prepared statement with parameters
 * 
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters for prepared statement
 * @return PDOStatement Executed statement
 * @throws PDOException If query fails
 */
function dbQuery($sql, $params = []) {
    try {
        $db = getDbConnection();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        // Log error if logger is available
        if (function_exists('logError')) {
            logError('Database query failed: ' . $e->getMessage(), [
                'sql' => $sql,
                'params' => $params
            ]);
        }
        
        throw $e;
    }
}

/**
 * Execute query and fetch single row
 * 
 * @param string $sql SQL query
 * @param array $params Query parameters
 * @return array|null Single row or null if not found
 */
function dbFetchOne($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    $result = $stmt->fetch();
    return $result ?: null;
}

/**
 * Execute query and fetch all rows
 * 
 * @param string $sql SQL query
 * @param array $params Query parameters
 * @return array Array of rows
 */
function dbFetchAll($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Execute INSERT and return last insert ID
 * 
 * @param string $sql INSERT query
 * @param array $params Query parameters
 * @return int Last insert ID
 */
function dbInsert($sql, $params = []) {
    dbQuery($sql, $params);
    return (int) getDbConnection()->lastInsertId();
}

/**
 * Execute UPDATE/DELETE and return affected rows
 * 
 * @param string $sql UPDATE or DELETE query
 * @param array $params Query parameters
 * @return int Number of affected rows
 */
function dbExecute($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    return $stmt->rowCount();
}

/**
 * Begin database transaction
 * 
 * @return bool True on success
 */
function dbBeginTransaction() {
    return getDbConnection()->beginTransaction();
}

/**
 * Commit database transaction
 * 
 * @return bool True on success
 */
function dbCommit() {
    return getDbConnection()->commit();
}

/**
 * Rollback database transaction
 * 
 * @return bool True on success
 */
function dbRollback() {
    return getDbConnection()->rollBack();
}
