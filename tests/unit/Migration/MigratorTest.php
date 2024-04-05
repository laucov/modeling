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

    protected Migrator $migrator;

    protected MigrationRepository $repo;

    /**
     * @covers ::__construct
     * @covers ::createTable
     * @covers ::getLast
     * @covers ::getSchema
     * @covers ::getTable
     * @covers ::runMigration
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
        // Check null status.
        $this->assertNull($this->migrator->getLast());

        // Migrate to the specified index.
        $this->migrator->upgrade(1);
        $migration = $this->migrator->getLast();
        $this->assertIsObject($migration);
        $this->assertSame(1, $migration->index);
        $this->assertSame(0, $migration->batch);
        $this->assertSame(__DIR__ . '/migration-files/2023-03-31-105000-CreateFlightsTable.php', $migration->filename);
        $this->assertSame('CreateFlightsTable', $migration->name);

        // Continue migration.
        $this->migrator->upgrade(3);
        $migration = $this->migrator->getLast();
        $this->assertIsObject($migration);
        $this->assertSame(3, $migration->index);
        $this->assertSame(1, $migration->batch);
        $this->assertSame('CreatePilotsTable', $migration->name);

        // Check migration records.
        $table = new Table($this->conn, 'migrations');
        $this->assertSame(4, $table->countRecords('index'));

        // Migrate to the last file.
        $this->migrator->upgrade();
        $migration = $this->migrator->getLast();
        $this->assertIsObject($migration);
        $this->assertSame(5, $migration->index);
        $this->assertSame(2, $migration->batch);
        $this->assertSame('AddAircraftToPilots', $migration->name);
        $this->assertSame(6, $table->countRecords('index'));
    }

    protected function setUp(): void
    {
        // Create connection.
        $drivers = new DriverFactory();
        $this->conn = new Connection($drivers, 'sqlite::memory:');

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
