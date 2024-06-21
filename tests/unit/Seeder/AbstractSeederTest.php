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

namespace Tests\Unit\Model;

use Laucov\Db\Data\Connection;
use Laucov\Modeling\Seeder\AbstractSeeder;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Laucov\Modeling\Seeder\AbstractSeeder
 */
class AbstractSeederTest extends TestCase
{
    /**
     * @covers ::__construct
     * @covers ::getTable
     */
    public function testCanSeed(): void
    {
        // Mock connection.
        $connection = $this->createMock(Connection::class);

        // Create the seeder instance.
        $seeder = new class ($connection) extends AbstractSeeder {
            public function seed(): void
            {
                $table = $this->getTable('books');
                $table->insertRecords(
                    [
                        'author' => 'John Doe',
                        'title' => 'The Art of Foobar',
                    ],
                    [
                        'author' => 'Nancy Roe',
                        'title' => 'Foo, Baz and Bar: a reflection',
                    ],
                );
            }
        };

        // Set expectations.
        $map = [
            ['books', '"books"'],
            ['author', '"author"'],
            ['title', '"title"'],
        ];
        $connection
            ->method('quoteIdentifier')
            ->willReturnMap($map);
        $connection
            ->expects($this->once())
            ->method('query')
            ->with(
                <<<SQL
                    INSERT INTO "books" ("author", "title")
                    VALUES
                    (:author_0, :title_0),
                    (:author_1, :title_1)
                    SQL,
                [
                    'author_0' => 'John Doe',
                    'title_0' => 'The Art of Foobar',
                    'author_1' => 'Nancy Roe',
                    'title_1' => 'Foo, Baz and Bar: a reflection',
                ],
            )
            ->willReturnSelf();
        $connection
            ->expects($this->once())
            ->method('getLastId');
        
        // Run the seeder.
        $seeder->seed();
    }
}
