<?php
/**
 * @package    Phpmig
 * @subpackage Phpmig\Adapter
 */

namespace Phpmig\Adapter\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Phpmig\Adapter\AdapterInterface;
use Phpmig\Migration\Migration;

/**
 * This file is part of phpmig
 *
 * Copyright (c) 2011 Dave Marshall <dave.marshall@atstsolutuions.co.uk>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


/**
 * Phpmig adapter for doctrine dbal connection
 *
 * @author      Dave Marshall <david.marshall@atstsolutions.co.uk>
 */
class DBAL implements AdapterInterface
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * @var string
     */
    protected $tableName;

    public function __construct(Connection $connection, string $tableName)
    {
        $this->connection = $connection;
        $this->tableName = $tableName;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll()
    {
        $tableName = $this->connection->quoteIdentifier($this->tableName);
        $sql = "SELECT version FROM $tableName ORDER BY version ASC";
        $all = $this->connection->fetchAll($sql);

        return array_map(function ($v) {
            return $v['version'];
        }, $all);
    }

    /**
     * {@inheritdoc}
     */
    public function up(Migration $migration)
    {
        $this->connection->insert($this->tableName, [
            'version' => $migration->getVersion(),
        ]);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function down(Migration $migration)
    {
        $this->connection->delete($this->tableName, [
            'version' => $migration->getVersion(),
        ]);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function hasSchema()
    {
        $sm = $this->connection->getSchemaManager();
        $tables = $sm->listTables();
        foreach ($tables as $table) {
            if ($table->getName() == $this->tableName) {
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
        $schema = new \Doctrine\DBAL\Schema\Schema();
        $table = $schema->createTable($this->tableName);
        $table->addColumn("version", "string", ["length" => 255]);
        $queries = $schema->toSql($this->connection->getDatabasePlatform());
        foreach ($queries as $sql) {
            $this->connection->query($sql);
        }

        return $this;
    }
}
