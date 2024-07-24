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

namespace Tests\Unit\Validation\Rules;

use Laucov\Db\Data\ConnectionFactory;
use Laucov\Modeling\Model\AbstractModel;
use Laucov\Modeling\Model\Collection;
use Laucov\Modeling\Validation\Rules\Unique;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Laucov\Modeling\Validation\Rules\Unique
 */
class UniqueTest extends TestCase
{
    /**
     * @covers ::__construct
     * @covers ::getInfo
     * @covers ::validate
     * @uses Laucov\Modeling\Validation\Rules\AbstractDatabaseRule::setConnectionFactory
     */
    public function testCanValidate(): void
    {
        // Mock collection.
        $map = [
            ['login', ['john', 'mary']],
        ];
        $collection = $this->createMock(Collection::class);
        $collection
            ->method('getColumn')
            ->willReturnMap($map);

        // Mock model.
        $methods = [
            'cacheEntityKeys',
            'createTable',
            'listAll',
            'withColumns',
        ];
        /** @var AbstractModel&MockObject */
        $model = $this->getMockBuilder(AbstractModel::class)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->disallowMockingUnknownTypes()
            ->setMockClassName('UniqueTestModel')
            ->addMethods(['doSomething'])
            ->onlyMethods($methods)
            ->getMock();
        $model
            ->expects($this->exactly(2))
            ->method('doSomething');
        $model
            ->method('listAll')
            ->willReturn($collection);
        $model
            ->expects($this->exactly(2))
            ->method('withColumns')
            ->with('login')
            ->willReturnSelf();
        
        // Mock rule.
        $arguments = ['UniqueTestModel', 'login', 'doSomething'];
        /** @var Unique&MockObject */
        $rule = $this->getMockBuilder(Unique::class)
            ->setConstructorArgs($arguments)
            ->onlyMethods(['createModel'])
            ->getMock();
        $rule
            ->expects($this->exactly(2))
            ->method('createModel')
            ->with('UniqueTestModel')
            ->willReturn($model);

        // Mock connection factory.
        $factory = $this->createMock(ConnectionFactory::class);

        // Test validation.
        $rule->setConnectionFactory($factory);
        $this->assertTrue($rule->validate('jane'));
        $this->assertFalse($rule->validate('john'));

        // Get info.
        $expected = [];
        $this->assertSame($expected, $rule->getInfo());
    }
}
