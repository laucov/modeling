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

use Laucov\Modeling\Entity\AbstractEntity;
use Laucov\Modeling\Entity\ErrorMessage;
use Laucov\Modeling\Entity\Required;
use Laucov\Validation\Interfaces\RuleInterface;
use Laucov\Validation\Ruleset;

/**
 * Validates entities.
 */
class EntityValidator
{
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
     * Set the current entity.
     */
    public function setEntity(AbstractEntity $entity): static
    {
        if (isset($this->entity) && $entity::class !== $this->entity::class) {
            unset($this->rules);
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
     * Cache the entity's rules.
     */
    protected function cacheRules(): void
    {
        // Extract each properties' rules.
        foreach ($this->getProperties() as $prop) {
            // Get name.
            $name = $prop->getName();
            // Get attributes.
            $attributes = $prop->getAttributes();
            // Create ruleset.
            $ruleset = $this->createRuleset();
            $this->rules[$name] = $ruleset;
            // Process attributes.
            $is_required = false;
            $required_with = null;
            $obligatoriness_message = null;
            /** @var null|string|RuleInterface */
            $rule = null;
            foreach ($attributes as $attr) {
                if (is_a($attr->getName(), Required::class, true)) {
                    // Remember obligatoriness.
                    /** @var Required */
                    $attr = $attr->newInstance();
                    $rule = 'required';
                    $is_required = true;
                    if (count($attr->with)) {
                        $required_with = $attr->with;
                    }
                } elseif (is_a($attr->getName(), RuleInterface::class, true)) {
                    // Add rule.
                    /** @var RuleInterface */
                    $attr = $attr->newInstance();
                    $ruleset->addRule($attr);
                    $rule = $attr;
                } elseif (is_a($attr->getName(), ErrorMessage::class, true)) {
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
    }

    /**
     * Create a `Ruleset` object.
     */
    public function createRuleset(): Ruleset
    {
        $ruleset = new Ruleset();
        $ruleset->setData($this->entity);
        return $ruleset;
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
            $reflection = new \ReflectionClass($this->entity::class);
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
        // Cache property names.
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
        if (!isset($this->rules)) {
            $this->cacheRules();
        }

        return $this->rules[$property_name];
    }
}
