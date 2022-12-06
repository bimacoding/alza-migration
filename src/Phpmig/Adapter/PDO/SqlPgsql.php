<?php

namespace Phpmig\Adapter\PDO;

use PDO;
use Phpmig\Migration\Migration;

/**
 * Simple PDO adapter to work with Postgres SQL database in particular.
 *
 * @author Theodson https://github.com/theodson
 */
class SqlPgsql extends Sql
{
    private $quote;
    private $schemaName;

    /**
     * @param \PDO   $connection
     * @param string $tableName
     * @param string $schemaName
     */
    public function __construct(\PDO $connection, $tableName, $schemaName = 'public')
    {
        parent::__construct($connection, $tableName);
        $driver = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME);
        $this->quote = in_array($driver, ['mysql', 'pgsql']) ? '"' : '`';
        $this->schemaName = $schemaName;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll()
    {
        $sql = "SELECT {$this->quote}version{$this->quote} FROM {$this->quotedTableName()} ORDER BY {$this->quote}version{$this->quote} ASC";
        return $this->connection->query($sql, PDO::FETCH_COLUMN, 0)->fetchAll();
    }

    /**
     * {@inheritdoc}
     */
    public function up(Migration $migration)
    {
        $sql = "INSERT into {$this->quotedTableName()} (version) VALUES (:version);";
        $this->connection->prepare($sql)
            ->execute([':version' => $migration->getVersion()]);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function down(Migration $migration)
    {
        $sql = "DELETE from {$this->quotedTableName()} where version = :version";
        $this->connection->prepare($sql)
            ->execute([':version' => $migration->getVersion()]);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function hasSchema()
    {
        $tables = $this->connection->query("SELECT table_name FROM information_schema.tables WHERE table_schema = '{$this->schemaName}';");
        while ($table = $tables->fetchColumn()) {
            if ($table == $this->tableName) {
                return true;
            }
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function createSchema()
    {
        $sql = sprintf("SELECT COUNT(*) FROM {$this->quote}information_schema{$this->quote}.{$this->quote}schemata{$this->quote} WHERE schema_name = '%s';",
            $this->schemaName);
        $res = $this->connection->query($sql);

        if (!$res || !$res->fetchColumn()) {
            $sql = sprintf("CREATE SCHEMA %s;", $this->schemaName);
            if (false === $this->connection->exec($sql)) {
                $e = $this->connection->errorInfo();
            }
        }

        $sql = "CREATE table {$this->quotedTableName()} (version %s NOT NULL, {$this->quote}migrate_date{$this->quote} timestamp(6) WITH TIME ZONE DEFAULT now())";
        $driver = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = sprintf($sql, in_array($driver, ['mysql', 'pgsql']) ? 'VARCHAR(255)' : '');

        if (false === $this->connection->exec($sql)) {
            $e = $this->connection->errorInfo();
        }
        return $this;
    }

    private function quotedTableName()
    {
        $sql = "{$this->quote}{$this->schemaName}{$this->quote}.{$this->quote}{$this->tableName}{$this->quote}";
        return $sql;
    }
}
