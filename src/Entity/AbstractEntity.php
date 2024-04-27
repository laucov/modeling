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

use Laucov\Validation\Interfaces\RuleInterface;
use Laucov\Validation\Ruleset;

/**
 * Represents a database record.
 */
abstract class AbstractEntity
{
    /**
     * Cached data.
     * 
     * Stores the current "original" state of this entity.
     * 
     * Used to check if there are any entities
     */
    protected AbstractEntity $cache;

    /**
     * Errors found.
     * 
     * @var array<string, RuleInterface[]>
     */
    protected array $errors = [];
    
    /**
     * Reflection properties.
     * 
     * @var array<\ReflectionProperty>
     */
    protected array $properties;

    /**
     * Property names.
     * 
     * @var array<string>
     */
    protected array $propertyNames;

    /**
     * Cached property rules.
     * 
     * @var array<string, Ruleset>
     */
    protected array $rules = [];

    /**
     * Whether rules are already cached.
     */
    protected bool $rulesAreCached = false;

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
     * Get current errors.
     */
    public function getErrors(string $name): array
    {
        return $this->errors[$name] ?? [];
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
     * Get the entity's data as an array.
     * 
     * @var array<string, mixed>
     */
    public function toArray(): array
    {
        return ObjectReader::toArray($this);
    }

    /**
     * Validate current values.
     */
    public function validate(): bool
    {
        // Reset errors and get stored values.
        $this->errors = [];

        // Validate each value.
        foreach ($this->getPropertyNames() as $name) {
            $value = $this->$name ?? null;
            $ruleset = $this->getRuleset($name);
            if (!$ruleset->validate($value)) {
                $this->errors[$name] = $ruleset->getErrors();
            }
        }

        return count($this->errors) === 0;
    }

    /**
     * Cache the entity's rules.
     */
    protected function cacheRules(): void
    {
        // Extract each properties' rules.
        foreach ($this->getProperties() as $prop) {
            // Get name and instantiate the ruleset.
            $name = $prop->getName();
            $ruleset = new Ruleset();
            $ruleset->setData($this);
            $this->rules[$name] = $ruleset;
            // Get attributes and add each rule.
            /** @var \ReflectionAttribute[] */
            $attributes = $prop->getAttributes(
                RuleInterface::class,
                \ReflectionAttribute::IS_INSTANCEOF,
            );
            foreach ($attributes as $attribute) {
                $ruleset->addRule($attribute->newInstance());
            }
        }
    }

    /**
     * Get all the entity's public property reflections.
     * 
     * @return array<\ReflectionProperty>
     */
    protected function getProperties(): array
    {
        // Cache properties.
        if (!isset($this->properties)) {
            $reflection = new \ReflectionObject($this);
            $filter = \ReflectionProperty::IS_PUBLIC;
            $this->properties = $reflection->getProperties($filter);
        }

        return $this->properties;
    }

    /**
     * Get all the entity's public property names.
     */
    protected function getPropertyNames(): array
    {
        // Cache names.
        if (!isset($this->propertyNames)) {
            $props = $this->getProperties();
            $this->propertyNames = array_map(fn ($p) => $p->getName(), $props);
        }

        return $this->propertyNames;
    }

    /**
     * Get rules for a specific property.
     */
    protected function getRuleset(string $property_name): Ruleset
    {
        // Cache rules.
        if (!$this->rulesAreCached) {
            $this->cacheRules();
        }

        return $this->rules[$property_name];
    }
}
