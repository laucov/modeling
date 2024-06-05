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
use Laucov\Modeling\Entity\ErrorMessage;
use Laucov\Modeling\Entity\Required;
use Laucov\Modeling\Entity\TypeError;
use Laucov\Validation\Rules\Length;
use Laucov\Validation\Rules\Regex;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Laucov\Modeling\Entity\AbstractEntity
 * @covers \Laucov\Modeling\Entity\ErrorMessage
 */
class AbstractEntityTest extends TestCase
{
    /**
     * @covers ::cacheRules
     * @covers ::getRuleset
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     * @uses Laucov\Modeling\Entity\AbstractEntity::getProperties
     * @uses Laucov\Modeling\Entity\AbstractEntity::getPropertyNames
     * @uses Laucov\Modeling\Entity\AbstractEntity::getRuleset
     * @uses Laucov\Modeling\Entity\AbstractEntity::validate
     * @uses Laucov\Modeling\Entity\Required::__construct
     */
    public function testCachesRules(): void
    {
        // Create entity.
        // Override the `cacheRules()` method to test whether the entity caches
        // its rules instead of repeatedly fetching them as attributes.
        $entity = new class () extends AbstractEntity {
            public static int $cacheCount = 0;
            #[Required]
            #[Regex('/^\d+\-\d+$/')]
            public string $zip_code;
            #[Required(['foo', 'bar'])]
            #[Regex('/^[A-Z]+$/')]
            #[Length(3, 3)]
            public string $country;
            protected function cacheRules(): void
            {
                static::$cacheCount++;
                parent::cacheRules();
            }
        };

        // Test caching.
        $entity->validate();
        $entity->validate();
        $entity->validate();
        $entity->validate();
        $entity->validate();
        $entity->validate();
        $this->assertSame(1, $entity::class::$cacheCount);
    }

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
     * @covers ::createFromArray
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     * @uses Laucov\Modeling\Entity\AbstractEntity::__set
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
     * @covers ::cacheRules
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     * @uses Laucov\Modeling\Entity\AbstractEntity::getErrors
     * @uses Laucov\Modeling\Entity\AbstractEntity::getProperties
     * @uses Laucov\Modeling\Entity\AbstractEntity::getPropertyNames
     * @uses Laucov\Modeling\Entity\AbstractEntity::getRuleset
     * @uses Laucov\Modeling\Entity\AbstractEntity::validate
     * @uses Laucov\Modeling\Entity\ErrorMessage::__construct
     * @uses Laucov\Modeling\Entity\Required::__construct
     */
    public function testCanSetValidationMessages(): void
    {
        // Create entity instance.
        $entity = new class () extends AbstractEntity {
            #[Required]
            #[Length(0, 16)]
            public string $name_a;
            #[Required]
            #[ErrorMessage('This field is required!')]
            #[Length(0, 16)]
            #[ErrorMessage('Value too long!')]
            public string $name_b;
        };

        // Test "Required" error.
        $entity->validate();
        $errors_a = $entity->getErrors('name_a');
        $this->assertCount(1, $errors_a);
        $this->assertSame('required', $errors_a[0]->rule);
        $this->assertNull($errors_a[0]->message);
        $errors_b = $entity->getErrors('name_b');
        $this->assertCount(1, $errors_b);
        $this->assertSame('required', $errors_b[0]->rule);
        $this->assertSame('This field is required!', $errors_b[0]->message);

        // Test rule error.
        $entity->name_a = 'Very long value...';
        $entity->name_b = 'Another very long value...';
        $entity->validate();
        $errors_a = $entity->getErrors('name_a');
        $this->assertCount(1, $errors_a);
        $this->assertSame(Length::class, $errors_a[0]->rule);
        $this->assertNull($errors_a[0]->message);
        $errors_b = $entity->getErrors('name_b');
        $this->assertCount(1, $errors_b);
        $this->assertSame(Length::class, $errors_b[0]->rule);
        $this->assertSame('Value too long!', $errors_b[0]->message);
    }

    /**
     * @covers ::__construct
     * @covers ::hasErrors
     * @covers ::getErrorKeys
     * @covers ::getErrors
     * @covers ::getProperties
     * @covers ::getPropertyNames
     * @covers ::validate
     * @uses Laucov\Modeling\Entity\AbstractEntity::cache
     * @uses Laucov\Modeling\Entity\AbstractEntity::cacheRules
     * @uses Laucov\Modeling\Entity\AbstractEntity::getRuleset
     * @uses Laucov\Modeling\Entity\AbstractEntity::toArray
     * @uses Laucov\Modeling\Entity\ObjectReader::toArray
     * @uses Laucov\Modeling\Entity\Required::__construct
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
            public bool $has_email;
            #[Required(['has_email'])]
            public string $email;
        };

        // Validate valid values.
        $entity->login = 'john.doe';
        $entity->password = 'Secret_Pass#1234';
        $this->assertValidation($entity, []);

        // Set invalid value.
        $entity->login = 'john.manoel.foobar.doe';
        $this->assertValidation($entity, ['login' => [Length::class]]);

        // Fix and add another invalid value.
        $entity->login = 'john.foobar';
        $entity->password = 'ABCDEF';
        $this->assertValidation($entity, [
            'password' => [
                Length::class,
                Regex::class,
                Regex::class,
                Regex::class,
            ],
        ]);

        // Fix again.
        $entity->password = 'SECUREpass@987654321';
        $this->assertValidation($entity, []);

        // Test context data.
        $entity->has_email = true;
        $this->assertValidation($entity, ['email' => ['required_with']]);
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
        $this->assertFalse(isset($entity->publisher));
    }

    /**
     * @covers ::validate
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     * @uses Laucov\Modeling\Entity\AbstractEntity::cacheRules
     * @uses Laucov\Modeling\Entity\AbstractEntity::getErrorKeys
     * @uses Laucov\Modeling\Entity\AbstractEntity::getErrors
     * @uses Laucov\Modeling\Entity\AbstractEntity::getProperties
     * @uses Laucov\Modeling\Entity\AbstractEntity::getPropertyNames
     * @uses Laucov\Modeling\Entity\AbstractEntity::getRuleset
     * @uses Laucov\Modeling\Entity\AbstractEntity::hasErrors
     * @uses Laucov\Modeling\Entity\Required::__construct
     */
    public function testValidatesUnsetProperties(): void
    {
        // Create entity instance.
        $entity = new class () extends AbstractEntity {
            #[Required]
            public string $first_name;
            #[Required]
            public string $last_name;
        };

        // Validate.
        $this->assertValidation($entity, [
            'first_name' => ['required'],
            'last_name' => ['required'],
        ]);
    }

    /**
     * Assert that the entity contains the given errors.
     */
    protected function assertValidation(
        AbstractEntity $entity,
        array $expected_errors = [],
    ): void {
        // Compare validation result.
        $expected_success = count($expected_errors) < 1;
        $actual_success = $entity->validate();
        $message = 'Assert that the entity data %s valid.';
        $message = sprintf($message, $expected_success ? 'is' : 'is not');
        $this->assertSame($expected_success, $actual_success, $message);

        // Assert that has errors.
        if ($expected_success) {
            $message = 'Assert that the entity doesn\'t have errors.';
            $this->assertFalse($entity->hasErrors(), $message);
        } else {
            $message = 'Assert that the entity has errors.';
            $this->assertTrue($entity->hasErrors(), $message);
        }

        // Check error keys.
        $expected_keys = array_keys($expected_errors);
        $actual_keys = $entity->getErrorKeys();
        $missing = array_diff($expected_keys, $actual_keys);
        if (count($missing) > 0) {
            $message = 'Missing entity error keys: %s.';
            $this->fail(sprintf($message, implode(', ', $missing)));
        }
        $unexpected = array_diff($actual_keys, $expected_keys);
        if (count($unexpected) > 0) {
            $message = 'Found unexpected entity error keys: %s.';
            $this->fail(sprintf($message, implode(', ', $unexpected)));
        }

        // Get public property names.
        $reflection = new \ReflectionObject($entity);
        $filter = \ReflectionProperty::IS_PUBLIC;
        $properties = $reflection->getProperties($filter);
        $property_names = array_map(fn ($p) => $p->getName(), $properties);

        // Compare property names.
        $err_tpl = 'Failed to assert that entity property "%s" %s errors.';
        foreach ($property_names as $name) {
            $has_errors = $entity->hasErrors($name);
            $expects_errors = array_key_exists($name, $expected_errors);
            if ($has_errors && !$expects_errors) {
                $this->fail(sprintf($err_tpl, $name, 'does not have'));
            } elseif (!$has_errors && $expects_errors) {
                $this->fail(sprintf($err_tpl, $name, 'has'));
            }
        }

        // Check property errors.
        foreach ($expected_errors as $name => $expected_classes) {
            $actual_errors = $entity->getErrors($name);
            $this->assertIsArray($actual_errors);
            $msg_tpl = 'Assert that entity property "%s" #%s error is %s.';
            foreach ($expected_classes as $i => $expected_class) {
                $message = sprintf($msg_tpl, $name, $i, $expected_class);
                $actual_class = $actual_errors[$i]->rule;
                $this->assertSame($expected_class, $actual_class, $message);
            }
            // Check error list size.
            $this->assertSameSize($expected_classes, $actual_errors);
        }
    }
}
