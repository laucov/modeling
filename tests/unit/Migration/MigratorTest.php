<?php

/**
 * This file is part of Laucov's Modeling Library project.
 * 
 * Copyright 2024 Laucov Serviços de Tecnologia da Informação Ltda.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * 
 * @package modeling
 * 
 * @author Rafael Covaleski Pereira <rafael.covaleski@laucov.com>
 * 
 * @license <http://www.apache.org/licenses/LICENSE-2.0> Apache License 2.0
 * 
 * @copyright © 2024 Laucov Serviços de Tecnologia da Informação Ltda.
 */

declare(strict_types=1);

namespace Tests\Unit\Migration;

use Laucov\Db\Data\Connection;
use Laucov\Db\Data\Driver\DriverFactory;
use Laucov\Db\Query\Schema;
use Laucov\Db\Query\Table;
use Laucov\Modeling\Migration\MigrationRepository;
use Laucov\Modeling\Migration\Migrator;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Laucov\Modeling\Migration\Migrator
 */
final class MigratorTest extends TestCase
{
    protected Connection $conn;

    protected array $dbStates = [
        [
            'migrations' => ['index', 'batch', 'filename', 'name', 'time_format', 'run_at'],
            'users' => ['id', 'login', 'password_hash'],
        ],
        [
            'migrations' => ['index', 'batch', 'filename', 'name', 'time_format', 'run_at'],
            'users' => ['id', 'login', 'password_hash'],
            'flights' => ['call_sign', 'operator', 'aircraft'],
        ],
        [
            'migrations' => ['index', 'batch', 'filename', 'name', 'time_format', 'run_at'],
            'users' => ['id', 'login', 'password_hash'],
            'flights' => ['call_sign', 'operator', 'aircraft'],
            'aircrafts' => ['registration', 'manufacturer', 'model'],
        ],
        [
            'migrations' => ['index', 'batch', 'filename', 'name', 'time_format', 'run_at'],
            'users' => ['id', 'login', 'password_hash'],
            'flights' => ['call_sign', 'operator', 'aircraft'],
            'aircrafts' => ['registration', 'manufacturer', 'model'],
            'pilots' => ['name', 'flight_hours'],
        ],
        [
            'migrations' => ['index', 'batch', 'filename', 'name', 'time_format', 'run_at'],
            'users' => ['id', 'login', 'password_hash'],
            'flights' => ['call_sign', 'operator', 'aircraft'],
            'aircrafts' => ['registration', 'manufacturer', 'model'],
            'pilots' => ['name', 'flight_hours'],
            'customers' => ['name', 'birth'],
        ],
        [
            'migrations' => ['index', 'batch', 'filename', 'name', 'time_format', 'run_at'],
            'users' => ['id', 'login', 'password_hash'],
            'flights' => ['call_sign', 'operator', 'aircraft'],
            'aircrafts' => ['registration', 'manufacturer', 'model'],
            'pilots' => ['name', 'flight_hours', 'aircraft'],
            'customers' => ['name', 'birth'],
        ],
    ];

    protected Migrator $migrator;

    protected MigrationRepository $repo;

    protected Schema $schema;

    /**
     * @todo ::audit
     * @covers ::__construct
     * @covers ::createTable
     * @covers ::downgrade
     * @covers ::getLast
     * @covers ::getMigration
     * @covers ::getTable
     * @covers ::rewind
     * @covers ::upgrade
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     * @uses Laucov\Modeling\Migration\AbstractMigration::__construct
     * @uses Laucov\Modeling\Migration\MigrationFile::__construct
     * @uses Laucov\Modeling\Migration\MigrationFile::findClassName
     * @uses Laucov\Modeling\Migration\MigrationRepository::__construct
     * @uses Laucov\Modeling\Migration\MigrationRepository::addDirectory
     * @uses Laucov\Modeling\Migration\MigrationRepository::addFile
     * @uses Laucov\Modeling\Migration\MigrationRepository::listFiles
     */
    public function testCanMigrate(): void
    {
        // Create schema and table instance.
        $table = new Table($this->conn, 'migrations');

        // Check null status.
        $this->assertNull($this->migrator->getLast());
        $this->assertCount(0, $this->schema->getTables());

        // Run the first two migrations.
        $this->migrator->upgrade(2);
        $migration = $this->migrator->getLast();
        $this->assertIsObject($migration);
        $this->assertSame(1, $migration->index);
        $this->assertSame(0, $migration->batch);
        $filename = __DIR__
            . '/migration-files/2023-03-31-105000-CreateFlightsTable.php';
        $this->assertSame($filename, $migration->filename);
        $this->assertSame('CreateFlightsTable', $migration->name);
        $this->assertSame(2, $table->countRecords('index'));
        $this->assertDbState($this->dbStates[1], 'Upgrade to index 1');

        // Run another two migrations.
        $this->migrator->upgrade(2);
        $migration = $this->migrator->getLast();
        $this->assertIsObject($migration);
        $this->assertSame(3, $migration->index);
        $this->assertSame(1, $migration->batch);
        $this->assertSame('CreatePilotsTable', $migration->name);
        $this->assertSame(4, $table->countRecords('index'));
        $this->assertDbState($this->dbStates[3], 'Upgrade to index 3');

        // Migrate to the last file.
        $this->migrator->upgrade();
        $migration = $this->migrator->getLast();
        $this->assertIsObject($migration);
        $this->assertSame(5, $migration->index);
        $this->assertSame(2, $migration->batch);
        $this->assertSame('AddAircraftToPilots', $migration->name);
        $this->assertSame(6, $table->countRecords('index'));
        $this->assertDbState($this->dbStates[5], 'Upgrade to the last index');

        // Rewind the last migration batch.
        $this->migrator->rewind();
        $migration = $this->migrator->getLast();
        $this->assertIsObject($migration);
        $this->assertSame(3, $migration->index);
        $this->assertSame(1, $migration->batch);
        $this->assertSame('CreatePilotsTable', $migration->name);
        $this->assertSame(4, $table->countRecords('index'));
        $this->assertDbState($this->dbStates[3], 'Rewind last batch (2 to 1)');

        // Rewind the last two migration batches.
        $this->migrator->rewind(2);
        $this->assertNull($this->migrator->getLast());
        $this->assertCount(0, $this->schema->getTables());

        // Rewind with no active batches - does nothing.
        $this->migrator->rewind();
        $this->assertNull($this->migrator->getLast());
        $this->assertCount(0, $this->schema->getTables());

        // Upgrade again so we can test downgrades.
        $this->migrator->upgrade();
        $migration = $this->migrator->getLast();
        $this->assertIsObject($migration);
        $this->assertSame(5, $migration->index);

        // Downgrade a single migration.
        $this->migrator->downgrade();
        $migration = $this->migrator->getLast();
        $this->assertIsObject($migration);
        $this->assertSame(4, $migration->index);
        $this->assertSame(0, $migration->batch);
        $this->assertSame('CreateCustomersTable', $migration->name);
        $this->assertSame(5, $table->countRecords('index'));
        $this->assertDbState($this->dbStates[4], 'Downgrade a single index');

        // Downgrade multiple migrations.
        $this->migrator->downgrade(2);
        $migration = $this->migrator->getLast();
        $this->assertIsObject($migration);
        $this->assertSame(2, $migration->index);
        $this->assertSame(0, $migration->batch);
        $this->assertSame('CreateAircraftsTable', $migration->name);
        $this->assertSame(3, $table->countRecords('index'));
        $this->assertDbState($this->dbStates[2], 'Downgrade multiple indexes');

        // Perform a full downgrade.
        $this->migrator->downgrade(null);
        $this->assertNull($this->migrator->getLast());
        $this->assertCount(0, $this->schema->getTables());

        // Rewind with no active files - does nothing.
        $this->migrator->downgrade();
        $this->assertNull($this->migrator->getLast());
        $this->assertCount(0, $this->schema->getTables());
    }

    /**
     * Assert that the database has the given tables and columns.
     */
    protected function assertDbState(array $state, string $title): void
    {
        // Check table count.
        $tables = $this->schema->getTables();
        $count = count($state);
        $message = $title . PHP_EOL
            . "Assert that there are exactly {$count} tables.";
        $this->assertCount($count, $tables, $message);

        // Check table names.
        foreach ($state as $expt_table => $expt_cols) {
            $message = $title . PHP_EOL
                . "Assert that table '{$expt_table}' exists.";
            $this->assertContains($expt_table, $tables, $message);
            // Check column count.
            $columns = $this->schema->getColumns($expt_table);
            $count = count($expt_cols);
            $message = $title . PHP_EOL
                . "Assert that '{$expt_table}' has exactly {$count} columns.";
            $this->assertCount($count, $columns, $message);
            // Check column names.
            foreach ($expt_cols as $expt_col) {
                $message = $title . PHP_EOL
                    . "Assert that column '{$expt_table}.{$expt_col}' exists.";
                $this->assertContains($expt_col, $columns, $message);
            }
        }
    }

    protected function setUp(): void
    {
        // Create connection.
        $drivers = new DriverFactory();
        $this->conn = new Connection($drivers, 'sqlite::memory:');

        // Create schema instance.
        $this->schema = new Schema($this->conn);

        // Create migration repository.
        $directory = __DIR__ . '/migration-files';
        $this->repo = new MigrationRepository('Y-m-d-His');
        $this->repo
            ->addDirectory("{$directory}/dir-a", 'Y-m-d-His')
            ->addDirectory("{$directory}/dir-b", 'YmdHis')
            ->addFile("{$directory}/2023-03-31-105000-CreateFlightsTable.php", 'Y-m-d-His')
            ->addFile("{$directory}/20230331_103200-CreateUsersTable.php", 'Ymd_His');
        
        // Create migrator.
        $this->migrator = new Migrator($this->repo, $this->conn, 'migrations');
    }
}
