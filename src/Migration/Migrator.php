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
use Laucov\Db\Query\Table;
use Laucov\Db\Statement\ColumnDefinition;

/**
 * Organizes, runs and tracks migrations.
 */
class Migrator
{
    /**
     * Schema instance.
     */
    public Schema $schema;

    /**
     * Table instance.
     */
    public Table $table;

    /**
     * Create the migrator instance.
     */
    public function __construct(
        /**
         * Migration repository.
         */
        protected MigrationRepository $repository,

        /**
         * Database connection.
         */
        protected Connection $connection,

        /**
         * Table to register migrations.
         */
        protected string $tableName,
    ) {
    }

    /**
     * Get the last executed migration data.
     */
    public function getLast(): null|Migration
    {
        // Get records.
        $records = $this->getTable()
            ->sort('index', true)
            ->limit(1)
            ->selectRecords(Migration::class);
        
        return $records[0] ?? null;
    }

    /**
     * Run migrations until the specific index.
     */
    public function upgrade(null|int $to = null): void
    {
        // Get the last migration data.
        $last = $this->getLast();
        $batch = $last === null ? 0 : $last->batch + 1;
        $offset = $last === null ? 0 : $last->index + 1;

        // Get all files from the last registered index.
        $files = $this->repository->listFiles();
        $files = array_slice($files, $offset, null, true);

        // Run each one until the specified index.
        $records = [];
        foreach ($files as $i => $file) {
            // Stop if the requested index was reached.
            if ($to !== null && $i > $to) {
                break;
            }
            // Run and register the migration upgrade.
            $this->runMigration($file);
            $records[] = [
                'index' => $i,
                'batch' => $batch,
                'filename' => $file->filename,
                'name' => $file->name,
                'run_at' => date('Y-m-d H:i:s', time()),
            ];
        }

        // Insert upgrade records.
        $this->table->insertRecords(...$records);
    }

    /**
     * Create the table instance.
     */
    protected function createTable(): Table
    {
        // Check if the table exists.
        $schema = $this->getSchema();
        if (!in_array($this->tableName, $schema->getTables())) {
            $schema->createTable(
                $this->tableName,
                new ColumnDefinition('index', 'INT', 11),
                new ColumnDefinition('batch', 'INT', 11),
                new ColumnDefinition('filename', 'VARCHAR', 256),
                new ColumnDefinition('name', 'INT', 128),
                new ColumnDefinition('run_at', 'DATETIME'),
            );
        }

        return new Table($this->connection, $this->tableName);
    }

    /**
     * Get the schema instance.
     */
    protected function getSchema(): Schema
    {
        $this->schema ??= new Schema($this->connection);
        return $this->schema;
    }

    /**
     * Get the migration table instance.
     */
    protected function getTable(): Table
    {
        if (!isset($this->table)) {
            $this->table = $this->createTable();
        }

        return $this->table;
    }

    /**
     * Run a migration from its file object.
     */
    protected function runMigration(MigrationFile $migration_file): void
    {
        // Include the file.
        require_once $migration_file->filename;

        // Instantiate.
        $class_name = $migration_file->className;
        $migration = new $class_name($this->connection);
        
        // Run.
        $migration->upgrade();
    }
}
