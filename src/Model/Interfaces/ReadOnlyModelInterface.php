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

namespace Laucov\Modeling\Model\Interfaces;

use Laucov\Modeling\Entity\AbstractEntity;
use Laucov\Modeling\Model\Collection;
use Laucov\Modeling\Model\DeletionFilter;
use Laucov\Modeling\Model\SearchMode;

/**
 * Provides table data in form of entities and collections.
 * 
 * @template T of AbstractEntity
 */
interface ReadOnlyModelInterface
{
    /**
     * Check if a record exists.
     */
    public function exists(string ...$ids): bool;

    /**
     * Set how the model shall treat soft-deleted records.
     */
    public function filterDeleted(DeletionFilter $filter): static;

    /**
     * List all records.
     * 
     * @return Collection<T>
     */
    public function listAll(): Collection;

    /**
     * Offset and limit the next list from a page length and number.
     */
    public function paginate(int $page_length, int $page_number): static;

    /**
     * Find a single record by its primary key value.
     * 
     * @return null|T
     */
    public function retrieve(string $id): mixed;

    /**
     * Find multiple records by their primary key value.
     * 
     * @return array<T>
     */
    public function retrieveBatch(string ...$ids): array;

    /**
     * Filter the next list/retrieval searching a specific column.
     */
    public function search(
        string|array $column_or_columns,
        string $search,
        SearchMode $mode,
    ): static;

    /**
     * Sort the next list/retrieval.
     */
    public function sort(string $column_name, bool $descending = false): static;

    /**
     * Set the columns to get.
     */
    public function withColumns(string ...$column_names): static;
}
