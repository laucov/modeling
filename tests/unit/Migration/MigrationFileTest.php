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

use Laucov\Modeling\Migration\MigrationFile;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Laucov\Modeling\Migration\MigrationFile
 */
class MigrationFileTest extends TestCase
{
    /**
     * @covers ::__construct
     * @covers ::findClassName
     */
    public function testCanCreateFromFilename(): void
    {
        // Create instance.
        $directory = __DIR__ . '/migration-files';
        $filename = "{$directory}/2023-03-31-105000-CreateFlightsTable.php";
        $format = 'Y-m-d-His';
        $migration = new MigrationFile($filename, $format);
        
        // Get filename, name and datetime.
        $this->assertSame($filename, $migration->filename);
        $this->assertSame($format, $migration->timeFormat);
        $this->assertSame('CreateFlightsTable', $migration->name);
        $time = '31/03/2023 10:50:00 +00:00';
        $this->assertSame($time, $migration->date->format('d/m/Y H:i:s P'));

        // Get real class name.
        $class_name = 'Tests\Unit\Migration\CreateFlightsTable';
        $this->assertSame($class_name, $migration->className);
    }

    /**
     * @covers ::__construct
     * @covers ::findClassName
     */
    public function testFileMustDeclareAClass(): void
    {
        $directory = __DIR__ . '/migration-files';
        $filename = "{$directory}/2023-03-31-125100-InvalidMigration.php";
        $this->expectException(\RuntimeException::class);
        new MigrationFile($filename, 'Y-m-d-His');
    }

    /**
     * @covers ::__construct
     */
    public function testFileMustExist(): void
    {
        $this->expectException(\RuntimeException::class);
        new MigrationFile('/path/to/2024-InexistentMigration.php', 'Y');
    }

    /**
     * @covers ::__construct
     */
    public function testFilenameMustHaveAValidSynthax(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MigrationFile("/path/to/invalid_migration.php", '');
    }

    /**
     * @covers ::__construct
     */
    public function testFilenamePrefixMustMatchDateFormat(): void
    {
        $filename = __DIR__ . '/migration-files'
            . '/2023-03-31-105000-CreateFlightsTable.php';
        $this->expectException(\RuntimeException::class);
        new MigrationFile($filename, 'YmdHis');
    }
}
