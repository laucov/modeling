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

use Laucov\Modeling\Model\Collection;
use Laucov\Modeling\Entity\AbstractEntity;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Laucov\Modeling\Model\Collection
 */
class CollectionTest extends TestCase
{
    /**
     * Entity mocks.
     * 
     * @var array<CollectionTestEntity&MockObject>
     */
    protected array $entities;

    /**
     * @covers ::__construct
     * @covers ::count
     * @covers ::current
     * @covers ::get
     * @covers ::getColumn
     * @covers ::has
     * @covers ::key
     * @covers ::next
     * @covers ::rewind
     * @covers ::valid
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     */
    public function testCanIterateAndGetValues(): void
    {
        // Create collection.
        $collection = new Collection(
            2,
            4,
            7,
            16,
            $this->entities[0],
            $this->entities[1],
            $this->entities[2],
        );

        // Test properties.
        $this->assertSame(2, $collection->page);
        $this->assertSame(4, $collection->pageLength);
        $this->assertSame(7, $collection->filteredCount);
        $this->assertSame(16, $collection->storedCount);

        // Test counting.
        $this->assertSame(3, count($collection));

        // Test iteration.
        $expected_index = 0;
        foreach ($collection as $i => $entity) {
            $this->assertSame($expected_index, $i);
            $this->assertSame($this->entities[$i], $entity);
            $this->assertTrue($collection->has($i));
            $this->assertSame($this->entities[$i], $collection->get($i));
            $expected_index++;
        }
        $this->assertFalse($collection->has($i + 1));

        // Test getting columns.
        $this->entities[0]->name = 'John';
        $this->entities[0]->age = 42;
        $this->entities[1]->name = 'Mary';
        $this->entities[1]->age = 51;
        $this->entities[2]->name = 'Robert';
        $column = $collection->getColumn('name');
        $this->assertSame(['John', 'Mary', 'Robert'], $column);
        $column = $collection->getColumn('age');
        $this->assertSame([42, 51, null], $column);
    }

    /**
     * Create a test entity.
     */
    protected function mockEntity(): CollectionTestEntity&MockObject
    {
        return $this->createMock(CollectionTestEntity::class);
    }

    /**
     * This method is called before each test.
     */
    protected function setUp(): void
    {
        $this->entities = [
            $this->mockEntity(),
            $this->mockEntity(),
            $this->mockEntity(),
            $this->mockEntity(),
            $this->mockEntity(),
            $this->mockEntity(),
            $this->mockEntity(),
            $this->mockEntity(),
        ];
    }
}

/**
 * Test entity to mock.
 * 
 * Includes public properties to test.
 */
class CollectionTestEntity extends AbstractEntity
{
    public string $name;
    public int $age;
    public float $balance;
}
