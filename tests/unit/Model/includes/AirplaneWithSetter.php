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

namespace Tests\Unit\Model;

/**
 * Extension of `Airplane` that catches unused values.
 * 
 * The setter is used to test if the model selects unnecessary columns.
 */
class AirplaneWithSetter extends Airplane
{
    /**
     * Property to store columns that shouldn't be selected.
     */
    protected array $unusedColumns = [];

    /**
     * Setter to catch unusable columns.
     * 
     * These columns should not be selected.
     */
    public function __set(string $name, mixed $value): void
    {
        $this->unusedColumns[] = $name;
        parent::__set($name, $value);
    }

    /**
     * Getter to check the unused columns.
     */
    public function getUnusedColumns(): array
    {
        return $this->unusedColumns;
    }
}
