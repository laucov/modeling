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

namespace Tests\Unit\Entity;

use Laucov\Modeling\Entity\Required;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Laucov\Modeling\Entity\Required
 */
class RequiredTest extends TestCase
{
    /**
     * @covers ::__construct
     */
    public function testCanInstantiate(): void
    {
        // Create instance.
        $instance = new Required(['prop_a', 'prop_b']);

        // Check info.
        $this->assertIsArray($instance->with);
        $this->assertContainsOnly('string', $instance->with);
        $this->assertCount(2, $instance->with);
        $this->assertSame('prop_a', $instance->with[0]);
        $this->assertSame('prop_b', $instance->with[1]);
    }

    /**
     * @coversNothing
     */
    public function testCanUseAsAttribute(): void
    {
        $this->expectNotToPerformAssertions();

        new class () {
            #[Required]
            public $a = 'foo';
        };
    }

    /**
     * @covers ::__construct
     */
    public function testMustInstantiateWithStringArray(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Required(['abc', ['invalid_arg']]);
    }
}
