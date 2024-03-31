<?php

/**
 * This file is part of Laucov's Web Framework project.
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
 * @package web-framework
 * 
 * @author Rafael Covaleski Pereira <rafael.covaleski@laucov.com>
 * 
 * @license <http://www.apache.org/licenses/LICENSE-2.0> Apache License 2.0
 * 
 * @copyright © 2024 Laucov Serviços de Tecnologia da Informação Ltda.
 */

namespace Laucov\Modeling\Entity;

/**
 * Reads and manipulates objects.
 */
class ObjectReader
{
    /**
     * Compare two objects public properties values.
     */
    public static function count(object $a): int
    {
        $count = 0;
        foreach ($a as $v) {
            $count++;
        }

        return $count;
    }

    /**
     * Get an object with properties that are only present in `$object`.
     */
    public static function diff(object $object, object ...$objects): \stdClass
    {
        // Create object.
        $result = new \stdClass();

        // Compare each property.
        foreach ($object as $name => $value) {
            foreach ($objects as $o) {
                if (isset($o->$name) && $o->$name === $value) {
                    continue 2;
                }
            }
            $result->$name = $value;
        }

        return $result;
    }

    /**
     * Copy the public properties of an object to an array.
     */
    public static function toArray(object $subject): array
    {
        return get_object_vars($subject);
    }

    /**
     * Copy the public properties of an object to a `stdClass` object.
     */
    public static function toObject(object $subject): \stdClass
    {
        $result = new \stdClass();
        foreach ($subject as $name => $value) {
            $result->$name = $value;
        }

        return $result;
    }
}
