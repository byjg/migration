<?php

namespace ByJG\DbMigration;

use ByJG\AnyDataset\ConnectionManagement;
use ByJG\AnyDataset\Repository\DBDataset;
use ByJG\DbMigration\Commands\CommandInterface;

class Migration
{
    /**
     * @var ConnectionManagement
     */
    protected $_connection;

    /**
     * @var string
     */
    protected $_folder;

    /**
     * @var DBDataset
     */
    protected $_dbDataset;

    /**
     * @var CommandInterface
     */
    protected $_dbCommand;
    
    /**
     * Migration constructor.
     *
     * @param ConnectionManagement $_connection
     * @param string $_folder
     */
    public function __construct(ConnectionManagement $_connection, $_folder)
    {
        $this->_connection = $_connection;
        $this->_folder = $_folder;

        if (!file_exists($this->_folder) || !is_dir($this->_folder)) {
            throw new \InvalidArgumentException("Base migrations directory '{$this->_folder}' not found");
        }
    }

    /**
     * @return DBDataset
     */
    public function getDbDataset()
    {
        if (is_null($this->_dbDataset)) {
            $this->_dbDataset = new DBDataset($this->_connection->getDbConnectionString());
        }
        return $this->_dbDataset;
    }

    /**
     * @return CommandInterface
     */
    public function getDbCommand()
    {
        if (is_null($this->_dbCommand)) {
            $class = "\\ByJG\\DbMigration\\Commands\\" . ucfirst($this->_connection->getDriver()) . "Command";
            $this->_dbCommand = new $class($this->getDbDataset());
        }
        return $this->_dbCommand;
    }

    /**
     * Get the full path and name of the "base.sql" script
     *
     * @return string
     */
    public function getBaseSql()
    {
        return $this->_folder . "/base.sql";
    }

    /**
     * Get the full path script based on the version
     *
     * @param $version
     * @param $increment
     * @return string
     */
    public function getMigrationSql($version, $increment)
    {
        return $this->_folder 
            . "/migrations" 
            . "/" . ($increment < 0 ? "down" : "up")
            . "/" . str_pad($version, 5, '0', STR_PAD_LEFT) . ".sql";
    }

    /**
     * Create a fresh database based on the "base.sql" script and run all migration scripts
     *
     * @param int $upVersion
     */
    public function reset($upVersion = null)
    {
        $this->getDbCommand()->dropDatabase();
        $this->getDbCommand()->createDatabase();
        $this->getDbDataset()->execSQL(file_get_contents($this->getBaseSql()));
        $this->getDbCommand()->createVersion();
        $this->up($upVersion);
    }

    /**
     * Get the current database version
     *
     * @return int
     */
    public function getCurrentVersion()
    {
        return intval($this->getDbCommand()->getVersion());
    }

    /**
     * @param $currentVersion
     * @param $upVersion
     * @param $increment
     * @return bool
     */
    protected function canContinue($currentVersion, $upVersion, $increment)
    {
        $existsUpVersion = ($upVersion !== null);
        $compareVersion = strcmp(
                str_pad($currentVersion, 5, '0', STR_PAD_LEFT),
                str_pad($upVersion, 5, '0', STR_PAD_LEFT)
            ) == $increment;

        return !($existsUpVersion && $compareVersion);
    }

    /**
     * Method for execute the migration.
     *
     * @param int $upVersion
     * @param int $increment Can accept 1 for UP or -1 for down
     */
    protected function migrate($upVersion, $increment)
    {
        $currentVersion = $this->getCurrentVersion() + $increment;
        
        while ($this->canContinue($currentVersion, $upVersion, $increment)
            && file_exists($file = $this->getMigrationSql($currentVersion, $increment))
        ) {
            $this->getDbDataset()->execSQL(file_get_contents($file));
            $this->getDbCommand()->setVersion($currentVersion++);
        }
    }

    /**
     * Run all scripts to up the database version from current up to latest version or the specified version.
     *
     * @param int $upVersion
     */
    public function up($upVersion = null)
    {
        $this->migrate($upVersion, 1);
    }

    /**
     * Run all scripts to down the database version from current version up to the specified version.
     *
     * @param int $upVersion
     */
    public function down($upVersion)
    {
        $this->migrate($upVersion, -1);
    }
}
