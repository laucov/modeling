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

use Laucov\Modeling\Entity\AbstractEntity;
use Laucov\Modeling\Entity\Relationship;
use Laucov\Modeling\Model\Collection;
use Laucov\Modeling\Validation\Rules\Exists;

/**
 * Test entity that represents a flight.
 */
class Flight extends AbstractEntity
{
    /**
     * Flight primary key.
     */
    public int $id;

    /**
     * Airplane ID.
     */
    #[Exists(AirplaneModel::class, 'id')]
    public int $airplane_id;

    /**
     * Origin airport code.
     */
    public null|string $origin;

    /**
     * Destination airport code.
     */
    public null|string $destination;

    /**
     * Departure time.
     * 
     * Property create to test one-to-one relationships.
     */
    public null|string $departed_at;

    /**
     * Airplane current altitude.
     * 
     * Property create to test one-to-one relationships.
     */
    #[Relationship('flights_statuses')]
    public null|int $airplane_altitude;

    /**
     * Property to test the "flights_crew_members" table relationship handling.
     * 
     * @var Collection<FlightCrewMember>
     */
    #[Relationship]
    public Collection $flights_crew_members;
}
