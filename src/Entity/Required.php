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

namespace Laucov\Modeling\Entity;

/**
 * Indicates that an entity property is required.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Required
{
    /**
     * Properties that must be present to require the value.
     * 
     * @var array<string>
     */
    public array $with;

    /**
     * Create the attribute instance.
     */
    public function __construct(array $with = [])
    {
        foreach ($with as $name) {
            if (!is_string($name)) {
                $message = 'All property names must be strings.';
                throw new \InvalidArgumentException($message);
            }
        }

        $this->with = $with;
    }
}
