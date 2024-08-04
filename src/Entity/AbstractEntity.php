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

namespace Laucov\Modeling\Entity;

use Laucov\Validation\Error;
use Laucov\Validation\Interfaces\RuleInterface;

/**
 * Represents a database record.
 */
abstract class AbstractEntity
{
    /**
     * Create an instance using the entries of an array.
     * 
     * @return CreationResult<static>
     */
    public static function createFromArray(array $data): mixed
    {
        $class_name = static::class;
        $entity = new $class_name();
        $update = static::updateFromArray($entity, $data);
        $result = new CreationResult();
        $result->entity = $update->entity;
        $result->typeErrors = $update->typeErrors;
        return $result;
    }

    /**
     * Update an instance using the entries of an array.
     * 
     * @template T of AbstractEntity
     * @param T $entity
     * @return CreationResult<T>
     */
    public static function updateFromArray(mixed $entity, array $data): mixed
    {
        if ($entity::class !== static::class) {
            $message = 'Cannot update %s using %s::updateFromArray().';
            $message = sprintf($message, $entity::class, static::class);
            throw new \InvalidArgumentException($message);
        }
        $errors = [];
        foreach ($data as $key => $value) {
            try {
                $entity->{$key} = $value;
            } catch (\TypeError $e) {
                $property = new \ReflectionProperty($entity, $key);
                $error = new TypeError();
                $error->actual = gettype($value);
                $error->error = $e;
                $error->expected = (string) $property->getType();
                $error->name = $key;
                $errors[] = $error;
            }
        }
        $result = new UpdateResult();
        $result->entity = $entity;
        $result->typeErrors = $errors;
        return $result;
    }

    /**
     * Cached data.
     * 
     * Stores the current "original" state of this entity.
     * 
     * Used to check if there are any entities
     */
    protected AbstractEntity $cache;

    /**
     * Stored errors.
     * 
     * @var array<string, RuleInterface[]>
     */
    protected array $errors = [];

    /**
     * Create the entity instance.
     */
    public function __construct()
    {
        // Cache for the first time.
        $this->cache = clone $this;
    }

    /**
     * Set the value of an inaccessible or non-existing property.
     */
    public function __set(string $name, mixed $value): void
    {
    }

    /**
     * Set the current entity state as the cached one.
     */
    public function cache(): void
    {
        foreach ($this->cache as $name => $value) {
            $this->cache->$name = $this->$name;
        }
    }

    /**
     * Get values which are different from the cached ones.
     */
    public function getEntries(): \stdClass
    {
        return ObjectReader::diff($this, $this->cache);
    }

    /**
     * Get all invalid properties names.
     * 
     * @return array<string>
     */
    public function getErrorKeys(): array
    {
        return array_keys($this->errors);
    }

    /**
     * Get current errors.
     * 
     * @return array<Error>
     */
    public function getErrors(string $property_name): array
    {
        return $this->errors[$property_name] ?? [];
    }

    /**
     * Check if the entity has errors.
     */
    public function hasErrors(null|string $name = null): bool
    {
        return $name !== null
            ? (isset($this->errors[$name]) && count($this->errors[$name]))
            : count($this->errors);
    }

    /**
     * Set errors for the specified property.
     */
    public function resetErrors(): static
    {
        $this->errors = [];
        return $this;
    }

    /**
     * Set errors for the specified property.
     */
    public function setErrors(string $property_name, Error ...$errors): static
    {
        $this->errors[$property_name] = $errors;
        return $this;
    }

    /**
     * Get the entity's data as an array.
     * 
     * @var array<string, mixed>
     */
    public function toArray(): array
    {
        return ObjectReader::toArray($this);
    }
}
