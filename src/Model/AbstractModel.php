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

namespace Laucov\Modeling\Model;

use Laucov\Db\Data\Connection;
use Laucov\Db\Query\Table;
use Laucov\Modeling\Entity\AbstractEntity;
use Laucov\Modeling\Entity\ObjectReader;
use Laucov\Modeling\Entity\Relationship;

/**
 * Provides table data in form of entities and collections.
 * 
 * @template T of AbstractEntity
 */
abstract class AbstractModel
{
    /**
     * Whether to reset the deletion filter upon the next query.
     */
    public bool $keepDeletionFilter = false;

    /**
     * Current deletion filter.
     */
    protected DeletionFilter $deletionFilter = DeletionFilter::HIDE;

    /**
     * The entity's default selected keys.
     * 
     * @var array<string>
     */
    protected array $entityKeys;

    /**
     * AbstractEntity class name.
     * 
     * @var class-string<T>
     */
    protected string $entityName;

    /**
     * Addition to the entity's default selected keys.
     * 
     * Used when joining one-to-one relationships.
     * 
     * @var array<string>
     */
    protected array $entityRelationshipKeys = [];

    /**
     * Joined one-to-one relationships.
     * 
     * @var array<string>
     */
    protected array $joinedRelationships = [];

    /**
     * One-to-many relationships.
     * 
     * @var array<array{class-string<AbstractModel>, string, string, callable}>
     */
    protected array $requestedRelationships = [];

    /**
     * Selected page length.
     */
    protected null|int $pageLength = null;

    /**
     * Selected page number.
     */
    protected int $pageNumber = 1;

    /**
     * Primary key column.
     */
    protected string $primaryKey;

    /**
     * Columns to select when getting entities.
     * 
     * @var array<string>
     */
    protected array $selecting = [];

    /**
     * Sorting calls.
     * 
     * @var array<array{string, string}>
     */
    protected array $sorting = [];

    /**
     * Table instance.
     */
    protected Table $table;

    /**
     * Table name.
     */
    protected string $tableName;

    /**
     * Cached update values.
     */
    protected array $updateValues = [];

    /**
     * Create the model instance.
     */
    public function __construct(
        /**
         * Database connection interface.
         */
        protected Connection $connection,
    ) {
        // Store a table instance.
        $this->table = new Table($connection, $this->tableName);

        // Store entity's key names.
        $this->entityKeys = [];
        foreach (array_keys(get_class_vars($this->entityName)) as $key) {
            // Check if is a relationship key.
            $property = new \ReflectionProperty($this->entityName, $key);
            $rel_attributes = $property->getAttributes(Relationship::class);
            $rel = count($rel_attributes) > 0
                ? $rel_attributes[0]->newInstance()
                : null;
            if ($rel === null) {
                // Store default key.
                $this->entityKeys[] = $key === $this->primaryKey
                    ? $this->prefix($key)
                    : $key;
            } else {
                // Store relationship key.
                $this->entityRelationshipKeys[$rel->tableName][] = $key;
            }
        }
    }

    /**
     * Create a new entity instance that can be used by this model.
     * 
     * @return T
     */
    public function createEntity(): mixed
    {
        return new $this->entityName();
    }

    /**
     * Create a new entity instance that can be used by this model.
     * 
     * @return CreationResult<T>
     */
    public function createEntityFromArray(array $values): mixed
    {
        return $this->entityName::createFromArray($values);
    }

    /**
     * Perform a soft delete operation for the given primary key values.
     */
    public function delete(string ...$ids): void
    {
        $this->applyDeletionFilter();

        $this->table
            ->filter($this->primaryKey, '=', $ids)
            ->updateRecords(['deleted_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Permanently remove one or more records.
     */
    public function erase(string ...$ids): void
    {
        $this->applyDeletionFilter();

        $this->table
            ->filter($this->primaryKey, '=', $ids)
            ->deleteRecords();
    }

    /**
     * Check if a record exists.
     */
    public function exists(string ...$ids): bool
    {
        $this->applyDeletionFilter();

        $count = $this->table
            ->filter($this->primaryKey, '=', $ids)
            ->countRecords($this->primaryKey);

        return $count === count($ids);
    }

    /**
     * Set how the model shall treat soft-deleted records.
     */
    public function filterDeleted(DeletionFilter $filter): static
    {
        $this->deletionFilter = $filter;
        return $this;
    }

    /**
     * Insert a new record.
     * 
     * @param T $entity
     */
    public function insert(mixed $entity): bool
    {
        // Validate entity.
        if (!$entity->validate()) {
            return false;
        }

        // Insert record.
        $data = $entity->toArray();
        if (count($data) < 1) {
            $message = 'Cannot insert empty records to the database.';
            throw new \RuntimeException($message);
        }
        $id = $this->table->insertRecord($data);
        $entity->{$this->primaryKey} = $id;

        return true;
    }

    /**
     * Insert one or more records.
     */
    public function insertBatch(AbstractEntity ...$entities): bool
    {
        // Validate each entity.
        $is_valid = true;
        foreach ($entities as $entity) {
            if (!$entity->validate() && $is_valid) {
                $is_valid = false;
            }
        }
        if (!$is_valid) {
            return false;
        }

        // Insert all entities.
        $data = [];
        foreach ($entities as $entity) {
            $entry = $entity->toArray();
            if (count($entry) < 1) {
                $message = 'Cannot insert empty records to the database.';
                throw new \RuntimeException($message);
            }
            $data[] = $entry;
        }
        $this->table->insertRecords(...$data);

        return true;
    }

    /**
     * List all records.
     * 
     * @return Collection<T>
     */
    public function listAll(): Collection
    {
        return $this->list();
    }

    /**
     * Offset and limit the next list from a page length and number.
     */
    public function paginate(int $page_length, int $page_number): static
    {
        // Register pagination.
        $this->pageLength = $page_length;
        $this->pageNumber = $page_number;

        return $this;
    }

    /**
     * Find a single record by its primary key value.
     * 
     * @return null|T
     */
    public function retrieve(string $id): mixed
    {
        $this->table->filter($this->prefix($this->primaryKey), '=', $id);
        return $this->getEntity();
    }

    /**
     * Find multiple records by their primary key value.
     * 
     * Note: the primary key is aways fetched when using this method.
     * 
     * @return array<T>
     */
    public function retrieveBatch(string ...$ids): array
    {
        // Check if the primary key is selected.
        if (
            count($this->selecting) > 0
            && !in_array($this->primaryKey, $this->selecting, true)
        ) {
            $this->selecting[] = $this->primaryKey;
        }

        // Get records.
        $this->table->filter($this->primaryKey, '=', $ids);
        $records = $this->getEntities();

        // Check for duplicated IDs.
        $record_ids = array_map(fn ($r) => $r->{$this->primaryKey}, $records);
        if (count($record_ids) > count(array_unique($record_ids))) {
            $msg = 'Found duplicated entries when querying for multiple IDs.';
            throw new \RuntimeException($msg);
        }

        return $records;
    }

    /**
     * Filter the next list/retrieval searching a specific column.
     */
    public function search(
        string $column_name,
        string $search,
        SearchMode $mode,
    ): static {
        $this->table->filter($column_name, $mode->value, $search);
        return $this;
    }

    /**
     * Sort the next list/retrieval.
     */
    public function sort(string $column_name, bool $descending = false): static
    {
        $this->table->sort($column_name, $descending);
        return $this;
    }

    /**
     * Update a record.
     * 
     * @param T $entity
     */
    public function update(mixed $entity): null|bool
    {
        // Validate entity.
        if (!$entity->validate()) {
            return false;
        }

        // Check if has entries.
        $entries = $entity->getEntries();
        if (ObjectReader::count($entries) < 1) {
            return null;
        }

        // Insert record.
        $this->table
            ->filter($this->primaryKey, '=', $entity->{$this->primaryKey})
            ->updateRecords((array) $entries);

        return true;
    }

    /**
     * Update multiple records with previously set values.
     */
    public function updateBatch(string ...$ids): BatchUpdateResult
    {
        // Check if values were set.
        if (count($this->updateValues) < 1) {
            $this->updateValues = [];
            return BatchUpdateResult::NO_VALUES;
        }

        // Get records.
        $records = $this->table
            ->filter($this->primaryKey, '=', $ids)
            ->selectRecords($this->entityName);
        if (count($records) !== count($ids)) {
            $this->updateValues = [];
            return BatchUpdateResult::NOT_FOUND;
        }

        // Iterate records.
        $ids = [];
        foreach ($records as $record) {
            // Set values.
            foreach ($this->updateValues as $name => $value) {
                $record->$name = $value;
            }
            // Validate.
            if (!$record->validate()) {
                $this->updateValues = [];
                return BatchUpdateResult::INVALID_VALUES;
            }
            // Count entries.
            // Ignore records that wouldn't change.
            if (ObjectReader::count($record->getEntries()) > 0) {
                $ids[] = (string) $record->{$this->primaryKey};
            }
        }

        // Cancel if there is nothing to update.
        if (count($ids) < 1) {
            $this->updateValues = [];
            return BatchUpdateResult::NO_ENTRIES;
        }

        // Update records.
        $this->table
            ->filter($this->primaryKey, '=', $ids)
            ->updateRecords($this->updateValues);

        // Reset values.
        $this->updateValues = [];

        return BatchUpdateResult::SUCCESS;
    }

    /**
     * Set the columns to get.
     * 
     * Note: entity properties that depend on unfetched columns won't be set.
     */
    public function withColumns(string ...$column_names): static
    {
        $this->selecting = $column_names;
        return $this;
    }

    /**
     * Set a value for further batch update.
     */
    public function withValue(
        string $column_name,
        null|int|float|string $value,
    ): static {
        $this->updateValues[$column_name] = $value;
        return $this;
    }

    /**
     * Apply the current deletion filter to the table instance.
     */
    public function applyDeletionFilter(): void
    {
        // Set constraints according to the filter mode.
        switch ($this->deletionFilter) {
            case DeletionFilter::HIDE:
                $this->table->filter($this->prefix('deleted_at'), '=', null);
                break;
            case DeletionFilter::SHOW:
                // Do nothing.
                break;
            case DeletionFilter::SHOW_EXCLUSIVELY:
                $this->table->filter($this->prefix('deleted_at'), '!=', null);
                break;
        }

        // Reset the filter.
        if (!$this->keepDeletionFilter) {
            $this->deletionFilter = DeletionFilter::HIDE;
        }
    }

    /**
     * Find and append related records to the given entities.
     * 
     * @param array<T> $entities Entities to fetch related records.
     */
    protected function fetchRelationship(
        array $entities,
        string $model_name,
        string $left_key,
        string $right_key,
        null|callable $callback,
    ): void {
        // Extract arguments and get the model instance.
        $model = new $model_name($this->connection);

        // Set model filters.
        $values = array_map(fn ($e) => $e->{$left_key}, $entities);
        $model->table->filter($right_key, '=', $values);

        // Run callback.
        if ($callback !== null) {
            $callback($model);
        }

        // Check if necessary keys are selected.
        // The $right_key column is required for filtering.
        if (
            count($model->selecting) > 0
            && !in_array($right_key, $model->selecting)
        ) {
            $model->selecting[] = $right_key;
        }

        // Fetch and group records.
        $records = [];
        foreach ($model->listAll() as $record) {
            $records[$record->{$right_key}][] = $record;
        }

        // Attach records.
        foreach ($entities as $entity) {
            $related = $records[$entity->{$left_key}] ?? [];
            $count = count($related);
            $collection = new Collection(1, null, $count, $count, ...$related);
            $entity->{$model->tableName} = $collection;
        }
    }

    /**
     * Get the next SELECT query default keys.
     * 
     * @return array<string>
     */
    public function getDefaultColumns(): array
    {
        // Get main keys.
        $columns = $this->entityKeys;

        // Get relationship keys.
        foreach ($this->joinedRelationships as $table_name) {
            $keys = $this->entityRelationshipKeys[$table_name] ?? [];
            array_push($columns, ...$keys);
        }

        return $columns;
    }

    /**
     * Get records from the current filters and return them as entities.
     * 
     * @return array<T>
     */
    protected function getEntities(): array
    {
        // Select columns.
        $column_names = count($this->selecting) > 0
            ? $this->selecting
            : $this->getDefaultColumns();
        foreach ($column_names as $column_name) {
            $this->table->pick($column_name);
        }
        $this->selecting = [];

        // Clear joined relationships table list.
        $this->joinedRelationships = [];

        // Apply filters and fetch.
        $this->applyDeletionFilter();
        $records = $this->table->selectRecords($this->entityName);

        // Query related records.
        if (count($this->requestedRelationships) > 0) {
            foreach ($this->requestedRelationships as $arguments) {
                $this->fetchRelationship($records, ...$arguments);
            }
        }
        $this->requestedRelationships = [];

        return $records;
    }

    /**
     * Get a single record from the current filters and return it as an entity.
     * 
     * @return null|T
     */
    protected function getEntity(): mixed
    {
        // Get entities.
        $entities = $this->getEntities();

        // Check if got duplicated entries.
        if (count($entities) > 1) {
            $msg = 'Found multiple entries when querying for a single record.';
            throw new \RuntimeException($msg);
        }

        return count($entities) === 1 ? $entities[0] : null;
    }

    /**
     * Get all filtered records as a `Collection`.
     * 
     * @return Collection<T>
     */
    protected function list(): Collection
    {
        // Apply deletion filter without resetting.
        $previous_kdf_value = $this->keepDeletionFilter;
        $this->keepDeletionFilter = true;
        $this->applyDeletionFilter();
        $this->keepDeletionFilter = $previous_kdf_value;

        // Count all filtered records.
        $this->table->autoReset = false;
        $primary_key = $this->prefix($this->primaryKey);
        $filtered = $this->table->countRecords($primary_key);

        // Paginate.
        if ($this->pageLength !== null) {
            $offset = abs($this->pageLength * ($this->pageNumber - 1));
            $this->table
                ->offset($offset)
                ->limit($this->pageLength);
        }

        // Get records.
        $this->table->autoReset = true;
        $array = $this->getEntities();

        // Count total table records.
        $stored = $this->table->countRecords($this->primaryKey);

        // Create collection.
        $collection = new Collection(
            $this->pageNumber,
            $this->pageLength,
            $filtered,
            $stored,
            ...$array,
        );

        // Reset pagination.
        $this->resetPagination();

        return $collection;
    }

    /**
     * Prefix a column name.
     */
    protected function prefix(string $key, null|string $table = null): string
    {
        $table ??= $this->tableName;
        return "{$table}.{$key}";
    }

    /**
     * Fetch related records as a one-to-many relationship in the next query.
     */
    protected function relateOneToMany(
        string $model_class_name,
        string $left_key,
        string $right_key,
        null|callable $callback = null,
    ): void {
        $this->requestedRelationships[] = [
            $model_class_name,
            $left_key,
            $right_key,
            $callback,
        ];
    }

    /**
     * Join a one-to-one or many-to-one relationship.
     * 
     * @param $table_name Table to join.
     * @param $left_key Root table key.
     * @param $right_key Joined table key.
     */
    protected function relateOneToOne(
        string $table_name,
        string $left_key,
        string $right_key,
    ): void {
        // Prefix keys.
        $left_key = $this->prefix($left_key);
        $right_key = $this->prefix($right_key, $table_name);

        // Join tables.
        $this->table
            ->join($table_name)
            ->on($left_key, '=', $right_key)
            ->on($this->prefix('deleted_at', $table_name), '=', null);

        // Store joined table name.
        $this->joinedRelationships[] = $table_name;
    }

    /**
     * Reset all cached collection metadata.
     */
    protected function resetPagination(): void
    {
        $this->pageLength = null;
        $this->pageNumber = 1;
    }
}
