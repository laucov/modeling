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
 * Test model that provides `Flight` instances.
 * 
 * @extends AbstractModel<Flight>
 */
class FlightModel extends AbstractModel
{
    protected string $entityName = Flight::class;
    protected string $primaryKey = 'id';
    protected string $tableName = 'flights';

    /**
     * Test one-to-one relationship with the "flights_statuses" table.
     */
    public function withStatus(null|callable $callback = null): static
    {
        $this->relateOneToOne('flights_statuses', 'id', 'flight_id');
        return $this;
    }

    /**
     * Test many-to-many relationship with the "flights_crew_members" table.
     */
    public function withCrewMembers(null|callable $callback = null): static
    {
        $this->relateOneToMany(
            FlightCrewMemberModel::class,
            'id',
            'flight_id',
            $callback,
        );
        return $this;
    }
}
