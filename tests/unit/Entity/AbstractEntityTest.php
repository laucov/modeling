<?php

/**
 * This file is part of Laucov's Web Framework project.
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
 * @package web-framework
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
use Laucov\Validation\Rules\Length;
use Laucov\Validation\Rules\Regex;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Laucov\Modeling\Entity\AbstractEntity
 */
class AbstractEntityTest extends TestCase
{
    /**
     * @covers ::cache
     * @covers ::getEntries
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
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
     * @covers ::__construct
     * @covers ::cacheRules
     * @covers ::hasErrors
     * @covers ::getErrors
     * @covers ::getRuleset
     * @covers ::validate
     * @uses Laucov\Modeling\Entity\AbstractEntity::cache
     * @uses Laucov\Modeling\Entity\AbstractEntity::toArray
     * @uses Laucov\Modeling\Entity\ObjectReader::toArray
     * @uses Laucov\Validation\Rules\Length::__construct
     * @uses Laucov\Validation\Rules\Length::validate
     * @uses Laucov\Validation\Rules\Regex::__construct
     * @uses Laucov\Validation\Rules\Regex::validate
     * @uses Laucov\Validation\Ruleset::addRule
     * @uses Laucov\Validation\Ruleset::getErrors
     * @uses Laucov\Validation\Ruleset::validate
     */
    public function testCanValidate(): void
    {
        // Create entity instance.
        $entity = new class () extends AbstractEntity {
            #[Length(8, 16)]
            public string $login;
            #[Length(16, 24)]
            #[Regex('/[A-Z]+/')]
            #[Regex('/[a-z]+/')]
            #[Regex('/\d+/')]
            #[Regex('/[\!\#\$\%\&\@]+/')]
            public string $password;
        };

        // Validate valid values.
        $entity->login = 'john.doe';
        $entity->password = 'Secret_Pass#1234';
        $this->assertTrue($entity->validate());

        // Set invalid value.
        $entity->login = 'john.manoel.foobar.doe';
        $this->assertFalse($entity->validate());
        $errors = $entity->getErrors('login');
        $this->assertIsArray($errors);
        $this->assertCount(1, $errors);
        $this->assertInstanceOf(Length::class, $errors[0]);
        $errors = $entity->getErrors('password');
        $this->assertCount(0, $errors);

        // Fix and add another invalid value.
        $entity->login = 'john.foobar';
        $entity->password = 'ABCDEF';
        $this->assertFalse($entity->validate());
        $this->assertTrue($entity->hasErrors());
        $this->assertFalse($entity->hasErrors('login'));
        $errors = $entity->getErrors('login');
        $this->assertCount(0, $errors);
        $this->assertTrue($entity->hasErrors('password'));
        $errors = $entity->getErrors('password');
        $this->assertIsArray($errors);
        $this->assertCount(4, $errors);
        $this->assertInstanceOf(Length::class, $errors[0]);
        $this->assertInstanceOf(Regex::class, $errors[1]);
        $this->assertInstanceOf(Regex::class, $errors[2]);
        $this->assertInstanceOf(Regex::class, $errors[3]);

        // Fix again.
        $entity->password = 'SECUREpass@987654321';
        $this->assertTrue($entity->validate());
    }

    /**
     * @covers ::__set
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     */
    public function testIgnoresInexistentProperties(): void
    {
        // Create entity instance.
        $entity = new class () extends AbstractEntity {
            public string $title = 'Foobar: a study of Baz';
            public string $author = 'Doe, John';
        };

        // Set invalid properties.
        $entity->publisher = 'John Doe Printing Inc.';
        $this->assertFalse(isset($entity->publisher));
    }
}
