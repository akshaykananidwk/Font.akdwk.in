<?php
/**
 * PDO wrapper — ફક્ત prepared statements.
 * કોઈ પણ query માં string concatenation થી variable ન વાપરવો.
 */

defined('BASE_PATH') or die('Direct access denied');

class Database
{
    private static ?Database $instance = null;
    private ?PDO $pdo = null;
    private string $prefix = '';

    /**
     * Private constructor — singleton pattern.
     *
     * @param array $cfg db config array (host, port, name, user, pass, prefix, charset)
     * @throws PDOException connection ફેલ થાય તો
     */
    private function __construct(array $cfg)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'] ?: '3306',
            $cfg['name'],
            $cfg['charset'] ?? 'utf8mb4'
        );
        $this->pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ]);
        $this->prefix = $cfg['prefix'] ?? '';
    }

    /**
     * Singleton instance મેળવો.
     */
    public static function getInstance(?array $cfg = null): Database
    {
        if (self::$instance === null) {
            if ($cfg === null) {
                $config = App::config();
                $cfg = $config['db'];
            }
            self::$instance = new self($cfg);
        }
        return self::$instance;
    }

    /**
     * Table name ને prefix સાથે જોડો.
     */
    public function table(string $name): string
    {
        return $this->prefix . $name;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Prepared query ચલાવો.
     *
     * @param string $sql   :placeholder અથવા ? સાથે SQL
     * @param array  $params bind values
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** એક row મેળવો (અથવા null). */
    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** બધી rows મેળવો. */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /** પહેલી column ની value (COUNT વગેરે માટે). */
    public function fetchValue(string $sql, array $params = []): mixed
    {
        $val = $this->query($sql, $params)->fetchColumn();
        return $val === false ? null : $val;
    }

    /**
     * INSERT helper — associative array માંથી.
     *
     * @return int છેલ્લું insert id
     */
    public function insert(string $tableName, array $data): int
    {
        $table = $this->table($tableName);
        $cols = array_keys($data);
        $colSql = '`' . implode('`, `', $cols) . '`';
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $this->query("INSERT INTO `{$table}` ({$colSql}) VALUES ({$placeholders})", array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * UPDATE helper.
     *
     * @param string $where WHERE clause (placeholders સાથે, 'WHERE' શબ્દ વગર)
     */
    public function update(string $tableName, array $data, string $where, array $whereParams = []): int
    {
        $table = $this->table($tableName);
        $sets = [];
        foreach (array_keys($data) as $col) {
            $sets[] = "`{$col}` = ?";
        }
        $sql = "UPDATE `{$table}` SET " . implode(', ', $sets) . " WHERE {$where}";
        $stmt = $this->query($sql, array_merge(array_values($data), $whereParams));
        return $stmt->rowCount();
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
