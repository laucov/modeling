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

use Laucov\Modeling\Entity\AbstractEntity;

/**
 * Represents a list of database records.
 * 
 * @template T of AbstractEntity
 * @implements \Iterator<int, T>
 */
class Collection implements \Countable, \Iterator
{
    /**
     * Stored entities.
     * 
     * @var array<T>
     */
    protected array $entities;

    /**
     * Current key.
     */
    protected int $key = 0;

    /**
     * Create the collection instance.
     * 
     * @param T ...$entities
     */
    public function __construct(
        /**
         * Page which this collection represents.
         */
        public readonly int $page,

        /**
         * Maximum number of records shown by each page.
         */
        public readonly null|int $pageLength,

        /**
         * Number of records that match this collection's filter.
         */
        public readonly int $filteredCount,

        /**
         * Total number of records in the database table.
         */
        public readonly int $storedCount,
        AbstractEntity ...$entities,
    ) {
        $this->entities = $entities;
    }

    /**
     * Count entities in this collection.
     * 
     * The same result is obtained from passing this object to PHP's `count()`.
     */
    public function count(): int
    {
        return count($this->entities);
    }

    /**
     * Returns the current entity.
     * 
     * @return T
     */
    public function current(): mixed
    {
        return $this->entities[$this->key];
    }

    /**
     * Returns the value at specified offset.
     * 
     * @return T
     */
    public function get(int $offset): mixed
    {
        return $this->entities[$offset];
    }

    /**
     * Returns whether the specified offset exists.
     * 
     * @return T
     */
    public function has(int $offset): mixed
    {
        return array_key_exists($offset, $this->entities);
    }

    /**
     * Returns the index of the current entity.
     */
    public function key(): int
    {
        return $this->key;
    }

    /**
     * Move forward to next entity.
     */
    public function next(): void
    {
        $this->key++;
    }

    /**
     * Rewind the collection to the first entity.
     */
    public function rewind(): void
    {
        $this->key = 0;
    }

    /**
     * Checks if current position is valid.
     */
    public function valid(): bool
    {
        return array_key_exists($this->key, $this->entities);
    }
}
