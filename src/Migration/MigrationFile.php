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
 * Represents a database migration file.
 */
class MigrationFile
{
    /**
     * Migration class name.
     */
    public readonly string $className;

    /**
     * Migration date.
     */
    public readonly \DateTimeImmutable $date;

    /**
     * Migration name.
     */
    public readonly string $name;

    /**
     * Create the migration file instance.
     */
    public function __construct(
        /**
         * Migration filename.
         */
        public readonly string $filename,

        string $time_format,
    ) {
        // Validate file basename.
        $basename = basename($filename);
        if (preg_match('/^.+\-\w+(\.\w+)+$/', $basename) !== 1) {
            $message = 'Migration filenames must have the format "%s".';
            $message = sprintf($message, '{datetime}_{classname}.php');
            throw new \InvalidArgumentException($message);
        }

        // Check if filename exists.
        if (!file_exists($filename)) {
            $message = "Migration file {$filename} does not exist.";
            throw new \RuntimeException($message);
        }

        // Get the last hyphen position.
        $split_at = strrpos($basename, '-');

        // Set date.
        $datetime = substr($basename, 0, $split_at);
        $date = \DateTimeImmutable::createFromFormat($time_format, $datetime);
        if (is_bool($date)) {
            $message = 'Migration datetime does not match the format %s.';
            throw new \RuntimeException(sprintf($message, $time_format));
        }
        $this->date = $date;

        // Set name.
        $this->name = explode('.', substr($basename, $split_at + 1))[0];

        // Set class name.
        $class_name = $this->findClassName();
        if ($class_name === null) {
            $message = "No class declaration found in {$filename}.";
            throw new \RuntimeException($message);
        }
        $this->className = $class_name;
    }

    /**
     * Get the migration actual class name.
     */
    protected function findClassName(): null|string
    {
        // Get tokens.
        $tokens = token_get_all(file_get_contents($this->filename));

        // Get namespace and name.
        $namespace = '';
        $waiting_namespace = false;
        $waiting_name = false;
        foreach ($tokens as $token) {
            // Check if isn't just a character
            if (!is_array($token)) {
                continue;
            }
            // Get token name.
            $name = token_name($token[0]);
            if ($name === 'T_NAMESPACE') {
                // Start waiting for a namespace.
                $waiting_namespace = true;
            } elseif ($name === 'T_NAME_QUALIFIED' && $waiting_namespace) {
                // Register the migration namespace.
                $namespace = $token[1];
                $waiting_namespace = false;
            } elseif ($name === 'T_CLASS') {
                // Start waiting for a class name.
                $waiting_name = true;
            } elseif ($name === 'T_STRING' && $waiting_name) {
                // Register the class name with the current namespace.
                return strlen($namespace) > 0
                    ? ($namespace . '\\' . $token[1])
                    : $token[1];
            }
        }

        return null;
    }
}
