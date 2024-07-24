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

namespace Tests\Unit\Validation;

use Laucov\Db\Data\ConnectionFactory;
use Laucov\Modeling\Entity\AbstractEntity;
use Laucov\Modeling\Entity\ErrorMessage;
use Laucov\Modeling\Entity\Required;
use Laucov\Modeling\Validation\EntityValidator;
use Laucov\Modeling\Validation\Rules\AbstractDatabaseRule;
use Laucov\Validation\Rules\Length;
use Laucov\Validation\Rules\Regex;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Laucov\Modeling\Validation\EntityValidator
 * @covers \Laucov\Modeling\Entity\ErrorMessage
 */
class EntityValidatorTest extends TestCase
{
    /**
     * Validator instance.
     */
    protected EntityValidator $validator;

    /**
     * @covers ::extractRules
     * @covers ::getRuleset
     * @covers ::setEntity
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     * @uses Laucov\Modeling\Entity\AbstractEntity::hasErrors
     * @uses Laucov\Modeling\Entity\AbstractEntity::resetErrors
     * @uses Laucov\Modeling\Entity\AbstractEntity::setErrors
     * @uses Laucov\Modeling\Validation\EntityValidator::createRuleset
     * @uses Laucov\Modeling\Validation\EntityValidator::getProperties
     * @uses Laucov\Modeling\Validation\EntityValidator::getPropertyNames
     * @uses Laucov\Modeling\Validation\EntityValidator::getRuleset
     * @uses Laucov\Modeling\Validation\EntityValidator::validate
     * @uses Laucov\Modeling\Entity\Required::__construct
     */
    public function testCachesRules(): void
    {
        // Create validator.
        // Override the `cacheRules()` method to test whether the entity caches
        // its rules instead of repeatedly fetching them as attributes.
        $validator = new class () extends EntityValidator {
            public static int $cacheCount = 0;
            protected function extractRules(): array
            {
                static::$cacheCount++;
                return parent::extractRules();
            }
        };

        // Create entity.
        $entity = new class () extends AbstractEntity {
            #[Required]
            #[Regex('/^\d+\-\d+$/')]
            public string $zip_code;
            #[Required(['foo', 'bar'])]
            #[Regex('/^[A-Z]+$/')]
            #[Length(3, 3)]
            public string $country;
        };
        $class_name = $entity::class;

        // Test caching with the same entity.
        $validator->setEntity($entity);
        $validator->validate();
        $validator->validate();
        $validator->validate();
        $validator->setEntity($entity);
        $validator->validate();
        $validator->validate();
        $validator->validate();
        $this->assertSame(1, $validator::class::$cacheCount);

        // Test caching with entity of same type.
        $validator->setEntity(new $class_name());
        $validator->validate();
        $validator->validate();
        $validator->validate();
        $this->assertSame(1, $validator::class::$cacheCount);

        // Create other entity.
        $other_entity = new class () extends AbstractEntity {
            #[Required]
            public string $name;
            #[Required]
            #[Length(2, 2)]
            public int $age;
        };

        // Test caching with entity of another type.
        $validator->setEntity($other_entity);
        $validator->validate();
        $validator->validate();
        $validator->validate();
        $this->assertSame(2, $validator::class::$cacheCount);
    }

    /**
     * @covers ::extractRules
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     * @uses Laucov\Modeling\Entity\AbstractEntity::getErrors
     * @uses Laucov\Modeling\Entity\AbstractEntity::hasErrors
     * @uses Laucov\Modeling\Entity\AbstractEntity::resetErrors
     * @uses Laucov\Modeling\Entity\AbstractEntity::setErrors
     * @uses Laucov\Modeling\Validation\EntityValidator::createRuleset
     * @uses Laucov\Modeling\Validation\EntityValidator::getProperties
     * @uses Laucov\Modeling\Validation\EntityValidator::getPropertyNames
     * @uses Laucov\Modeling\Validation\EntityValidator::getRuleset
     * @uses Laucov\Modeling\Validation\EntityValidator::setEntity
     * @uses Laucov\Modeling\Validation\EntityValidator::validate
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
        $this->validator
            ->setEntity($entity)
            ->validate();
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
        $this->validator->validate();
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
     * @covers ::createRuleset
     * @covers ::getProperties
     * @covers ::getPropertyNames
     * @covers ::setEntity
     * @covers ::validate
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     * @uses Laucov\Modeling\Entity\AbstractEntity::cache
     * @uses Laucov\Modeling\Entity\AbstractEntity::getErrorKeys
     * @uses Laucov\Modeling\Entity\AbstractEntity::getErrors
     * @uses Laucov\Modeling\Entity\AbstractEntity::hasErrors
     * @uses Laucov\Modeling\Entity\AbstractEntity::resetErrors
     * @uses Laucov\Modeling\Entity\AbstractEntity::setErrors
     * @uses Laucov\Modeling\Entity\AbstractEntity::toArray
     * @uses Laucov\Modeling\Validation\EntityValidator::extractRules
     * @uses Laucov\Modeling\Validation\EntityValidator::getRuleset
     * @uses Laucov\Modeling\Validation\EntityValidator::setEntity
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
     * @covers ::extractRules
     * @covers ::setConnectionFactory
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     * @uses Laucov\Modeling\Entity\AbstractEntity::hasErrors
     * @uses Laucov\Modeling\Entity\AbstractEntity::resetErrors
     * @uses Laucov\Modeling\Entity\AbstractEntity::setErrors
     * @uses Laucov\Modeling\Validation\EntityValidator::extractRules
     * @uses Laucov\Modeling\Validation\EntityValidator::createRuleset
     * @uses Laucov\Modeling\Validation\EntityValidator::getProperties
     * @uses Laucov\Modeling\Validation\EntityValidator::getPropertyNames
     * @uses Laucov\Modeling\Validation\EntityValidator::getRuleset
     * @uses Laucov\Modeling\Validation\EntityValidator::setConnectionFactory
     * @uses Laucov\Modeling\Validation\EntityValidator::setEntity
     * @uses Laucov\Modeling\Validation\EntityValidator::validate
     * @uses Laucov\Modeling\Validation\Rules\AbstractDatabaseRule::setConnectionFactory
     */
    public function testValidatesModelRules(): void
    {
        // Create entity instance.
        $entity = new class () extends AbstractEntity {
            #[EntityValidatorTestRule]
            public int $foobar_id = 42;
        };

        // Mock connection factory.
        $conn_factory = $this->createMock(ConnectionFactory::class);

        // Validate.
        EntityValidatorTestRule::$isValid = true;
        $result = $this->validator
            ->setConnectionFactory($conn_factory)
            ->setEntity($entity)
            ->validate();
        $this->assertTrue($result);
        EntityValidatorTestRule::$isValid = false;
        $this->assertFalse($this->validator->validate());
        $this->assertSame([$conn_factory], EntityValidatorTestRule::$factories);
    }

    /**
     * @covers ::validate
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     * @uses Laucov\Modeling\Validation\EntityValidator::extractRules
     * @uses Laucov\Modeling\Validation\EntityValidator::createRuleset
     * @uses Laucov\Modeling\Entity\AbstractEntity::getErrorKeys
     * @uses Laucov\Modeling\Entity\AbstractEntity::getErrors
     * @uses Laucov\Modeling\Validation\EntityValidator::getProperties
     * @uses Laucov\Modeling\Validation\EntityValidator::getPropertyNames
     * @uses Laucov\Modeling\Validation\EntityValidator::getRuleset
     * @uses Laucov\Modeling\Entity\AbstractEntity::hasErrors
     * @uses Laucov\Modeling\Entity\AbstractEntity::resetErrors
     * @uses Laucov\Modeling\Entity\AbstractEntity::setErrors
     * @uses Laucov\Modeling\Validation\EntityValidator::setEntity
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
        $actual_success = $this->validator
            ->setEntity($entity)
            ->validate();
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

    /**
     * This method is called before each test.
     */
    protected function setUp(): void
    {
        $this->validator = new EntityValidator();
    }
}

/**
 * Provides test functionalities to the entity validator.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class EntityValidatorTestRule extends AbstractDatabaseRule
{
    /**
     * Passed connection factories.
     */
    public static array $factories;

    /**
     * Value to return on validation calls.
     */
    public static bool $isValid;

    /**
     * Get the rule's info.
     * 
     * Returns an empty array.
     */
    public function getInfo(): array
    {
        return [];
    }
    
    /**
     * Set the connection factory.
     * 
     * Register each factory passed to the static factory list.
     */
    public function setConnectionFactory(ConnectionFactory $factory): static
    {
        static::$factories[] = $factory;
        return parent::setConnectionFactory($factory);
    }

    /**
     * Validate a single value.
     * 
     * Use the user-defined static validation value for test purposes.
     */
    public function validate(mixed $value): bool
    {
        return static::$isValid;
    }
}
