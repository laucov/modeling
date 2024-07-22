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

namespace Laucov\Modeling\Migration;

use Laucov\Db\Data\Connection;
use Laucov\Db\Query\Schema;

/**
 * Represents a database migration step.
 */
abstract class AbstractMigration
{
    /**
     * Schema instance.
     */
    protected Schema $schema;

    /**
     * Create the migration instance.
     */
    public function __construct(
        /**
         * Database connection.
         */
        protected Connection $connection,
    ) {
        $this->schema = $this->createSchema();
    }

    /**
     * Execute the migration's downgrade procedure.
     */
    abstract public function downgrade(): void;

    /**
     * Execute the migration's upgrade procedure.
     */
    abstract public function upgrade(): void;

    /**
     * Create the `Schema` object.
     */
    public function createSchema(): Schema
    {
        return new Schema($this->connection);
    }
}
