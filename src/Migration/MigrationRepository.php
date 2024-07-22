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

/**
 * Organizes migrations for one or more directories.
 */
class MigrationRepository
{
    /**
     * Registered files.
     * 
     * @var array<MigrationFile>
     */
    protected array $files = [];

    /**
     * Create the repository instance.
     */
    public function __construct(
        /**
         * Default migration file date format.
         */
        protected string $defaultDateFormat,
    ) {
    }

    /**
     * Add all migration files from the given directory.
     */
    public function addDirectory(
        string $directory,
        null|string $date_format = null,
    ): static {
        // Get directory filenames.
        $directory = rtrim($directory, '/\\');
        $basenames = array_diff(scandir($directory), ['.', '..']);

        // Create migration file objects.
        $date_format ??= $this->defaultDateFormat;
        foreach ($basenames as $basename) {
            $filename = $directory . DIRECTORY_SEPARATOR . $basename;
            $this->files[] = $this->createMigrationFile(
                $filename,
                $date_format,
            );
        }

        return $this;
    }

    /**
     * Add an individual migration file to this repository.
     */
    public function addFile(
        string $filename,
        null|string $date_format = null,
    ): static {
        // Add migration file.
        $date_format ??= $this->defaultDateFormat;
        $this->files[] = $this->createMigrationFile($filename, $date_format);

        return $this;
    }

    /**
     * List all migration files for the current directory/file set.
     * 
     * @return array<MigrationFile>
     */
    public function listFiles(): array
    {
        // Get and sort migration files.
        $files = $this->files;
        usort($files, function (MigrationFile $a, MigrationFile $b): int {
            return $a->date <=> $b->date;
        });

        return $files;
    }

    /**
     * Create a `MigrationFile` object.
     */
    public function createMigrationFile(
        string $filename,
        string $date_format,
    ): MigrationFile {
        return new MigrationFile($filename, $date_format);
    }
}
