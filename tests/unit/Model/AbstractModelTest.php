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
use Laucov\Db\Data\Driver\DriverFactory;
use Laucov\Modeling\Model\BatchUpdateResult;
use Laucov\Modeling\Model\Collection;
use Laucov\Modeling\Model\DeletionFilter;
use Laucov\Modeling\Entity\AbstractEntity;
use Laucov\Modeling\Model\AbstractModel;
use Laucov\Validation\Rules\Regex;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Laucov\Modeling\Model\AbstractModel
 */
class AbstractModelTest extends TestCase
{
    protected Connection $conn;
    protected AirplaneModel $model;

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
     * @covers ::applyDeletionFilter
     * @covers ::delete
     * @covers ::erase
     * @covers ::exists
     * @covers ::filterDeleted
     * @uses Laucov\Modeling\Model\AbstractModel::__construct
     * @uses Laucov\Modeling\Model\AbstractModel::prefix
     */
    public function testCanDeleteAndErase(): void
    {
        // Delete single record.
        $this->model->delete('1');
        // Delete multiple records.
        $this->model->delete('2', '3');
        // Erase records.
        $this->model
            ->filterDeleted(DeletionFilter::SHOW)
            ->erase('1');

        // Turn off filter resetting for the next operations.
        $this->model->keepDeletionFilter = true;

        // Check active records.
        $this->assertFalse($this->model->exists('999')); // Never existed
        $this->assertFalse($this->model->exists('1')); // Erased
        $this->assertFalse($this->model->exists('1', '2')); // Erased + Deleted
        $this->assertFalse($this->model->exists('2', '3')); // Deleted
        $this->assertFalse($this->model->exists('3', '4')); // Deleted + Active
        $this->assertTrue($this->model->exists('4', '5')); // Active

        // Check all records.
        $this->model->filterDeleted(DeletionFilter::SHOW);
        $this->assertFalse($this->model->exists('999'));
        $this->assertFalse($this->model->exists('1'));
        $this->assertFalse($this->model->exists('1', '2'));
        $this->assertTrue($this->model->exists('2', '3'));
        $this->assertTrue($this->model->exists('3', '4'));
        $this->assertTrue($this->model->exists('4', '5'));

        // Check deleted records.
        $this->model->filterDeleted(DeletionFilter::SHOW_EXCLUSIVELY);
        $this->assertFalse($this->model->exists('999'));
        $this->assertFalse($this->model->exists('1'));
        $this->assertFalse($this->model->exists('1', '2'));
        $this->assertTrue($this->model->exists('2', '3'));
        $this->assertFalse($this->model->exists('3', '4'));
        $this->assertFalse($this->model->exists('4', '5'));

        // Turn on filter resetting again.
        $this->model->keepDeletionFilter = false;
    }

    /**
     * @covers ::insert
     * @covers ::insertBatch
     * @covers ::update
     * @covers ::updateBatch
     * @covers ::withValue
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     * @uses Laucov\Modeling\Entity\AbstractEntity::__set
     * @uses Laucov\Modeling\Entity\AbstractEntity::cacheRules
     * @uses Laucov\Modeling\Entity\AbstractEntity::getEntries
     * @uses Laucov\Modeling\Entity\AbstractEntity::getProperties
     * @uses Laucov\Modeling\Entity\AbstractEntity::getPropertyNames
     * @uses Laucov\Modeling\Entity\AbstractEntity::getRuleset
     * @uses Laucov\Modeling\Entity\AbstractEntity::toArray
     * @uses Laucov\Modeling\Entity\AbstractEntity::validate
     * @uses Laucov\Modeling\Model\AbstractModel::__construct
     * @uses Laucov\Modeling\Model\AbstractModel::applyDeletionFilter
     * @uses Laucov\Modeling\Model\AbstractModel::getEntity
     * @uses Laucov\Modeling\Model\AbstractModel::getEntities
     * @uses Laucov\Modeling\Model\AbstractModel::prefix
     * @uses Laucov\Modeling\Model\AbstractModel::retrieve
     * @uses Laucov\Modeling\Model\AbstractModel::retrieveBatch
     * @uses Laucov\Modeling\Entity\ObjectReader::count
     * @uses Laucov\Modeling\Entity\ObjectReader::diff
     * @uses Laucov\Modeling\Entity\ObjectReader::toArray
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
        $this->assertTrue($this->model->insert($airplane_a));
        $this->assertSame(14, $airplane_a->id);

        // Test validation.
        $airplane_b = new Airplane();
        $airplane_b->registration = 'PR-TOOLONG';
        $airplane_b->manufacturer = 'Airbus';
        $airplane_b->model = 'A320-214';
        $this->assertFalse($this->model->insert($airplane_b));
        $this->assertFalse(isset($airplane_b->id));
        $airplane_b->registration = 'PR-MYR';
        $this->assertTrue($this->model->insert($airplane_b));
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
        $this->assertFalse($this->model->insertBatch($airplane_c, $airplane_d));
        $airplane_c->registration = 'LV-BMS';
        $this->assertTrue($this->model->insertBatch($airplane_c, $airplane_d));
        $this->assertSame('17', $this->conn->getLastId());
        $this->assertFalse(isset($airplane_c->id));
        $this->assertFalse(isset($airplane_d->id));

        // Test updating.
        $airplane_e = $this->model->retrieve('17');
        $this->assertNull($this->model->update($airplane_e));
        $airplane_e->registration = 'AA-AAAA';
        $this->assertFalse($this->model->update($airplane_e));
        $airplane_e->registration = 'AA-AAA';
        $this->assertTrue($this->model->update($airplane_e));

        // Update multiple.
        $update = $this->model
            ->withValue('model', 'A320-271N')
            ->updateBatch('3', '12', '13');
        $this->assertSame(BatchUpdateResult::SUCCESS, $update);
        $records = $this->model->retrieveBatch('3', '12', '13');
        foreach ($records as $record) {
            $this->assertSame('A320-271N', $record->model);
        }

        // Test with invalid values.
        $update = $this->model
            ->withValue('registration', 'AB-CDEFG')
            ->updateBatch('1', '2');
        $this->assertSame(BatchUpdateResult::INVALID_VALUES, $update);

        // Test with same values.
        $update = $this->model
            ->withValue('manufacturer', 'Boeing')
            ->updateBatch('1', '5', '11');
        $this->assertSame(BatchUpdateResult::NO_ENTRIES, $update);

        // Test empty update.
        $update = $this->model->updateBatch('3', '12', '13');
        $this->assertSame(BatchUpdateResult::NO_VALUES, $update);

        // Test with inexistent ID.
        $update = $this->model
            ->withValue('model', 'A320-271N')
            ->updateBatch('3', '12', '56');
        $this->assertSame(BatchUpdateResult::NOT_FOUND, $update);
    }

    /**
     * @covers ::joinToOne
     * @covers ::prefix
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     * @uses Laucov\Modeling\Entity\AbstractEntity::__set
     * @uses Laucov\Modeling\Model\AbstractModel::__construct
     * @uses Laucov\Modeling\Model\AbstractModel::applyDeletionFilter
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
    public function testCanJoinRelationships(): void
    {
        // Set the new entity class.
        $model = new class ($this->conn) extends AirplaneModel {
            protected string $entityName = AirplaneWithNotes::class;
        };

        // Create extra tables.
        $this->createAirplanesInfoTable();

        // Retrieve without joining notes.
        /** @var AirplaneWithNotes */
        $airplane = $model->retrieve('4');
        $this->assertNull($airplane->notes ?? null);

        // Retrieve joining notes.
        /** @var AirplaneWithNotes */
        $airplane = $model
            ->withNotes()
            ->retrieve('4');
        $this->assertSame('Nothing to add.', $airplane->notes);

        // List without joining notes.
        /** @var Collection<AirplaneWithNotes> */
        $collection = $model->listAll();
        foreach ($collection as $entity) {
            $this->assertNull($entity->notes ?? null);
        }

        // Set a list of expected notes.
        $expected_notes = [
            1 => 'Very cool plane!',
            2 => 'Flying for 3 years.',
            4 => 'Nothing to add.',
            6 => 'This is a note.',
        ];

        // List joining notes.
        /** @var Collection<AirplaneWithNotes> */
        $collection = $model
            ->withNotes()
            ->listAll();
        foreach ($collection as $entity) {
            $id = $entity->id;
            if (array_key_exists($id, $expected_notes)) {
                $this->assertSame($expected_notes[$id], $entity->notes);
            } else {
                $this->assertNull($entity->notes ?? null);
            }
        }

        // Test other
        /** @var Collection<AirplaneWithNotes> */
        $collection = $model
            ->withNotes()
            ->sort('notes', true)
            ->listAll();
        $this->assertSame(1, $collection->get(0)->id);
        $this->assertSame(6, $collection->get(1)->id);
        $this->assertSame(4, $collection->get(2)->id);
        $this->assertSame(2, $collection->get(3)->id);
        $this->assertNull($collection->get(4)->notes);
    }

    /**
     * @covers ::__construct
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
     * @uses Laucov\Modeling\Model\AbstractModel::applyDeletionFilter
     */
    public function testCanList(): void
    {
        // List without pagination.
        $records = $this->model->listAll();
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
        $records = $this->model
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
        $model = new class ($this->conn) extends AirplaneModel {
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
        $collection = $this->model
            ->sort('model')
            ->paginate(3, 1)
            ->listAll();
        $this->assertSame(14, $collection->get(0)->id);
        $this->assertSame(10, $collection->get(1)->id);
        $this->assertSame(1, $collection->get(2)->id);

        // Sort - descending.
        $collection = $this->model
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
     * @uses Laucov\Modeling\Model\AbstractModel::__construct
     * @uses Laucov\Modeling\Model\AbstractModel::applyDeletionFilter
     * @uses Laucov\Modeling\Model\AbstractModel::getEntities
     * @uses Laucov\Modeling\Model\AbstractModel::prefix
     */
    public function testCanRetrieve(): void
    {
        // Get single record.
        $record = $this->model->retrieve('10');
        $this->assertInstanceOf(Airplane::class, $record);
        $this->assertSame('PP-PTM', $record->registration);
        $this->assertSame('ATR', $record->manufacturer);
        $this->assertSame('72-500', $record->model);

        // Get multiple records.
        $records = $this->model->retrieveBatch('9', '7');
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
        $records = $this->model
            ->sort('manufacturer')
            ->retrieveBatch('7', '10', '11');
        $this->assertSame(10, $records[0]->id);
        $this->assertSame(11, $records[1]->id);
        $this->assertSame(7, $records[2]->id);
        $records = $this->model
            ->sort('id', true)
            ->retrieveBatch('1', '10', '5');
        $this->assertSame(10, $records[0]->id);
        $this->assertSame(5, $records[1]->id);
        $this->assertSame(1, $records[2]->id);

        // Retrieve inexistent record.
        $this->assertNull($this->model->retrieve('95'));

        // Retrieve partially existing batch.
        $records = $this->model->retrieveBatch('95', '7');
        $this->assertCount(1, $records);
        $this->assertSame(7, $records[0]->id);
    }

    /**
     * @coversNothing
     */
    public function testFiltersSoftDeletedRecords(): void
    {
        // Create mock for model.
        $model_mock = $this
            ->getMockBuilder(AirplaneModel::class)
            ->setConstructorArgs([$this->conn])
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
     * @uses Laucov\Modeling\Model\AbstractModel::__construct
     * @uses Laucov\Modeling\Model\AbstractModel::applyDeletionFilter
     * @uses Laucov\Modeling\Model\AbstractModel::getEntities
     * @uses Laucov\Modeling\Model\AbstractModel::prefix
     * @uses Laucov\Modeling\Model\AbstractModel::retrieve
     */
    public function testFailsIfRetrievesDuplicatedEntries(): void
    {
        // Create model with faulty primary key.
        $model = new class ($this->conn) extends AirplaneModel {
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
     * @uses Laucov\Modeling\Model\AbstractModel::__construct
     * @uses Laucov\Modeling\Model\AbstractModel::applyDeletionFilter
     * @uses Laucov\Modeling\Model\AbstractModel::getEntities
     * @uses Laucov\Modeling\Model\AbstractModel::prefix
     * @dataProvider duplicatedAirplaneModelRetrievalProvider
     */
    public function testFailsIfRetrievesBatchesWithDuplicatedEntries(
        array $lucky_ids,
        array $faulty_ids,
    ): void {
        // Create model with faulty primary key.
        $model = new class ($this->conn) extends AirplaneModel {
            protected string $primaryKey = 'model';
        };

        // Test IDs.
        $model->retrieveBatch(...$lucky_ids);
        $this->expectException(\RuntimeException::class);
        $model->retrieveBatch(...$faulty_ids);
    }

    /**
     * @covers ::getEntities
     * @covers ::retrieveBatch
     * @covers ::withColumns
     * @uses Laucov\Modeling\Entity\AbstractEntity::__construct
     * @uses Laucov\Modeling\Model\AbstractModel::__construct
     * @uses Laucov\Modeling\Model\AbstractModel::applyDeletionFilter
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
        $model = new class ($this->conn) extends AbstractModel {
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
     * Create a table for airplane info entries.
     */
    protected function createAirplanesInfoTable(): void
    {
        $this->conn
            ->query(<<<SQL
                CREATE TABLE "airplanes_notes" (
                    "id" INTEGER PRIMARY KEY,
                    "airplane_id" INT(11),
                    "notes" VARCHAR(256),
                    "deleted_at" DATETIME
                )
                SQL)
            ->query(<<<SQL
                INSERT INTO "airplanes_notes"
                    ("airplane_id", "notes", "deleted_at")
                VALUES
                    (1, 'Very cool plane!', null),
                    (2, 'Flying for 3 years.', null),
                    (4, 'Something to add.', '2024-01-01 01:00:00'),
                    (4, 'Nothing to add.', null),
                    (6, 'This is a note.', null)
            SQL);
    }

    // /**
    //  * Create a table for flights.
    //  */
    // protected function createFlightsTable(): void
    // {
    //     $this->conn
    //         ->query(<<<SQL
    //             CREATE TABLE "flights" (
    //                 "id" INTEGER PRIMARY KEY,
    //                 "airplane_id" INT(11),
    //                 "origin" VARCHAR(3),
    //                 "destination" VARCHAR(3),
    //                 "deleted_at" DATETIME
    //             )
    //             SQL)
    //         ->query(<<<SQL
    //             INSERT INTO "flights"
    //                 ("airplane_id", "origin", "destination")
    //             VALUES
    //                 (1, 'GIG', 'FLN'),
    //                 (3, 'VCP', 'FLN'),
    //                 (10, 'VCP', 'LDB'),
    //                 (3, 'CNF', 'VCP'),
    //                 (2, 'GRU', 'MOC'),
    //                 (3, 'GRU', 'BOG'),
    //                 (9, 'PLU', NULL)
    //                 (10, 'CNF', 'UDI')
    //                 (1, 'SSA', 'GRU'),
    //         SQL);
    // }

    protected function setUp(): void
    {
        // Create connection instance and table.
        $this->conn = new Connection(new DriverFactory(), 'sqlite::memory:');
        $this->conn
            ->query(<<<SQL
                CREATE TABLE airplanes (
                    id INTEGER PRIMARY KEY,
                    registration VARCHAR(8),
                    manufacturer VARCHAR(32),
                    model VARCHAR(128),
                    deleted_at DATETIME
                )
                SQL)
            ->query(<<<SQL
                INSERT INTO airplanes
                    (registration, manufacturer, model)
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
            SQL);

        $this->model = new AirplaneModel($this->conn);
    }
}

/**
 * Test entity that represents an airplane.
 */
class Airplane extends AbstractEntity
{
    public int $id;
    #[Regex('/^[A-Z]{2}\-[A-Z]{3}$/')]
    public string $registration;
    public string $manufacturer;
    public string $model;
}

/**
 * Test entity that represents an airplane with an extra field "notes".
 */
class AirplaneWithNotes extends Airplane
{
    public null|string $notes;
}

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
    public function withNotes(): static
    {
        $this->joinToOne('airplanes_notes', 'id', 'airplane_id');
        return $this;
    }
}

/**
 * Extension of `Airplane` that catches unused values.
 */
class AirplaneWithSetter extends Airplane
{
    protected array $unusedColumns = [];
    public function __set(string $name, mixed $value): void
    {
        $this->unusedColumns[] = $name;
        parent::__set($name, $value);
    }
    public function getUnusedColumns(): array
    {
        return $this->unusedColumns;
    }
}

/**
 * Bad model.
 * 
 * Will fail when retrieval methods are called for planes with the same model.
 * 
 * @extends AbstractModel<Airplane>
 */
class FaultyModel extends AbstractModel
{
    protected string $entityName = Airplane::class;
    protected string $primaryKey = 'model';
    protected string $tableName = 'airplanes';
}

// /**
//  * Test entity that represents an flight.
//  */
// class Flight
// {
//     public int $id;
//     public string $origin;
//     public string $destination;
// }
