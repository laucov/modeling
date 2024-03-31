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

namespace Tests\Unit\Entity;

use Laucov\Modeling\Entity\ObjectReader;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Laucov\Modeling\Entity\ObjectReader
 */
class ObjectReaderTest extends TestCase
{
    /**
     * @covers ::count
     * @covers ::diff
     * @covers ::toArray
     * @covers ::toObject
     */
    public function testCanExtractPublicPropertiesFromObjects(): void
    {
        // Create class to test scopes.
        $car = new class () {
            public string $manufacturer = 'Chevrolet';
            public string $model = 'Corsa';
            public int $year = 1998;
            protected string $prot = 'Protected';
            private string $priv = 'Protected';
            public function toArray(): array
            {
                return ObjectReader::toArray($this);
            }
            public function toObject(): object
            {
                return ObjectReader::toObject($this);
            }
        };

        // Test with array.
        $array = $car->toArray();
        $this->assertCount(3, $array);
        $this->assertSame('Chevrolet', $array['manufacturer']);
        $this->assertSame('Corsa', $array['model']);
        $this->assertSame(1998, $array['year']);

        // Test with object.
        $object = $car->toObject();
        $this->assertSame('Chevrolet', $object->manufacturer);
        $this->assertSame('Corsa', $object->model);
        $this->assertSame(1998, $object->year);
        $this->assertFalse(isset($object->prot));
        $this->assertFalse(isset($object->priv));

        // Count properties.
        $this->assertSame(3, ObjectReader::count($car));

        // Get difference.
        $other_car = new class () {
            public string $manufacturer = 'Chevrolet';
        };
        $some_car = new class () {
            public string $model = 'Corsa';
            public int $passengers = 2;
        };
        $diff = ObjectReader::diff($car, $other_car, $some_car);
        $this->assertCount(1, (array) $diff);
        $this->assertSame(1998, $diff->year);
    }
}
