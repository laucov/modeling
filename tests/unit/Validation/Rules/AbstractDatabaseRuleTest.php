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
use Laucov\Modeling\Entity\AbstractEntity;
use Laucov\Modeling\Entity\ErrorMessage;
use Laucov\Modeling\Entity\Required;
use Laucov\Modeling\Model\AbstractModel;
use Laucov\Modeling\Model\Collection;
use Laucov\Modeling\Validation\EntityValidator;
use Laucov\Modeling\Validation\Rules\AbstractDatabaseRule;
use Laucov\Modeling\Validation\Rules\Exists;
use Laucov\Validation\Rules\Length;
use Laucov\Validation\Rules\Regex;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Laucov\Modeling\Validation\Rules\AbstractDatabaseRule
 */
class AbstractDatabaseRuleTest extends TestCase
{
    /**
     * @covers ::createModel
     * @covers ::setConnectionFactory
     * @uses Laucov\Modeling\Model\AbstractModel::__construct
     */
    public function testCanValidate(): void
    {
        // Mock model.
        $model = $this->getMockBuilder(AbstractModel::class)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->disallowMockingUnknownTypes()
            ->onlyMethods(['cacheEntityKeys', 'createTable'])
            ->getMock();
        
        // Mock connection factory.
        $factory = $this->createMock(ConnectionFactory::class);
        
        // Create rule and test.
        $rule = new class ($model::class) extends AbstractDatabaseRule
        {
            public static $factory;
            public static $model;
            public function __construct(protected $modelName)
            {
            }
            public function getInfo(): array
            {
                return [];
            }
            public function validate(mixed $value): bool
            {
                static::$factory = $this->connections;
                $class_name = $this->modelName;
                static::$model = $this->createModel($class_name);
                return true;
            }
        };
        $rule->setConnectionFactory($factory)->validate('foo');
        $this->assertSame($factory, $rule::class::$factory);
        $this->assertInstanceOf($model::class, $rule::class::$model);
    }
}
