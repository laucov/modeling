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

use Laucov\Db\Data\ConnectionFactory;
use Laucov\Modeling\Model\AbstractModel;
use Laucov\Validation\AbstractRule;

/**
 * Indicates that a property must match one or more values from the database.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
abstract class AbstractDatabaseRule extends AbstractRule
{
    /**
     * Connection factory.
     */
    protected ConnectionFactory $connections;

    /**
     * Set the connection factory.
     */
    public function setConnectionFactory(ConnectionFactory $factory): static
    {
        $this->connections = $factory;
        return $this;
    }

    /**
     * Create a model instance.
     * 
     * @template T of AbstractModel
     * @param class-string<T>
     * @return T
     */
    protected function createModel(string $class_name): mixed
    {
        return new $class_name($this->connections);
    }
}