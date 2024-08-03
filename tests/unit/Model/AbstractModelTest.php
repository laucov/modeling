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

use Laucov\Db\Data\Connection;
use Laucov\Db\Data\ConnectionFactory;
use Laucov\Db\Data\Driver\DriverFactory;
use Laucov\Modeling\Model\AbstractModel;
use Laucov\Modeling\Model\BatchUpdateResult;
use Laucov\Modeling\Model\Collection;
use Laucov\Modeling\Model\DeletionFilter;
use Laucov\Modeling\Model\SearchMode;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

require __DIR__ . '/includes/Airplane.php';
require __DIR__ . '/includes/AirplaneModel.php';
require __DIR__ . '/includes/AirplaneWithSetter.php';
require __DIR__ . '/includes/Flight.php';
require __DIR__ . '/includes/FlightCrewMember.php';
require __DIR__ . '/includes/FlightCrewMemberModel.php';
require __DIR__ . '/includes/FlightModel.php';

/**
 * @coversDefaultClass \Laucov\Modeling\Model\AbstractModel
 * @covers \Laucov\Modeling\Entity\Relationship
 */
class AbstractModelTest extends TestCase
{
    /**
     * Airplane model.
     */
    protected AirplaneModel $airplanes;

    /**
     * Connection instance.
     */
    protected Connection $conn;

    /**
     * Connection factory instance.
     */
    protected ConnectionFactory $conns;

    /**
     * Flight model.
     */
    protected FlightModel $flights;

    /**
     * Provides arguments to retrieve duplicated entries.
     */
    public function duplicatedAirplaneModelRetrievalProvider(): array
    {
        return [
            [
                ['SR22', 'EMB-820C Caraja'],
                ['EMB-820C Caraja', '737 MAX 8'],
            ],
            [
                ['PA-46-500TP', '72-500'],
                ['A320-251N', 'foo', 'bar'],
            ],
        ];
    }

    /**
     * Provides data for testing the model search features.
     */
    public function searchProvider(): array
    {
        return [
            [
                [2, 3, 4, 6, 10, 12, 13],
                [['manufacturer', 'A', SearchMode::STARTS_WITH]],
            ],
            [
                [8, 12],
                [
                    ['registration', 'C', SearchMode::CONTAINS],
                    ['manufacturer', 'us', SearchMode::CONTAINS],
                ],
            ],
            [[10], [['model', '500', SearchMode::ENDS_WITH]]],
            [
                [3, 13],
                [
                    ['registration', 'P', SearchMode::STARTS_WITH],
                    ['model', 'A320-251N', SearchMode::EQUAL_TO],
                ],
            ]
        ];
    }

    /**
     * @covers ::createEntity
     * @covers ::createEntityFromArray
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     * @uses Laucov\Modeling\Entity\AbstractEntity::__set
     * @uses Laucov\Modeling\Entity\AbstractEntity::createFromArray
     * @uses Laucov\Modeling\Model\AbstractModel::__construct
     * @uses Laucov\Modeling\Model\AbstractModel::cacheEntityKeys
     * @uses Laucov\Modeling\Model\AbstractModel::createTable
     * @uses Laucov\Modeling\Model\AbstractModel::createValidator
     * @uses Laucov\Modeling\Model\AbstractModel::prefix
     */
    public function testCanCreateEntity(): void
    {
        // Create with default values.
        $entity = $this->airplanes->createEntity();
        $this->assertIsObject($entity);
        $this->assertInstanceOf(Airplane::class, $entity);
        $this->assertNull($entity->id ?? null);
        $this->assertNull($entity->manufacturer ?? null);
        $this->assertNull($entity->model ?? null);
        $this->assertNull($entity->registration ?? null);

        // Create with custom values.
        $result = $this->airplanes->createEntityFromArray([
            'manufacturer' => 'John Doe Aviation',
            'model' => 'Little John B',
            'registration' => 'FO-BAR',
            'some_prop' => 'foobar',
        ]);
        $this->assertCount(0, $result->typeErrors);
        $entity = $result->entity;
        $this->assertIsObject($entity);
        $this->assertInstanceOf(Airplane::class, $entity);
        $this->assertNull($entity->id ?? null);
        $this->assertSame('John Doe Aviation', $entity->manufacturer ?? null);
        $this->assertSame('Little John B', $entity->model ?? null);
        $this->assertSame('FO-BAR', $entity->registration ?? null);
        $this->assertObjectNotHasProperty('some_prop', $entity);
    }

    /**
     * @covers ::applyDeletionFilter
     * @covers ::delete
     * @covers ::erase
     * @covers ::exists
     * @covers ::filterDeleted
     * @uses Laucov\Modeling\Entity\Relationship::__construct
     * @uses Laucov\Modeling\Model\AbstractModel::__construct
     * @uses Laucov\Modeling\Model\AbstractModel::cacheEntityKeys
     * @uses Laucov\Modeling\Model\AbstractModel::createTable
     * @uses Laucov\Modeling\Model\AbstractModel::createValidator
     * @uses Laucov\Modeling\Model\AbstractModel::prefix
     */
    public function testCanDeleteAndErase(): void
    {
        // Delete single record.
        $this->airplanes->delete('1');
        // Delete multiple records.
        $this->airplanes->delete('2', '3');
        // Erase records.
        $this->airplanes
            ->filterDeleted(DeletionFilter::SHOW)
            ->erase('1');

        // Turn off filter resetting for the next operations.
        $this->airplanes->keepDeletionFilter = true;

        // Check active records.
        $this->assertTrue($this->airplanes->exists()); // Active records exist
        $this->assertFalse($this->airplanes->exists('999')); // Never existed
        $this->assertFalse($this->airplanes->exists('1')); // Erased
        $this->assertFalse($this->airplanes->exists('1', '2')); // Erased + Deleted
        $this->assertFalse($this->airplanes->exists('2', '3')); // Deleted
        $this->assertFalse($this->airplanes->exists('3', '4')); // Deleted + Active
        $this->assertTrue($this->airplanes->exists('4', '5')); // Active

        // Check all records.
        $this->airplanes->filterDeleted(DeletionFilter::SHOW);
        $this->assertTrue($this->airplanes->exists()); // Records exist
        $this->assertFalse($this->airplanes->exists('999'));
        $this->assertFalse($this->airplanes->exists('1'));
        $this->assertFalse($this->airplanes->exists('1', '2'));
        $this->assertTrue($this->airplanes->exists('2', '3'));
        $this->assertTrue($this->airplanes->exists('3', '4'));
        $this->assertTrue($this->airplanes->exists('4', '5'));

        // Check deleted records.
        $this->airplanes->filterDeleted(DeletionFilter::SHOW_EXCLUSIVELY);
        $this->assertTrue($this->airplanes->exists()); // Deleted records exist
        $this->assertFalse($this->airplanes->exists('999'));
        $this->assertFalse($this->airplanes->exists('1'));
        $this->assertFalse($this->airplanes->exists('1', '2'));
        $this->assertTrue($this->airplanes->exists('2', '3'));
        $this->assertFalse($this->airplanes->exists('3', '4'));
        $this->assertFalse($this->airplanes->exists('4', '5'));

        // Delete all.
        $this->airplanes->filterDeleted(DeletionFilter::HIDE);
        $this->airplanes->delete(...array_map('strval', range(1, 13)));
        $this->assertFalse($this->airplanes->exists()); // No active records
        $this->airplanes->filterDeleted(DeletionFilter::SHOW);
        $this->assertTrue($this->airplanes->exists()); // No active records

        // Turn on filter resetting again.
        $this->airplanes->keepDeletionFilter = false;
    }

    /**
     * @covers ::createModel
     * @covers ::fetchRelationship
     * @covers ::getEntities
     * @covers ::relateOneToMany
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     * @uses Laucov\Modeling\Entity\AbstractEntity::__set
     * @uses Laucov\Modeling\Entity\Relationship::__construct
     * @uses Laucov\Modeling\Model\AbstractModel::__construct
     * @uses Laucov\Modeling\Model\AbstractModel::applyDeletionFilter
     * @uses Laucov\Modeling\Model\AbstractModel::cacheEntityKeys
     * @uses Laucov\Modeling\Model\AbstractModel::createCollection
     * @uses Laucov\Modeling\Model\AbstractModel::createTable
     * @uses Laucov\Modeling\Model\AbstractModel::createValidator
     * @uses Laucov\Modeling\Model\AbstractModel::getDefaultColumns
     * @uses Laucov\Modeling\Model\AbstractModel::getEntities
     * @uses Laucov\Modeling\Model\AbstractModel::getEntity
     * @uses Laucov\Modeling\Model\AbstractModel::list
     * @uses Laucov\Modeling\Model\AbstractModel::listAll
     * @uses Laucov\Modeling\Model\AbstractModel::prefix
     * @uses Laucov\Modeling\Model\AbstractModel::resetPagination
     * @uses Laucov\Modeling\Model\AbstractModel::retrieve
     * @uses Laucov\Modeling\Model\AbstractModel::withColumns
     * @uses Laucov\Modeling\Model\Collection::__construct
     * @uses Laucov\Modeling\Model\Collection::count
     * @uses Laucov\Modeling\Model\Collection::current
     * @uses Laucov\Modeling\Model\Collection::get
     * @uses Laucov\Modeling\Model\Collection::key
     * @uses Laucov\Modeling\Model\Collection::next
     * @uses Laucov\Modeling\Model\Collection::rewind
     * @uses Laucov\Modeling\Model\Collection::valid
     */
    public function testCanFetchOneToManyRelationships(): void
    {
        // Retrieve without flights.
        $airplane = $this->airplanes->retrieve('3');
        $this->assertNull($airplane->flights ?? null);

        // Retrieve with flights.
        $airplane = $this->airplanes
            ->withFlights()
            ->retrieve('3');
        $flights = $airplane->flights;
        $this->assertNotNull($flights);
        $this->assertCount(3, $flights);
        $airplane_flight_ids = [2, 4, 6];
        foreach ($flights as $i => $flight) {
            $this->assertSame(3, $flight->airplane_id);
            $this->assertSame($airplane_flight_ids[$i], $flight->id);
        }

        // List without flights.
        $airplanes = $this->airplanes->listAll();
        foreach ($airplanes as $entity) {
            $this->assertNull($entity->flights ?? null);
        }

        // List with flights.
        $airplanes = $this->airplanes
            ->withFlights()
            ->listAll();
        $airplanes_flight_ids = [
            1 => [1, 9],
            2 => [5],
            3 => [2, 4, 6],
            4 => [],
            5 => [],
            6 => [],
            7 => [],
            8 => [],
            9 => [7],
            10 => [3, 8],
            11 => [],
            12 => [],
            13 => [],
        ];
        foreach ($airplanes as $entity) {
            $flights = $entity->flights;
            $this->assertNotNull($flights ?? null);
            $entity_flight_ids = $airplanes_flight_ids[$entity->id];
            $this->assertSameSize($entity_flight_ids, $flights);
            foreach ($flights as $i => $flight) {
                $this->assertSame($entity_flight_ids[$i], $flight->id);
            }
        }

        // List using callback.
        $airplanes = $this->airplanes
            ->withFlights(function (FlightModel $model) {
                $model->withColumns('origin');
            })
            ->listAll();
        $this->assertNull($airplanes->get(0)->flights->get(0)->id ?? null);
        $this->assertSame('GIG', $airplanes->get(0)->flights->get(0)->origin);

        // Test nested callbacks.
        $airplanes = $this->airplanes
            ->withFlights(function (FlightModel $m1) {
                $m1->withCrewMembers(function (FlightCrewMemberModel $m2) {
                    $m2->withColumns('name');
                });
            })
            ->listAll();
        $member = $airplanes
            ->get(9)
            ->flights
            ->get(0)
            ->flights_crew_members
            ->get(1);
        $this->assertSame('Jane Baz', $member->name);
        $this->assertSame(null, $member->id ?? null);
    }

    /**
     * @covers ::getDefaultColumns
     * @covers ::relateOneToOne
     * @covers ::prefix
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     * @uses Laucov\Modeling\Entity\AbstractEntity::__set
     * @uses Laucov\Modeling\Entity\Relationship::__construct
     * @uses Laucov\Modeling\Model\AbstractModel::__construct
     * @uses Laucov\Modeling\Model\AbstractModel::applyDeletionFilter
     * @uses Laucov\Modeling\Model\AbstractModel::cacheEntityKeys
     * @uses Laucov\Modeling\Model\AbstractModel::createCollection
     * @uses Laucov\Modeling\Model\AbstractModel::createTable
     * @uses Laucov\Modeling\Model\AbstractModel::createValidator
     * @uses Laucov\Modeling\Model\AbstractModel::getEntities
     * @uses Laucov\Modeling\Model\AbstractModel::getEntity
     * @uses Laucov\Modeling\Model\AbstractModel::list
     * @uses Laucov\Modeling\Model\AbstractModel::listAll
     * @uses Laucov\Modeling\Model\AbstractModel::resetPagination
     * @uses Laucov\Modeling\Model\AbstractModel::retrieve
     * @uses Laucov\Modeling\Model\AbstractModel::sort
     * @uses Laucov\Modeling\Model\Collection::__construct
     * @uses Laucov\Modeling\Model\Collection::current
     * @uses Laucov\Modeling\Model\Collection::get
     * @uses Laucov\Modeling\Model\Collection::next
     * @uses Laucov\Modeling\Model\Collection::rewind
     * @uses Laucov\Modeling\Model\Collection::valid
     */
    public function testCanFetchOneToOneRelationships(): void
    {
        // Retrieve without joining status.
        $flight = $this->flights->retrieve('5');
        $this->assertNull($flight->airplane_altitude ?? null);

        // Retrieve joining status.
        $flight = $this->flights
            ->withStatus()
            ->retrieve('5');
        $this->assertSame(15014, $flight->airplane_altitude);

        // List without joining status (one-to-one).
        $collection = $this->flights->listAll();
        foreach ($collection as $entity) {
            $this->assertNull($entity->airplane_altitude ?? null);
        }

        // Set the list of expected altitudes.
        $expected_altitudes = [
            5 => 15014,
            6 => 9574,
            7 => 11477,
            8 => 9315,
            9 => 14976,
        ];

        // List joining statuses (one-to-one).
        $collection = $this->flights
            ->withStatus()
            ->listAll();
        foreach ($collection as $flight) {
            $id = $flight->id;
            if (array_key_exists($id, $expected_altitudes)) {
                $altitude = $flight->airplane_altitude;
                $this->assertSame($expected_altitudes[$id], $altitude);
            } else {
                $this->assertNull($entity->airplane_altitude ?? null);
            }
        }

        // Test sorting after joining notes (one-to-one).
        $collection = $this->flights
            ->withStatus()
            ->sort('airplane_altitude', true)
            ->listAll();
        $this->assertSame(5, $collection->get(0)->id);
        $this->assertSame(9, $collection->get(1)->id);
        $this->assertSame(7, $collection->get(2)->id);
        $this->assertSame(6, $collection->get(3)->id);
        $this->assertSame(8, $collection->get(4)->id);
        $this->assertNull($collection->get(5)->airplane_altitude);

        // Test if prefixes selected primary keys.
        $flight = $this->flights
            ->withStatus()
            ->search('airplane_altitude', '15014', SearchMode::EQUAL_TO)
            ->withColumns('id')
            ->listAll();
        $this->assertSame(5, $flight->get(0)->id);
    }

    /**
     * @covers ::insert
     * @covers ::insertBatch
     * @covers ::update
     * @covers ::updateBatch
     * @covers ::withValue
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     * @uses Laucov\Modeling\Entity\AbstractEntity::__set
     * @uses Laucov\Modeling\Entity\AbstractEntity::getEntries
     * @uses Laucov\Modeling\Entity\AbstractEntity::hasErrors
     * @uses Laucov\Modeling\Entity\AbstractEntity::resetErrors
     * @uses Laucov\Modeling\Entity\AbstractEntity::setErrors
     * @uses Laucov\Modeling\Entity\AbstractEntity::toArray
     * @uses Laucov\Modeling\Entity\ObjectReader::count
     * @uses Laucov\Modeling\Entity\ObjectReader::diff
     * @uses Laucov\Modeling\Entity\ObjectReader::toArray
     * @uses Laucov\Modeling\Entity\Relationship::__construct
     * @uses Laucov\Modeling\Model\AbstractModel::__construct
     * @uses Laucov\Modeling\Model\AbstractModel::applyDeletionFilter
     * @uses Laucov\Modeling\Model\AbstractModel::cacheEntityKeys
     * @uses Laucov\Modeling\Model\AbstractModel::createCollection
     * @uses Laucov\Modeling\Model\AbstractModel::createTable
     * @uses Laucov\Modeling\Model\AbstractModel::createValidator
     * @uses Laucov\Modeling\Model\AbstractModel::getDefaultColumns
     * @uses Laucov\Modeling\Model\AbstractModel::getEntity
     * @uses Laucov\Modeling\Model\AbstractModel::getEntities
     * @uses Laucov\Modeling\Model\AbstractModel::prefix
     * @uses Laucov\Modeling\Model\AbstractModel::retrieve
     * @uses Laucov\Modeling\Model\AbstractModel::retrieveBatch
     * @uses Laucov\Modeling\Validation\EntityValidator::createRuleset
     * @uses Laucov\Modeling\Validation\EntityValidator::extractProperties
     * @uses Laucov\Modeling\Validation\EntityValidator::extractPropertyNames
     * @uses Laucov\Modeling\Validation\EntityValidator::extractRules
     * @uses Laucov\Modeling\Validation\EntityValidator::getProperties
     * @uses Laucov\Modeling\Validation\EntityValidator::getPropertyNames
     * @uses Laucov\Modeling\Validation\EntityValidator::getRuleset
     * @uses Laucov\Modeling\Validation\EntityValidator::setEntity
     * @uses Laucov\Modeling\Validation\EntityValidator::validate
     * @uses Laucov\Validation\Rules\Regex::__construct
     * @uses Laucov\Validation\Rules\Regex::validate
     * @uses Laucov\Validation\Ruleset::addRule
     * @uses Laucov\Validation\Ruleset::getErrors
     * @uses Laucov\Validation\Ruleset::validate
     */
    public function testCanInsertAndUpdate(): void
    {
        // Create entities.
        $airplane_a = new Airplane();
        $airplane_a->registration = 'PR-AKA';
        $airplane_a->manufacturer = 'ATR';
        $airplane_a->model = '72-600';

        // Insert single record.
        $this->assertTrue($this->airplanes->insert($airplane_a));
        $this->assertSame(14, $airplane_a->id);

        // Test validation.
        $airplane_b = new Airplane();
        $airplane_b->registration = 'PR-TOOLONG';
        $airplane_b->manufacturer = 'Airbus';
        $airplane_b->model = 'A320-214';
        $this->assertFalse($this->airplanes->insert($airplane_b));
        $this->assertFalse(isset($airplane_b->id));
        $airplane_b->registration = 'PR-MYR';
        $this->assertTrue($this->airplanes->insert($airplane_b));
        $this->assertSame(15, $airplane_b->id);

        // Test batch insert/validation.
        $airplane_c = new Airplane();
        $airplane_c->registration = 'LV-TOOLONG';
        $airplane_c->manufacturer = 'Beech';
        $airplane_c->model = 'King Air B200GT';
        $airplane_d = new Airplane();
        $airplane_d->registration = 'PS-GPA';
        $airplane_d->manufacturer = 'Boeing';
        $airplane_d->model = '737 MAX 8';
        $this->assertFalse($this->airplanes->insertBatch($airplane_c, $airplane_d));
        $airplane_c->registration = 'LV-BMS';
        $this->assertTrue($this->airplanes->insertBatch($airplane_c, $airplane_d));
        $this->assertSame('17', $this->conn->getLastId());
        $this->assertFalse(isset($airplane_c->id));
        $this->assertFalse(isset($airplane_d->id));

        // Test updating.
        $airplane_e = $this->airplanes->retrieve('17');
        $this->assertNull($this->airplanes->update($airplane_e));
        $airplane_e->registration = 'AA-AAAA';
        $this->assertFalse($this->airplanes->update($airplane_e));
        $airplane_e->registration = 'AA-AAA';
        $this->assertTrue($this->airplanes->update($airplane_e));

        // Update multiple.
        $update = $this->airplanes
            ->withValue('model', 'A320-271N')
            ->updateBatch('3', '12', '13');
        $this->assertSame(BatchUpdateResult::SUCCESS, $update);
        $records = $this->airplanes->retrieveBatch('3', '12', '13');
        foreach ($records as $record) {
            $this->assertSame('A320-271N', $record->model);
        }

        // Test with invalid values.
        $update = $this->airplanes
            ->withValue('registration', 'AB-CDEFG')
            ->updateBatch('1', '2');
        $this->assertSame(BatchUpdateResult::INVALID_VALUES, $update);

        // Test with same values.
        $update = $this->airplanes
            ->withValue('manufacturer', 'Boeing')
            ->updateBatch('1', '5', '11');
        $this->assertSame(BatchUpdateResult::NO_ENTRIES, $update);

        // Test empty update.
        $update = $this->airplanes->updateBatch('3', '12', '13');
        $this->assertSame(BatchUpdateResult::NO_VALUES, $update);

        // Test with inexistent ID.
        $update = $this->airplanes
            ->withValue('model', 'A320-271N')
            ->updateBatch('3', '12', '56');
        $this->assertSame(BatchUpdateResult::NOT_FOUND, $update);
    }

    /**
     * @covers ::__construct
     * @covers ::applyDeletionFilter
     * @covers ::cacheEntityKeys
     * @covers ::createCollection
     * @covers ::createTable
     * @covers ::createValidator
     * @covers ::getEntities
     * @covers ::list
     * @covers ::listAll
     * @covers ::paginate
     * @covers ::resetPagination
     * @covers ::sort
     * @uses Laucov\Modeling\Model\AbstractModel::prefix
     * @uses Laucov\Modeling\Model\Collection::__construct
     * @uses Laucov\Modeling\Model\Collection::count
     * @uses Laucov\Modeling\Model\Collection::current
     * @uses Laucov\Modeling\Model\Collection::get
     * @uses Laucov\Modeling\Model\Collection::next
     * @uses Laucov\Modeling\Model\Collection::rewind
     * @uses Laucov\Modeling\Model\Collection::valid
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     * @uses Laucov\Modeling\Entity\AbstractEntity::__set
     * @uses Laucov\Modeling\Entity\Relationship::__construct
     * @uses Laucov\Modeling\Model\AbstractModel::getDefaultColumns
     */
    public function testCanList(): void
    {
        // List without pagination.
        $records = $this->airplanes->listAll();
        $this->assertInstanceOf(Collection::class, $records);
        $this->assertContainsOnlyInstancesOf(Airplane::class, $records);
        $this->assertCount(13, $records);
        $this->assertSame(1, $records->page);
        $this->assertSame(null, $records->pageLength);
        $this->assertSame(13, $records->filteredCount);
        $this->assertSame(13, $records->storedCount);

        // Add a record.
        $this->conn->query(<<<SQL
            INSERT INTO airplanes
                (registration, manufacturer, model)
            VALUES
                ('PR-BCC', 'Learjet', '40')
            SQL);

        // Paginate - test without filters.
        $records = $this->airplanes
            ->paginate(5, 3)
            ->listAll();
        $this->assertCount(4, $records);
        $this->assertSame(3, $records->page);
        $this->assertSame(5, $records->pageLength);
        $this->assertSame(14, $records->filteredCount);
        $this->assertSame(14, $records->storedCount);
        $ids = [11, 12, 13, 14];
        foreach ($ids as $i => $id) {
            $this->assertSame($id, $records->get($i)->id);
        }

        // Paginate - test with filter.
        $model = new class ($this->conns) extends AirplaneModel {
            /**
             * List all planes for a specific manufacturer.
             * 
             * @return Collection<Airplane>
             */
            public function listForManufacturer(string $name): Collection
            {
                $this->table->filter('manufacturer', '=', $name);
                return $this->list();
            }
        };
        $records = $model
            ->paginate(2, 3)
            ->listForManufacturer('Airbus');
        $this->assertCount(2, $records);
        $this->assertSame(3, $records->page);
        $this->assertSame(2, $records->pageLength);
        $this->assertSame(6, $records->filteredCount);
        $this->assertSame(14, $records->storedCount);

        // Sort - ascending.
        $collection = $this->airplanes
            ->sort('model')
            ->paginate(3, 1)
            ->listAll();
        $this->assertSame(14, $collection->get(0)->id);
        $this->assertSame(10, $collection->get(1)->id);
        $this->assertSame(1, $collection->get(2)->id);

        // Sort - descending.
        $collection = $this->airplanes
            ->sort('registration', true)
            ->paginate(2, 2)
            ->listAll();
        $this->assertSame(5, $collection->get(0)->id);
        $this->assertSame(13, $collection->get(1)->id);
    }

    /**
     * @covers ::getEntity
     * @covers ::sort
     * @covers ::retrieve
     * @covers ::retrieveBatch
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     * @uses Laucov\Modeling\Entity\AbstractEntity::__set
     * @uses Laucov\Modeling\Entity\Relationship::__construct
     * @uses Laucov\Modeling\Model\AbstractModel::__construct
     * @uses Laucov\Modeling\Model\AbstractModel::applyDeletionFilter
     * @uses Laucov\Modeling\Model\AbstractModel::cacheEntityKeys
     * @uses Laucov\Modeling\Model\AbstractModel::createTable
     * @uses Laucov\Modeling\Model\AbstractModel::createValidator
     * @uses Laucov\Modeling\Model\AbstractModel::getDefaultColumns
     * @uses Laucov\Modeling\Model\AbstractModel::getEntities
     * @uses Laucov\Modeling\Model\AbstractModel::prefix
     */
    public function testCanRetrieve(): void
    {
        // Get single record.
        $record = $this->airplanes->retrieve('10');
        $this->assertInstanceOf(Airplane::class, $record);
        $this->assertSame('PP-PTM', $record->registration);
        $this->assertSame('ATR', $record->manufacturer);
        $this->assertSame('72-500', $record->model);

        // Get multiple records.
        $records = $this->airplanes->retrieveBatch('9', '7');
        $this->assertIsArray($records);
        $this->assertContainsOnlyInstancesOf(Airplane::class, $records);
        $this->assertCount(2, $records);
        $this->assertSame('PT-VEV', $records[0]->registration);
        $this->assertSame('Embraer', $records[0]->manufacturer);
        $this->assertSame('EMB-820C Caraja', $records[0]->model);
        $this->assertSame('PS-KLT', $records[1]->registration);
        $this->assertSame('Piper', $records[1]->manufacturer);
        $this->assertSame('PA-46-500TP', $records[1]->model);

        // Sort and retrieve.
        $records = $this->airplanes
            ->sort('manufacturer')
            ->retrieveBatch('7', '10', '11');
        $this->assertSame(10, $records[0]->id);
        $this->assertSame(11, $records[1]->id);
        $this->assertSame(7, $records[2]->id);
        $records = $this->airplanes
            ->sort('id', true)
            ->retrieveBatch('1', '10', '5');
        $this->assertSame(10, $records[0]->id);
        $this->assertSame(5, $records[1]->id);
        $this->assertSame(1, $records[2]->id);

        // Retrieve inexistent record.
        $this->assertNull($this->airplanes->retrieve('95'));

        // Retrieve partially existing batch.
        $records = $this->airplanes->retrieveBatch('95', '7');
        $this->assertCount(1, $records);
        $this->assertSame(7, $records[0]->id);
    }

    /**
     * @covers ::search
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     * @uses Laucov\Modeling\Model\AbstractModel::__construct
     * @uses Laucov\Modeling\Model\AbstractModel::applyDeletionFilter
     * @uses Laucov\Modeling\Model\AbstractModel::cacheEntityKeys
     * @uses Laucov\Modeling\Model\AbstractModel::createCollection
     * @uses Laucov\Modeling\Model\AbstractModel::createTable
     * @uses Laucov\Modeling\Model\AbstractModel::createValidator
     * @uses Laucov\Modeling\Model\AbstractModel::getDefaultColumns
     * @uses Laucov\Modeling\Model\AbstractModel::getEntities
     * @uses Laucov\Modeling\Model\AbstractModel::list
     * @uses Laucov\Modeling\Model\AbstractModel::listAll
     * @uses Laucov\Modeling\Model\AbstractModel::prefix
     * @uses Laucov\Modeling\Model\AbstractModel::resetPagination
     * @uses Laucov\Modeling\Model\Collection::__construct
     * @uses Laucov\Modeling\Model\Collection::get
     * @uses Laucov\Modeling\Model\Collection::has
     * @dataProvider searchProvider
     */
    public function testCanSearch(array $expected_ids, array $search): void
    {
        foreach ($search as [$name, $text, $mode]) {
            $this->airplanes->search($name, $text, $mode);
        }
        $records = $this->airplanes->listAll();
        foreach ($expected_ids as $offset => $id) {
            $this->assertTrue($records->has($offset));
            $this->assertSame($id, $records->get($offset)->id);
        }
    }

    /**
     * @covers ::insert
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     * @uses Laucov\Modeling\Validation\EntityValidator::createRuleset
     * @uses Laucov\Modeling\Validation\EntityValidator::extractProperties
     * @uses Laucov\Modeling\Validation\EntityValidator::extractPropertyNames
     * @uses Laucov\Modeling\Validation\EntityValidator::extractRules
     * @uses Laucov\Modeling\Validation\EntityValidator::getProperties
     * @uses Laucov\Modeling\Validation\EntityValidator::getPropertyNames
     * @uses Laucov\Modeling\Validation\EntityValidator::getRuleset
     * @uses Laucov\Modeling\Entity\AbstractEntity::hasErrors
     * @uses Laucov\Modeling\Entity\AbstractEntity::resetErrors
     * @uses Laucov\Modeling\Entity\AbstractEntity::toArray
     * @uses Laucov\Modeling\Validation\EntityValidator::setEntity
     * @uses Laucov\Modeling\Validation\EntityValidator::validate
     * @uses Laucov\Modeling\Entity\ObjectReader::toArray
     * @uses Laucov\Modeling\Model\AbstractModel::__construct
     * @uses Laucov\Modeling\Model\AbstractModel::cacheEntityKeys
     * @uses Laucov\Modeling\Model\AbstractModel::createCollection
     * @uses Laucov\Modeling\Model\AbstractModel::createTable
     * @uses Laucov\Modeling\Model\AbstractModel::createValidator
     * @uses Laucov\Modeling\Model\AbstractModel::prefix
     */
    public function testChecksIfInsertedEntitiesAreEmpty(): void
    {
        // Insert empty entity.
        $this->expectException(\RuntimeException::class);
        $message = 'Cannot insert empty records to the database.';
        $this->expectExceptionMessage($message);
        $this->airplanes->insert(new Airplane());
    }

    /**
     * @covers ::insertBatch
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     * @uses Laucov\Modeling\Validation\EntityValidator::extractProperties
     * @uses Laucov\Modeling\Validation\EntityValidator::extractPropertyNames
     * @uses Laucov\Modeling\Validation\EntityValidator::extractRules
     * @uses Laucov\Modeling\Validation\EntityValidator::createRuleset
     * @uses Laucov\Modeling\Validation\EntityValidator::getProperties
     * @uses Laucov\Modeling\Validation\EntityValidator::getPropertyNames
     * @uses Laucov\Modeling\Validation\EntityValidator::getRuleset
     * @uses Laucov\Modeling\Entity\AbstractEntity::hasErrors
     * @uses Laucov\Modeling\Entity\AbstractEntity::resetErrors
     * @uses Laucov\Modeling\Entity\AbstractEntity::toArray
     * @uses Laucov\Modeling\Validation\EntityValidator::setEntity
     * @uses Laucov\Modeling\Validation\EntityValidator::validate
     * @uses Laucov\Modeling\Entity\ObjectReader::toArray
     * @uses Laucov\Modeling\Model\AbstractModel::__construct
     * @uses Laucov\Modeling\Model\AbstractModel::cacheEntityKeys
     * @uses Laucov\Modeling\Model\AbstractModel::createCollection
     * @uses Laucov\Modeling\Model\AbstractModel::createTable
     * @uses Laucov\Modeling\Model\AbstractModel::createValidator
     * @uses Laucov\Modeling\Model\AbstractModel::prefix
     */
    public function testChecksIfInsertedEntityBatchIsEmpty(): void
    {
        // Insert empty entities.
        $this->expectException(\RuntimeException::class);
        $message = 'Cannot insert empty records to the database.';
        $this->expectExceptionMessage($message);
        $this->airplanes->insertBatch(new Airplane(), new Airplane());
    }

    /**
     * @coversNothing
     */
    public function testFiltersSoftDeletedRecords(): void
    {
        // Create mock for model.
        /** @var AirplaneModel & MockObject */
        $model_mock = $this
            ->getMockBuilder(AirplaneModel::class)
            ->setConstructorArgs([$this->conns])
            ->onlyMethods(['applyDeletionFilter'])
            ->getMock();
        $model_mock
            ->expects($this->exactly(7))
            ->method('applyDeletionFilter');

        // Test `AirplaneModel::applyDeletionFilter()`.
        $model_mock->delete('3');
        $model_mock->erase('9');
        $model_mock->exists('1');
        $model_mock->listAll();
        $model_mock->retrieve('2');
        $model_mock->retrieveBatch('1', '2');
    }

    /**
     * @covers ::getEntity
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     * @uses Laucov\Modeling\Entity\AbstractEntity::__set
     * @uses Laucov\Modeling\Entity\Relationship::__construct
     * @uses Laucov\Modeling\Model\AbstractModel::__construct
     * @uses Laucov\Modeling\Model\AbstractModel::applyDeletionFilter
     * @uses Laucov\Modeling\Model\AbstractModel::cacheEntityKeys
     * @uses Laucov\Modeling\Model\AbstractModel::createTable
     * @uses Laucov\Modeling\Model\AbstractModel::createValidator
     * @uses Laucov\Modeling\Model\AbstractModel::getDefaultColumns
     * @uses Laucov\Modeling\Model\AbstractModel::getEntities
     * @uses Laucov\Modeling\Model\AbstractModel::prefix
     * @uses Laucov\Modeling\Model\AbstractModel::retrieve
     */
    public function testFailsIfRetrievesDuplicatedEntries(): void
    {
        // Create model with faulty primary key.
        $model = new class ($this->conns) extends AirplaneModel {
            protected string $primaryKey = 'model';
        };

        // Test IDs.
        $model->retrieve('SR22');
        $this->expectException(\RuntimeException::class);
        $model->retrieve('A320-251N');
    }

    /**
     * @covers ::retrieveBatch
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     * @uses Laucov\Modeling\Entity\AbstractEntity::__set
     * @uses Laucov\Modeling\Entity\Relationship::__construct
     * @uses Laucov\Modeling\Model\AbstractModel::__construct
     * @uses Laucov\Modeling\Model\AbstractModel::applyDeletionFilter
     * @uses Laucov\Modeling\Model\AbstractModel::cacheEntityKeys
     * @uses Laucov\Modeling\Model\AbstractModel::createTable
     * @uses Laucov\Modeling\Model\AbstractModel::createValidator
     * @uses Laucov\Modeling\Model\AbstractModel::getDefaultColumns
     * @uses Laucov\Modeling\Model\AbstractModel::getEntities
     * @uses Laucov\Modeling\Model\AbstractModel::prefix
     * @dataProvider duplicatedAirplaneModelRetrievalProvider
     */
    public function testFailsIfRetrievesBatchesWithDuplicatedEntries(
        array $lucky_ids,
        array $faulty_ids,
    ): void {
        // Create model with faulty primary key.
        $model = new class ($this->conns) extends AirplaneModel {
            protected string $primaryKey = 'model';
        };

        // Test IDs.
        $model->retrieveBatch(...$lucky_ids);
        $this->expectException(\RuntimeException::class);
        $model->retrieveBatch(...$faulty_ids);
    }

    /**
     * @covers ::getDefaultColumns
     * @covers ::getEntities
     * @covers ::retrieveBatch
     * @covers ::withColumns
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     * @uses Laucov\Modeling\Entity\Relationship::__construct
     * @uses Laucov\Modeling\Model\AbstractModel::__construct
     * @uses Laucov\Modeling\Model\AbstractModel::applyDeletionFilter
     * @uses Laucov\Modeling\Model\AbstractModel::cacheEntityKeys
     * @uses Laucov\Modeling\Model\AbstractModel::createCollection
     * @uses Laucov\Modeling\Model\AbstractModel::createTable
     * @uses Laucov\Modeling\Model\AbstractModel::createValidator
     * @uses Laucov\Modeling\Model\AbstractModel::getEntity
     * @uses Laucov\Modeling\Model\AbstractModel::list
     * @uses Laucov\Modeling\Model\AbstractModel::listAll
     * @uses Laucov\Modeling\Model\AbstractModel::paginate
     * @uses Laucov\Modeling\Model\AbstractModel::prefix
     * @uses Laucov\Modeling\Model\AbstractModel::resetPagination
     * @uses Laucov\Modeling\Model\AbstractModel::retrieve
     * @uses Laucov\Modeling\Model\Collection::__construct
     * @uses Laucov\Modeling\Model\Collection::get
     */
    public function testSelectsColumns(): void
    {
        // Create custom model.
        // Use AirplaneWithSetter to track unused fetched columns.
        /** @var AbstractModel<AirplaneWithSetter> */
        $model = new class ($this->conns) extends AbstractModel {
            protected string $entityName = AirplaneWithSetter::class;
            protected string $primaryKey = 'id';
            protected string $tableName = 'airplanes';
        };

        // List with specific columns.
        $entity = $model
            ->withColumns('manufacturer', 'model')
            ->paginate(1, 1)
            ->listAll()
            ->get(0);
        $this->assertFalse(isset($entity->id));
        $this->assertTrue(isset($entity->manufacturer));
        $this->assertTrue(isset($entity->model));
        $this->assertFalse(isset($entity->registration));
        $this->assertCount(0, $entity->getUnusedColumns());

        // List with all columns - ensure columns are reset.
        $entity = $model
            ->paginate(1, 1)
            ->listAll()
            ->get(0);
        $this->assertTrue(isset($entity->id));
        $this->assertTrue(isset($entity->manufacturer));
        $this->assertTrue(isset($entity->model));
        $this->assertTrue(isset($entity->registration));
        $this->assertCount(0, $entity->getUnusedColumns());

        // Retrieve with specific columns.
        $entity = $model
            ->withColumns('manufacturer', 'model')
            ->retrieve('1');
        $this->assertFalse(isset($entity->id));
        $this->assertTrue(isset($entity->manufacturer));
        $this->assertTrue(isset($entity->model));
        $this->assertFalse(isset($entity->registration));
        $this->assertCount(0, $entity->getUnusedColumns());

        // Retrieve with all columns - ensure columns are reset.
        $entity = $model->retrieve('1');
        $this->assertTrue(isset($entity->id));
        $this->assertTrue(isset($entity->manufacturer));
        $this->assertTrue(isset($entity->model));
        $this->assertTrue(isset($entity->registration));
        $this->assertCount(0, $entity->getUnusedColumns());

        // Retrieve batch with specific columns.
        $entity = $model
            ->withColumns('manufacturer', 'model')
            ->retrieveBatch('1')[0];
        $this->assertTrue(isset($entity->id));
        $this->assertTrue(isset($entity->manufacturer));
        $this->assertTrue(isset($entity->model));
        $this->assertFalse(isset($entity->registration));
        $this->assertCount(0, $entity->getUnusedColumns());

        // Retrieve batch with all columns - ensure columns are reset.
        $entity = $model->retrieveBatch('1')[0];
        $this->assertTrue(isset($entity->id));
        $this->assertTrue(isset($entity->manufacturer));
        $this->assertTrue(isset($entity->model));
        $this->assertTrue(isset($entity->registration));
        $this->assertCount(0, $entity->getUnusedColumns());
    }

    /**
     * This method is called before each test.
     */
    protected function setUp(): void
    {
        // Create connection.
        $drivers = new DriverFactory();
        $this->conns = new ConnectionFactory($drivers);
        $this->conn = $this->conns
            ->setConnection('main', 'sqlite::memory:')
            ->getConnection();

        // Create tables.
        $this->conn
            ->query(<<<SQL
                CREATE TABLE "airplanes" (
                    "id" INTEGER PRIMARY KEY,
                    "registration" VARCHAR(8),
                    "manufacturer" VARCHAR(32),
                    "model" VARCHAR(128),
                    "deleted_at" DATETIME
                )
                SQL)
            ->query(<<<SQL
                CREATE TABLE "flights" (
                    "id" INTEGER PRIMARY KEY,
                    "airplane_id" INT(11),
                    "origin" VARCHAR(3),
                    "destination" VARCHAR(3),
                    "deleted_at" DATETIME
                )
                SQL)
            ->query(<<<SQL
                CREATE TABLE "flights_statuses" (
                    "id" INTEGER PRIMARY KEY,
                    "flight_id" INT(11),
                    "departed_at" DATETIME,
                    "airplane_altitude" INT(11),
                    "deleted_at" DATETIME
                )
                SQL)
            ->query(<<<SQL
                CREATE TABLE "flights_crew_members" (
                    "id" INTEGER PRIMARY KEY,
                    "flight_id" INT(11),
                    "name" VARCHAR(128),
                    "deleted_at" DATETIME
                )
                SQL);

        // Insert records.
        $this->conn
            ->query(<<<SQL
                INSERT INTO "airplanes"
                    ("registration", "manufacturer", "model")
                VALUES
                    ('PR-XMI', 'Boeing', '737 MAX 8'),
                    ('PR-XBR', 'Airbus', 'A320-271N'),
                    ('PR-YRH', 'Airbus', 'A320-251N'),
                    ('LV-KFX', 'Airbus', 'A320-232'),
                    ('PS-GRC', 'Boeing', '737 MAX 8'),
                    ('PR-XBF', 'Airbus', 'A320-273N'),
                    ('PT-VEV', 'Embraer', 'EMB-820C Caraja'),
                    ('PR-CPG', 'Cirrus', 'SR22'),
                    ('PS-KLT', 'Piper', 'PA-46-500TP'),
                    ('PP-PTM', 'ATR', '72-500'),
                    ('LV-KEI', 'Boeing', '737 MAX 8'),
                    ('CC-DBE', 'Airbus', 'A320-251N'),
                    ('PR-YSH', 'Airbus', 'A320-251N')
            SQL)
            ->query(<<<SQL
                INSERT INTO "flights"
                    ("airplane_id", "origin", "destination")
                VALUES
                    (1, 'GIG', 'FLN'),
                    (3, 'VCP', 'FLN'),
                    (10, 'VCP', 'LDB'),
                    (3, 'CNF', 'VCP'),
                    (2, 'GRU', 'MOC'),
                    (3, 'GRU', 'BOG'),
                    (9, 'PLU', NULL),
                    (10, 'CNF', 'UDI'),
                    (1, 'SSA', 'GRU')
            SQL)
            ->query(<<<SQL
                INSERT INTO "flights_statuses"
                    ("flight_id", "departed_at", "airplane_altitude", "deleted_at")
                VALUES
                    (1, NULL, NULL, '2024-05-01 12:10:28'),
                    (1, '2024-05-01 17:00:04', NULL, '2024-05-01 19:56:02'),
                    (2, '2024-05-01 17:41:53', NULL, '2024-05-01 18:59:58'),
                    (3, '2024-05-01 17:49:30', NULL, '2024-05-01 21:02:56'),
                    (4, '2024-05-01 18:05:41', NULL, '2024-05-01 20:12:01'),
                    (5, '2024-05-02 20:48:12', 15014, NULL),
                    (6, '2024-05-02 21:00:07', 9574, NULL),
                    (7, '2024-05-02 21:01:49', 11477, NULL),
                    (8, '2024-05-02 21:04:31', 9315, NULL),
                    (9, '2024-05-02 21:09:56', 14976, NULL)
            SQL)
            ->query(<<<SQL
                INSERT INTO "flights_crew_members"
                    ("flight_id", "name")
                VALUES
                    (1, 'John Doe'),
                    (1, 'James Foobar'),
                    (3, 'Mary Papadopoulos'),
                    (3, 'Jane Baz')
            SQL);

        // Create model instances.
        $this->airplanes = new AirplaneModel($this->conns);
        $this->flights = new FlightModel($this->conns);
    }
}
