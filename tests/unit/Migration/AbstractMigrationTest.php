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
use Laucov\Db\Statement\ColumnDefinition as ColumnDef;
use Laucov\Modeling\Migration\AbstractMigration;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Laucov\Modeling\Migration\AbstractMigration
 */
class AbstractMigrationTest extends TestCase
{
    /**
     * @covers ::__construct
     */
    public function testCanMigrate(): void
    {
        // Create connection.
        $drivers = new DriverFactory();
        $conn = new Connection($drivers, 'sqlite::memory:');

        // Create a migration.
        $migration = new class ($conn) extends AbstractMigration {
            public function upgrade(): void
            {
                $this->schema->createTable(
                    'flights',
                    new ColumnDef('id', 'INTEGER', isPk: true, isAi: true),
                    new ColumnDef('call_sign', 'VARCHAR', 16),
                    new ColumnDef('aircraft', 'VARCHAR', 128),
                );
            }

            public function downgrade(): void
            {
                $this->schema->dropTable('flights');
            }
        };

        // Run the migration upgrade.
        $migration->upgrade();

        // Check upgrade result.
        $schema = new Schema($conn);
        $tables = $schema->getTables();
        $this->assertContains('flights', $tables);
        $columns = $schema->getColumns('flights');
        $this->assertContains('id', $columns);
        $this->assertContains('call_sign', $columns);
        $this->assertContains('aircraft', $columns);

        // Run the migration downgrade.
        $migration->downgrade();

        // Check downgrade result.
        $tables = $schema->getTables();
        $this->assertNotContains('flights', $tables);
    }
}
