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

use Laucov\Modeling\Entity\AbstractEntity;
use Laucov\Modeling\Entity\TypeError;
use Laucov\Validation\Error;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Laucov\Modeling\Entity\AbstractEntity
 */
class AbstractEntityTest extends TestCase
{
    /**
     * @covers ::__construct
     * @covers ::cache
     * @covers ::getEntries
     * @uses Laucov\Modeling\Entity\ObjectReader::diff
     */
    public function testCanCacheAndGetEntries(): void
    {
        // Create entity instance.
        $entity = new class () extends AbstractEntity {
            public string $firstName = 'John';
            public string $lastName = 'Doe';
            public int $age = 40;
        };

        // Test if caches default values.
        $entries = $entity->getEntries();
        $this->assertCount(0, (array) $entries);
        $entity->firstName = 'Josef';
        $entity->age = 45;
        $entries = $entity->getEntries();
        $this->assertCount(2, (array) $entries);
        $this->assertSame('Josef', $entries->firstName);
        $this->assertFalse(isset($entries->lastName));
        $this->assertSame(45, $entries->age);

        // Test caching.
        $entity->cache();
        $entity->lastName = 'Doevsky';
        $entries = $entity->getEntries();
        $this->assertCount(1, (array) $entries);
        $this->assertFalse(isset($entries->firstName));
        $this->assertSame('Doevsky', $entries->lastName);
        $this->assertFalse(isset($entries->age));
    }

    /**
     * @covers ::createFromArray
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     * @uses Laucov\Modeling\Entity\AbstractEntity::__set
     * @uses Laucov\Modeling\Entity\AbstractEntity::updateFromArray
     */
    public function testCanCreateFromArray(): void
    {
        // Create entity class.
        $entity = new class () extends AbstractEntity {
            public string $foo;
            public int $bar;
            public float $baz;
        };

        // Instantiate from array.
        $result = $entity::class::createFromArray([
            'foo' => 'This is a string.',
            'bar' => 123,
            'baz' => 123.001,
            'hey' => 'ho',
        ]);
        $instance = $result->entity;

        // Assert.
        $this->assertIsObject($entity);
        $this->assertInstanceOf($entity::class, $instance);
        $this->assertSame('This is a string.', $instance->foo);
        $this->assertSame(123, $instance->bar);
        $this->assertSame(123.001, $instance->baz);
        $this->assertIsArray($result->typeErrors);
        $this->assertCount(0, $result->typeErrors);

        // Test if catches type errors.
        $result = $entity::class::createFromArray([
            'baz' => [123],
            'foo' => ['This is not a string.'],
            'bar' => 123.902392,
        ]);
        $this->assertIsArray($result->typeErrors);
        $this->assertCount(2, $result->typeErrors);
        /** @var TypeError */
        $error = $result->typeErrors[0];
        $this->assertIsObject($error);
        $this->assertSame('array', $error->actual);
        $this->assertSame('float', $error->expected);
        $this->assertSame('baz', $error->name);
        $this->assertIsObject($error->error);
        $this->assertInstanceOf(\TypeError::class, $error->error);
        /** @var TypeError */
        $error = $result->typeErrors[1];
        $this->assertIsObject($error);
        $this->assertSame('array', $error->actual);
        $this->assertSame('string', $error->expected);
        $this->assertSame('foo', $error->name);
        $this->assertIsObject($error->error);
        $this->assertInstanceOf(\TypeError::class, $error->error);
    }

    /**
     * @covers ::getErrorKeys
     * @covers ::getErrors
     * @covers ::hasErrors
     * @covers ::resetErrors
     * @covers ::setErrors
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     */
    public function testCanGetAndSetErrors(): void
    {
        // Create entity class.
        $entity = new class () extends AbstractEntity {
            public string $foo;
            public int $bar;
            public float $baz;
        };

        // Get initial errors (none).
        $this->assertFalse($entity->hasErrors());
        $this->assertFalse($entity->hasErrors('foo'));
        $this->assertFalse($entity->hasErrors('bar'));
        $this->assertFalse($entity->hasErrors('baz'));
        $this->assertEmpty($entity->getErrorKeys());
        $this->assertEmpty($entity->getErrors('foo'));
        $this->assertEmpty($entity->getErrors('bar'));
        $this->assertEmpty($entity->getErrors('baz'));

        // Set new errors.
        $foo_errors = [
            $this->createMock(Error::class),
            $this->createMock(Error::class),
        ];
        $bar_errors = [$this->createMock(Error::class)];
        $entity
            ->setErrors('foo', ...$foo_errors)
            ->setErrors('bar', ...$bar_errors);

        // Get new errors.
        $this->assertTrue($entity->hasErrors());
        $this->assertTrue($entity->hasErrors('foo'));
        $this->assertTrue($entity->hasErrors('bar'));
        $this->assertFalse($entity->hasErrors('baz'));
        $this->assertSame(['foo', 'bar'], $entity->getErrorKeys());
        $this->assertSame($foo_errors, $entity->getErrors('foo'));
        $this->assertSame($bar_errors, $entity->getErrors('bar'));
        $this->assertEmpty($entity->getErrors('baz'));

        // Reset all errors.
        $entity->resetErrors();
        $this->assertFalse($entity->hasErrors());
        $this->assertFalse($entity->hasErrors('foo'));
        $this->assertFalse($entity->hasErrors('bar'));
        $this->assertFalse($entity->hasErrors('baz'));
        $this->assertEmpty($entity->getErrorKeys());
        $this->assertEmpty($entity->getErrors('foo'));
        $this->assertEmpty($entity->getErrors('bar'));
        $this->assertEmpty($entity->getErrors('baz'));
    }

    /**
     * @covers ::toArray
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     * @uses Laucov\Modeling\Entity\ObjectReader::toArray
     */
    public function testCanGetAsArray(): void
    {
        // Create entity instance.
        $entity = new class () extends AbstractEntity {
            public int $user_id = 21;
            public string $number = '5555555555554444';
            public string $cvc = '123';
            public string $expires_on = '2024-05-01';
            public string $unused_prop;
        };

        // Get as array.
        $array = $entity->toArray();
        $this->assertIsArray($array);
        $this->assertCount(4, $array);
        $this->assertSame($array['user_id'], 21);
        $this->assertSame($array['number'], '5555555555554444');
        $this->assertSame($array['cvc'], '123');
        $this->assertSame($array['expires_on'], '2024-05-01');
    }

    /**
     * @covers ::updateFromArray
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     * @uses Laucov\Modeling\Entity\AbstractEntity::__set
     */
    public function testCanUpdateFromArray(): void
    {
        // Create entity class.
        $entity = new class () extends AbstractEntity {
            public string $foo;
            public int $bar;
            public float $baz;
        };

        // Instantiate from array.
        $result = $entity::class::updateFromArray(
            clone $entity,
            [
                'foo' => 'This is a string.',
                'bar' => 123,
                'baz' => 123.001,
                'hey' => 'ho',
            ],
        );
        $instance = $result->entity;

        // Assert.
        $this->assertIsObject($entity);
        $this->assertInstanceOf($entity::class, $instance);
        $this->assertSame('This is a string.', $instance->foo);
        $this->assertSame(123, $instance->bar);
        $this->assertSame(123.001, $instance->baz);
        $this->assertIsArray($result->typeErrors);
        $this->assertCount(0, $result->typeErrors);

        // Test if catches type errors.
        $result = $entity::class::updateFromArray(
            clone $entity,
            [
                'baz' => [123],
                'foo' => ['This is not a string.'],
                'bar' => 123.902392,
            ],
        );
        $this->assertIsArray($result->typeErrors);
        $this->assertCount(2, $result->typeErrors);
        /** @var TypeError */
        $error = $result->typeErrors[0];
        $this->assertIsObject($error);
        $this->assertSame('array', $error->actual);
        $this->assertSame('float', $error->expected);
        $this->assertSame('baz', $error->name);
        $this->assertIsObject($error->error);
        $this->assertInstanceOf(\TypeError::class, $error->error);
        /** @var TypeError */
        $error = $result->typeErrors[1];
        $this->assertIsObject($error);
        $this->assertSame('array', $error->actual);
        $this->assertSame('string', $error->expected);
        $this->assertSame('foo', $error->name);
        $this->assertIsObject($error->error);
        $this->assertInstanceOf(\TypeError::class, $error->error);

        // Check if validates the entity used.
        $other = new class extends AbstractEntity {
            public string $abc = 'def';
        };
        $this->expectException(\InvalidArgumentException::class);
        $entity::class::updateFromArray($other, []);
    }

    /**
     * @covers ::__set
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     */
    public function testIgnoresInexistentPropertyAssignments(): void
    {
        // Create entity instance.
        $entity = new class () extends AbstractEntity {
            public string $title = 'Foobar: a study of Baz';
            public string $author = 'Doe, John';
        };

        // Set invalid properties.
        $entity->publisher = 'John Doe Printing Inc.';
        $this->assertFalse(property_exists($entity, 'publisher'));
        $this->assertNull($entity->{'publisher'} ?? null);
    }
}
