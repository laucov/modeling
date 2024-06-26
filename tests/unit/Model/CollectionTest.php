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
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Laucov\Modeling\Model\Collection
 */
class CollectionTest extends TestCase
{
    /**
     * @covers ::__construct
     * @covers ::count
     * @covers ::current
     * @covers ::get
     * @covers ::has
     * @covers ::key
     * @covers ::next
     * @covers ::rewind
     * @covers ::valid
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     */
    public function testCanInstantiate(): void
    {
        // Create collection.
        /** @var Collection<Product> */
        $collection = new Collection(
            2,
            4,
            7,
            16,
            $this->createProduct('ProdA', 23.99),
            $this->createProduct('ProdB', 6.41),
            $this->createProduct('ProdC', 1.99),
        );

        // Test properties.
        $this->assertSame(2, $collection->page);
        $this->assertSame(4, $collection->pageLength);
        $this->assertSame(7, $collection->filteredCount);
        $this->assertSame(16, $collection->storedCount);

        // Test counting.
        $this->assertSame(3, count($collection));

        // Test iteration.
        $actual = [];
        foreach ($collection as $i => $entity) {
            $actual[$i][] = $entity->code;
            $actual[$i][] = $entity->price;
        }
        $expected = [['ProdA', 23.99], ['ProdB', 6.41], ['ProdC', 1.99]];
        $this->assertSameSize($expected, $actual);
        foreach ($expected as $i => $v) {
            $this->assertSame($v[0], $actual[$i][0] ?? null);
            $this->assertSame($v[1], $actual[$i][1] ?? null);
        }

        // Test index access.
        foreach ($expected as $i => [$code, $price]) {
            $this->assertTrue($collection->has($i));
            $this->assertSame($code, $collection->get($i)->code ?? null);
            $this->assertSame($price, $collection->get($i)->price ?? null);
        }
        $this->assertFalse($collection->has($i + 1));
    }

    protected function createProduct(string $code, float $price): Product
    {
        $product = new Product();
        $product->code = $code;
        $product->price = $price;

        return $product;
    }
}

class Product extends AbstractEntity
{
    public string $code;
    public float $price = 0.00;
}
