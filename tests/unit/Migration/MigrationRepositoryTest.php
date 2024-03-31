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

use Laucov\Modeling\Migration\MigrationRepository;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Laucov\Modeling\Migration\MigrationRepository
 */
class MigrationRepositoryTest extends TestCase
{
    /**
     * @covers ::__construct
     * @covers ::addDirectory
     * @covers ::addFile
     * @covers ::listFiles
     * @uses Laucov\Modeling\Migration\MigrationFile::__construct
     * @uses Laucov\Modeling\Migration\MigrationFile::findClassName
     */
    public function testCanSetFiles(): void
    {
        // Create repository.
        $repo = new MigrationRepository('Y-m-d-His');

        // Add directory paths and individual files.
        $dir = __DIR__ . '/migration-files';
        $repo
            // Add with default format.
            ->addDirectory("{$dir}/dir-a")
            ->addFile("{$dir}/2023-03-31-105000-CreateFlightsTable.php")
            // Use custom date formats.
            ->addFile("{$dir}/20230331_103200-CreateUsersTable.php", 'Ymd_His')
            ->addDirectory("{$dir}/dir-b", 'YmdHis');
        
        // List all migrations.
        $list = $repo->listFiles();
        $this->assertIsArray($list);
        $this->assertCount(6, $list);

        // Check listed migrations.
        $expected = [
            [
                "{$dir}/20230331_103200-CreateUsersTable.php",
                'CreateUsersTable',
                '31/03/2023 10:32:00',
                'Tests\\Unit\\Migration\\CreateUsersTable',
            ],
            [
                "{$dir}/2023-03-31-105000-CreateFlightsTable.php",
                'CreateFlightsTable',
                '31/03/2023 10:50:00',
                'Tests\\Unit\\Migration\\CreateFlightsTable',
            ],
            [
                "{$dir}/dir-a/2023-03-31-105400-CreateAircraftsTable.php",
                'CreateAircraftsTable',
                '31/03/2023 10:54:00',
                'Tests\\Unit\\Migration\\CreateAircraftsTable',
            ],
            [
                "{$dir}/dir-b/20230331105500-CreatePilotsTable.php",
                'CreatePilotsTable',
                '31/03/2023 10:55:00',
                'Tests\\Unit\\Migration\\CreatePilotsTable',
            ],
            [
                "{$dir}/dir-b/20230331105600-CreateCustomersTable.php",
                'CreateCustomersTable',
                '31/03/2023 10:56:00',
                'Tests\\Unit\\Migration\\CreateCustomersTable',
            ],
            [
                "{$dir}/dir-a/2023-03-31-105700-AddAircraftToPilots.php",
                'AddAircraftToPilots',
                '31/03/2023 10:57:00',
                'Tests\\Unit\\Migration\\AddAircraftToPilots',
            ],
        ];
        foreach ($expected as $i => $v) {
            $this->assertArrayHasKey($i, $list);
            $file = $list[$i];
            $this->assertSame($v[0], $file->filename);
            $this->assertSame($v[1], $file->name);
            $this->assertSame($v[2], $file->date->format('d/m/Y H:i:s'));
            $this->assertSame($v[3], $file->className);
        }
    }
}
