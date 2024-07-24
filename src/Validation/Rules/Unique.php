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

namespace Laucov\Modeling\Validation\Rules;

/**
 * Indicates that a property value must be unique in the database.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Unique extends AbstractDatabaseRule
{
    /**
     * Create the attribute instance.
     */
    public function __construct(
        /**
         * Model class name.
         * 
         * @var class-string<AbstractModel>
         */
        protected string $model,

        /**
         * Column to match.
         */
        protected string $column,

        /**
         * Method to call from the given model before querying values.
         */
        protected null|string $callback = null,
    ) {
    }

    /**
     * Get the rule's info.
     */
    public function getInfo(): array
    {
        return [];
    }

    /**
     * Validate a single value.
     */
    public function validate(mixed $value): bool
    {
        $model = $this->createModel($this->model);
        if ($this->callback !== null) {
            $model->{$this->callback}();
        }
        $values = $model
            ->withColumns($this->column)
            ->listAll()
            ->getColumn($this->column);
        return !in_array($value, $values, true);
    }
}
