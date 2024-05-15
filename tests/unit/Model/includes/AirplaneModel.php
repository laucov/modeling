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

use Laucov\Modeling\Model\AbstractModel;

/**
 * Test model that provides `Airplane` instances.
 * 
 * @extends AbstractModel<Airplane>
 */
class AirplaneModel extends AbstractModel
{
    protected string $entityName = Airplane::class;
    protected string $primaryKey = 'id';
    protected string $tableName = 'airplanes';

    /**
     * Test one-to-many relationship with the "flights" table.
     */
    public function withFlights(null|callable $callback = null): static
    {
        $this->relateOneToMany(
            FlightModel::class,
            'id',
            'airplane_id',
            $callback,
        );
        return $this;
    }

    /**
     * Test one-to-one relationship with the "airplane_notes" table.
     */
    public function withNotes(): static
    {
        $this->relateOneToOne('airplanes_notes', 'id', 'airplane_id');
        return $this;
    }
}
