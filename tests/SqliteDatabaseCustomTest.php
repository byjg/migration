<?php

require_once 'SqliteDatabaseTest.php';

/**
 * @requires extension pdo_sqlite
 */
class SqliteDatabaseCustomTest extends SqliteDatabaseTest
{
    protected $migrationTable = "some_table_version";
}
