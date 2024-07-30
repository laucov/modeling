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

namespace Laucov\Modeling\Validation;

use Laucov\Db\Data\ConnectionFactory;
use Laucov\Modeling\Entity\AbstractEntity;
use Laucov\Modeling\Entity\ErrorMessage;
use Laucov\Modeling\Entity\Required;
use Laucov\Modeling\Model\AbstractModel;
use Laucov\Modeling\Validation\Rules\AbstractDatabaseRule;
use Laucov\Validation\Interfaces\RuleInterface;
use Laucov\Validation\Ruleset;

/**
 * Validates entities.
 */
class EntityValidator
{
    /**
     * Connection factory.
     */
    protected ConnectionFactory $connections;

    /**
     * Active entity.
     */
    protected AbstractEntity $entity;

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
    protected array $rules;

    /**
     * Set connection factory.
     */
    public function setConnectionFactory(ConnectionFactory $factory): static
    {
        $this->connections = $factory;
        return $this;
    }

    /**
     * Set the current entity.
     */
    public function setEntity(AbstractEntity $entity): static
    {
        if (isset($this->entity) && $entity::class !== $this->entity::class) {
            unset($this->properties, $this->propertyNames, $this->rules);
        }
        $this->entity = $entity;
        return $this;
    }

    /**
     * Validate current values.
     */
    public function validate(): bool
    {
        // Reset errors and get stored values.
        $this->entity->resetErrors();

        // Validate each value.
        foreach ($this->getPropertyNames() as $name) {
            $value = $this->entity->$name ?? null;
            $ruleset = $this->getRuleset($name);
            if (!$ruleset->validate($value)) {
                $this->entity->setErrors($name, ...$ruleset->getErrors());
            }
        }

        return !$this->entity->hasErrors();
    }

    /**
     * Get the entity's public properties from its reflection.
     */
    protected function extractProperties(): array
    {
        $reflection = new \ReflectionClass($this->entity::class);
        $filter = \ReflectionProperty::IS_PUBLIC;
        return $reflection->getProperties($filter);
    }

    /**
     * Get the entity's public property names from its reflection.
     */
    protected function extractPropertyNames(): array
    {
        $properties = $this->getProperties();
        return array_map(fn ($p) => $p->getName(), $properties);
    }

    /**
     * Get the entity's rules from its reflection.
     */
    protected function extractRules(): array
    {
        // Extract each properties' rules.
        $rules = [];
        foreach ($this->getProperties() as $prop) {
            // Get name.
            $name = $prop->getName();
            // Get attributes.
            $attributes = $prop->getAttributes();
            // Create ruleset.
            $ruleset = $this->createRuleset();
            $rules[$name] = $ruleset;
            // Process attributes.
            $is_required = false;
            $required_with = null;
            $obligatoriness_message = null;
            /** @var null|string|RuleInterface */
            $rule = null;
            foreach ($attributes as $attr) {
                $attr_name = $attr->getName();
                if (is_a($attr_name, Required::class, true)) {
                    // Remember obligatoriness.
                    /** @var Required */
                    $attr = $attr->newInstance();
                    $rule = 'required';
                    $is_required = true;
                    if (count($attr->with)) {
                        $required_with = $attr->with;
                    }
                } elseif (is_a($attr_name, RuleInterface::class, true)) {
                    // Add rule.
                    /** @var RuleInterface */
                    $rule = $attr->newInstance();
                    if (is_a($rule, AbstractDatabaseRule::class, true)) {
                        /** @var AbstractDatabaseRule $rule */
                        $rule->setConnectionFactory($this->connections);
                    }
                    $ruleset->addRule($rule);
                } elseif (is_a($attr_name, ErrorMessage::class, true)) {
                    // Add message.
                    /** @var ErrorMessage */
                    $attr = $attr->newInstance();
                    if ($rule === 'required') {
                        $obligatoriness_message = $attr->content;
                    } elseif ($rule instanceof RuleInterface) {
                        $rule->setMessage($attr->content);
                    }
                }
            }
            // Set obligatoriness.
            if ($is_required) {
                $ruleset->require($required_with, $obligatoriness_message);
            }
        }
        return $rules;
    }

    /**
     * Create a `Ruleset` object.
     */
    protected function createRuleset(): Ruleset
    {
        $ruleset = new Ruleset();
        return $ruleset;
    }

    /**
     * Get all the entity's public property reflections.
     * 
     * @return array<\ReflectionProperty>
     */
    protected function getProperties(): array
    {
        if (!isset($this->properties)) {
            $this->properties = $this->extractProperties();
        }
        return $this->properties;
    }

    /**
     * Get all the entity's public property names.
     */
    protected function getPropertyNames(): array
    {
        if (!isset($this->propertyNames)) {
            $this->propertyNames = $this->extractPropertyNames();
        }
        return $this->propertyNames;
    }

    /**
     * Get rules for a specific property.
     */
    protected function getRuleset(string $property_name): Ruleset
    {
        // Cache rules.
        if (!isset($this->rules)) {
            $this->rules = $this->extractRules();
        }
        $ruleset = $this->rules[$property_name];
        $ruleset->setData($this->entity);
        return $ruleset;
    }
}
