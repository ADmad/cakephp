<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         3.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Test\TestCase\Datasource;

use Cake\Datasource\EntityNew;
use Cake\Datasource\Exception\MissingPropertyException;
use Cake\TestSuite\TestCase;
use Exception;
use InvalidArgumentException;
use Mockery;
use stdClass;
use TestApp\Model\Entity\UserProps;

/**
 * Entity test case.
 */
class EntityNewTest extends TestCase
{
    /**
     * Tests setting a single property in an entity without custom setters
     */
    public function testSetOneParamNoSetters(): void
    {
        $entity = new class extends EntityNew {
            protected $id;
            protected $foo;
        };

        $this->assertNull($entity->getOriginal('foo'));
        $entity->set('foo', 'bar', ['asOriginal' => true]);
        $this->assertSame('bar', $entity->foo);
        $this->assertSame('bar', $entity->getOriginal('foo'));

        $entity->set('foo', 'baz');
        $this->assertSame('baz', $entity->foo);
        $this->assertSame('bar', $entity->getOriginal('foo'));

        $entity->set('id', 1, ['asOriginal' => true]);
        $this->assertSame(1, $entity->id);
        $this->assertSame(1, $entity->getOriginal('id'));
        $this->assertSame('bar', $entity->getOriginal('foo'));
    }

    /**
     * Tests setting multiple properties without custom setters
     */
    public function testSetMultiplePropertiesNoSetters(): void
    {
        $entity = new class extends EntityNew {
            protected $id;
            protected $foo;
            protected $thing;
        };
        $entity->setAccess('*', true);

        $entity->set(['foo' => 'bar', 'id' => 1], ['asOriginal' => true]);
        $this->assertSame('bar', $entity->foo);
        $this->assertSame(1, $entity->id);

        $entity->set(['foo' => 'baz', 'id' => 2, 'thing' => 3]);
        $this->assertSame('baz', $entity->foo);
        $this->assertSame(2, $entity->id);
        $this->assertSame(3, $entity->thing);
        $this->assertSame('bar', $entity->getOriginal('foo'));
        $this->assertSame(1, $entity->getOriginal('id'));
    }

    /**
     * Test that getOriginal() retains falsey values.
     */
    public function testGetOriginal(): void
    {
        $entity = new class (
            ['false' => false, 'null' => null, 'zero' => 0, 'empty' => ''],
            ['markNew' => true],
        ) extends EntityNew  {
            protected $false;
            protected $null;
            protected $zero;
            protected $empty;
        };

        $this->assertNull($entity->getOriginal('null'));
        $this->assertFalse($entity->getOriginal('false'));
        $this->assertSame(0, $entity->getOriginal('zero'));
        $this->assertSame('', $entity->getOriginal('empty'));

        $entity->set(['false' => 'y', 'null' => 'y', 'zero' => 'y', 'empty' => '']);
        $this->assertNull($entity->getOriginal('null'));
        $this->assertFalse($entity->getOriginal('false'));
        $this->assertSame(0, $entity->getOriginal('zero'));
        $this->assertSame('', $entity->getOriginal('empty'));
    }

    /**
     * Test that getOriginal throws an exception for fields without original value
     * when called with second parameter "false"
     */
    public function testGetOriginalFallback(): void
    {
        $entity = new class (
            ['foo' => 'foo', 'bar' => 'bar'],
            ['markNew' => true],
        ) extends EntityNew  {
            protected $foo;
            protected $bar;
        };
        $this->assertNull($entity->getOriginal('baz', true));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot retrieve original value for field `baz`');
        $entity->getOriginal('baz', false);
    }

    /**
     * Test extractOriginal()
     */
    public function testExtractOriginal(): void
    {
        $entity = new class (
            [
                'id' => 1,
                'title' => 'original',
                'body' => 'no',
                'null' => null,
            ],
            ['markNew' => true]
        ) extends EntityNew {
            protected $id;
            protected $title;
            protected $body;
            protected $null;
        };
        $entity->set('body', 'updated body');
        $result = $entity->extractOriginal(['id', 'title', 'body', 'null', 'undefined']);
        $expected = [
            'id' => 1,
            'title' => 'original',
            'body' => 'no',
            'null' => null,
        ];
        $this->assertEquals($expected, $result);

        $result = $entity->extractOriginalChanged(['id', 'title', 'body', 'null', 'undefined']);
        $expected = [
            'body' => 'no',
        ];
        $this->assertEquals($expected, $result);

        $entity->set('null', 'not null');
        $result = $entity->extractOriginalChanged(['id', 'title', 'body', 'null', 'undefined']);
        $expected = [
            'null' => null,
            'body' => 'no',
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test that all original values are returned properly
     */
    public function testExtractOriginalValues(): void
    {
        $entity = new class (
            [
                'id' => 1,
                'title' => 'original',
                'body' => 'no',
                'null' => null,
            ],
            ['markNew' => true]
        ) extends EntityNew {
            protected $id;
            protected $title;
            protected $body;
            protected $null;
        };
        $entity->set('body', 'updated body');
        $result = $entity->getOriginalValues();
        $expected = [
            'id' => 1,
            'title' => 'original',
            'body' => 'no',
            'null' => null,
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Tests setting a single property using a setter function
     */
    public function testSetOneParamWithSetter(): void
    {
        $entity = new class extends EntityNew {
            protected ?string $name {
                set (?string $name) {
                    $this->name = 'Dr. ' . $name;
                }
            }
        };
        $entity->set('name', 'Jones');
        $this->assertSame('Dr. Jones', $entity->name);
    }

    /**
     * Tests setting multiple properties using a setter function
     */
    public function testMultipleWithSetter(): void
    {
        $entity = new class extends EntityNew {
            protected ?string $name {
                set (?string $name) {
                    $this->name = 'Dr. ' . $name;
                }
            }

            protected ?array $stuff {
                set (?array $stuff) {
                    $this->stuff = ['c', 'd'];
                }
            }
        };

        $entity->setAccess('*', true);
        $entity->set(['name' => 'Jones', 'stuff' => ['a', 'b']]);
        $this->assertSame('Dr. Jones', $entity->name);
        $this->assertEquals(['c', 'd'], $entity->stuff);
    }

    /**
     * Tests that the constructor will set initial properties
     */
    public function testConstructor(): void
    {
        $entity = $this->getMockBuilder(EntityNew::class)
            ->onlyMethods(['set'])
            ->disableOriginalConstructor()
            ->getMock();
        $entity->expects($this->exactly(2))
            ->method('set')
            ->with(
                ...self::withConsecutive(
                    [
                    ['a' => 'b', 'c' => 'd'], ['guard' => false, 'setter' => true],
                    ],
                    [['foo' => 'bar'], ['guard' => false, 'setter' => true]],
                ),
            );

        $entity->__construct(['a' => 'b', 'c' => 'd']);
        $entity->__construct(['foo' => 'bar']);
    }

    /**
     * Tests that the constructor will set initial properties and pass the guard
     * option along
     */
    public function testConstructorWithGuard(): void
    {
        $entity = $this->getMockBuilder(EntityNew::class)
            ->onlyMethods(['set'])
            ->disableOriginalConstructor()
            ->getMock();
        $entity->expects($this->once())
            ->method('set')
            ->with(['foo' => 'bar'], ['guard' => true, 'setter' => true]);
        $entity->__construct(['foo' => 'bar'], ['guard' => true]);
    }

    /**
     * Tests getting properties with no custom getters
     */
    public function testGetNoGetters(): void
    {
        $entity = new class (['id' => 1, 'foo' => 'bar']) extends EntityNew {
            protected $id;
            protected $foo;
        };
        $this->assertSame(1, $entity->get('id'));
        $this->assertSame('bar', $entity->get('foo'));
    }

    public function testRequirePresenceException(): void
    {
        $this->expectException(MissingPropertyException::class);
        $this->expectExceptionMessage('Property `not_present` does not exist for the entity `Cake\Datasource\EntityNew`');

        $entity = new EntityNew();
        $entity->requireFieldPresence();
        $entity->get('not_present');
    }

    public function testRequirePresenceNoException(): void
    {
        $entity = new class (['is_present' => null]) extends EntityNew {
            protected ?bool $is_present;
            protected string $bonus;
        };
        $entity->requireFieldPresence();
        $this->assertNull($entity->get('is_present'));

        $entity = new class extends EntityNew
        {
            protected array $_virtual = [
                'bonus',
            ];

            protected $bonus {
                get => 'bonus';
            }
        };
        $entity->requireFieldPresence();
        $this->assertSame('bonus', $entity->get('bonus'));
    }

    /**
     * Tests get with custom getter
     */
    public function testGetCustomGetters(): void
    {
        $entity = new class extends EntityNew {
            protected $name {
                get => 'Dr. ' . $this->name;
            }
        };
        $entity->set('name', 'Jones');
        $this->assertSame('Dr. Jones', $entity->get('name'));
    }

    /**
     * Tests get with custom getter
     */
    public function testGetCustomGettersAfterSet(): void
    {
        $entity = new class extends EntityNew {
            protected $name {
                get => 'Dr. ' . $this->name;
            }
        };
        $entity->set('name', 'Jones');
        $this->assertSame('Dr. Jones', $entity->get('name'));

        $entity->set('name', 'Mark');
        $this->assertSame('Dr. Mark', $entity->get('name'));
    }

    /**
     * Tests that the get cache is cleared by unset.
     */
    public function testGetCacheClearedByUnset(): void
    {
        $entity = new class extends EntityNew {
            protected ?string $name {
                get => 'Dr. ' . $this->name;
            }
        };
        $entity->set('name', 'Jones');
        $this->assertSame('Dr. Jones', $entity->get('name'));

        $entity->unset('name');
        $this->assertSame('Dr. ', $entity->get('name'));
    }

    /**
     * Test getting camelcased virtual fields.
     */
    public function testGetCamelCasedProperties(): void
    {
        $entity = new class extends EntityNew {
            protected $list_id_name {
                get => 'A name';
            }
        };
        $entity->setVirtual(['list_id_name']);
        $this->assertSame('A name', $entity->list_id_name);
    }

    /**
     * Test magic property setting with no custom setter
     */
    public function testMagicSet(): void
    {
        $entity = new class extends EntityNew {
            protected $name;
        };
        $entity->name = 'Jones';
        $this->assertSame('Jones', $entity->name);
        $entity->name = 'George';
        $this->assertSame('George', $entity->name);
    }

    /**
     * Tests magic set with custom setter function
     */
    public function testMagicSetWithSetter(): void
    {
        $entity = new class extends EntityNew {
            protected ?string $name {
                set (?string $name) {
                    $this->name = 'Dr. ' . $name;
                }
            }
        };
        $entity->name = 'Jones';
        $this->assertSame('Dr. Jones', $entity->name);
    }

    /**
     * Tests magic set with custom setter function using a Title cased property
     */
    public function testMagicSetWithSetterTitleCase(): void
    {
        $entity = new class extends EntityNew {
            protected ?string $Name {
                set (?string $name) {
                    $this->Name = 'Dr. ' . $name;
                }
            }
        };
        $entity->Name = 'Jones';
        $this->assertSame('Dr. Jones', $entity->Name);
    }

    /**
     * Tests the magic getter with a custom getter function
     */
    public function testMagicGetWithGetter(): void
    {
        $entity = new class extends EntityNew {
            protected $name {
                get => 'Dr. ' . $this->name;
            }
        };
        $entity->set('name', 'Jones');
        $this->assertSame('Dr. Jones', $entity->name);
    }

    /**
     * Tests magic get with custom getter function using a Title cased property
     */
    public function testMagicGetWithGetterTitleCase(): void
    {
        $entity = new class extends EntityNew {
            protected $Name {
                get => 'Dr. ' . $this->Name;
            }
        };
        $entity->set('Name', 'Jones');
        $this->assertSame('Dr. Jones', $entity->Name);
    }

    /**
     * Test indirectly modifying internal properties
     */
    public function testIndirectModificationFailure(): void
    {
        $entity = new class extends EntityNew {
            protected $things;
        };
        $entity->things = ['a', 'b'];
        $entity->things[] = 'c';
        $this->assertEquals(['a', 'b'], $entity->things);

        $entity->things = array_merge($entity->things, ['c']);
        $this->assertEquals(['a', 'b', 'c'], $entity->things);
    }

    /**
     * Tests has() method
     */
    public function testHas(): void
    {
        $entity = new class (['id' => 1, 'name' => 'Juan', 'foo' => null]) extends EntityNew {
            protected $id;
            protected $name;
            protected $foo;
        };
        $this->assertTrue($entity->has('id'));
        $this->assertTrue($entity->has('name'));
        $this->assertTrue($entity->has('foo'));
        $this->assertFalse($entity->has('last_name'));

        $this->assertTrue($entity->has(['id']));
        $this->assertTrue($entity->has(['id', 'name']));
        $this->assertTrue($entity->has(['id', 'foo']));
        $this->assertFalse($entity->has(['id', 'nope']));

        $entity = new class extends EntityNew {
            protected $things {
                get {
                    throw new Exception('$things::get() should not have been called');
                }
            }
        };
        $this->assertTrue($entity->has('things'));
    }

    /**
     * Tests unset one property at a time
     */
    public function testUnset(): void
    {
        $entity = new class (['id' => 1, 'name' => 'bar']) extends EntityNew {
            protected $id;
            protected $name;
        };
        $entity->unset('id');
        $this->assertFalse($entity->has('id'));
        $this->assertTrue($entity->has('name'));
        $entity->unset('name');
        $this->assertFalse($entity->has('id'));

        $this->assertSame([], $entity->toArray());
    }

    /**
     * Unsetting a property should not mark it as dirty.
     */
    public function testUnsetMakesClean(): void
    {
        $entity = new class (['id' => 1, 'name' => 'bar']) extends EntityNew {
            protected $id;
            protected $name;
        };
        $this->assertTrue($entity->isDirty('name'));
        $entity->unset('name');
        $this->assertFalse($entity->isDirty('name'), 'Removed properties are not dirty.');
    }

    /**
     * Tests unset with multiple properties
     */
    public function testUnsetMultiple(): void
    {
        $entity = new class (['id' => 1, 'name' => 'bar', 'thing' => 2]) extends EntityNew {
            protected $id;
            protected $name;
            protected $thing;
        };
        $entity->unset(['id', 'thing']);
        $this->assertFalse($entity->has('id'));
        $this->assertTrue($entity->has('name'));
        $this->assertFalse($entity->has('thing'));
    }

    /**
     * Tests the magic __isset() method
     */
    public function testMagicIsset(): void
    {
        $entity = new class (['id' => 1, 'name' => 'Juan', 'foo' => null]) extends EntityNew {
            protected $id;
            protected $name;
            protected $foo;
        };
        $this->assertTrue(isset($entity->id));
        $this->assertTrue(isset($entity->name));
        $this->assertFalse(isset($entity->foo));
        $this->assertFalse(isset($entity->thing));
    }

    /**
     * Tests the magic __unset() method
     */
    public function testMagicUnset(): void
    {
        $entity = $this->getMockBuilder(EntityNew::class)
            ->onlyMethods(['unset'])
            ->getMock();
        $entity->expects($this->once())
            ->method('unset')
            ->with('foo');
        unset($entity->foo);
    }

    /**
     * Tests isset with array access
     */
    public function testIssetArrayPatchable(): void
    {
        $entity = new class (['id' => 1, 'name' => 'Juan', 'foo' => null]) extends EntityNew {
            protected $id;
            protected $name;
            protected $foo;
        };
        $this->assertArrayHasKey('id', $entity);
        $this->assertArrayHasKey('name', $entity);
        $this->assertArrayNotHasKey('foo', $entity);
        $this->assertArrayNotHasKey('thing', $entity);
    }

    /**
     * Tests get property with array access
     */
    public function testGetArrayPatchable(): void
    {
        $entity = $this->getMockBuilder(EntityNew::class)
            ->onlyMethods(['get'])
            ->getMock();
        $entity->expects($this->exactly(2))
            ->method('get')
            ->with(
                ...self::withConsecutive(['foo'], ['bar']),
            )
            ->willReturn('worked', 'worked too');

        $this->assertSame('worked', $entity['foo']);
        $this->assertSame('worked too', $entity['bar']);
    }

    /**
     * Tests set with array access
     */
    public function testSetArrayPatchable(): void
    {
        $entity = $this->getMockBuilder(EntityNew::class)
            ->onlyMethods(['set'])
            ->getMock();
        $entity->setAccess('*', true);

        $entity->expects($this->exactly(2))
            ->method('set')
            ->with(
                ...self::withConsecutive(['foo', 1], ['bar', 2]),
            )
            ->willReturnSelf();

        $entity['foo'] = 1;
        $entity['bar'] = 2;
    }

    /**
     * Tests unset with array access
     */
    public function testUnsetArrayPatchable(): void
    {
        /** @var \Cake\ORM\Entity|\PHPUnit\Framework\MockObject\MockObject $entity */
        $entity = $this->getMockBuilder(EntityNew::class)
            ->onlyMethods(['unset'])
            ->getMock();
        $entity->expects($this->once())
            ->method('unset')
            ->with('foo');
        unset($entity['foo']);
    }

    /**
     * Tests serializing an entity as JSON
     */
    public function testJsonSerialize(): void
    {
        $data = ['name' => 'James', 'age' => 20, 'phones' => ['123', '457']];
        $entity = new UserProps($data);
        $this->assertEquals(json_encode($data), json_encode($entity));
    }

    /**
     * Tests serializing an entity as PHP
     */
    public function testPhpSerialize(): void
    {
        $data = ['name' => 'James', 'age' => 20, 'phones' => ['123', '457']];
        $entity = new UserProps($data);
        $copy = unserialize(serialize($entity));
        $this->assertInstanceOf(EntityNew::class, $copy);
        $this->assertEquals($data, $copy->toArray());
    }

    /**
     * Tests that jsonSerialize is called recursively for contained entities
     */
    public function testJsonSerializeRecursive(): void
    {
        $phone = $this->getMockBuilder(EntityNew::class)
            ->onlyMethods(['jsonSerialize'])
            ->getMock();
        $phone->expects($this->once())->method('jsonSerialize')->willReturn(['something']);
        $data = ['name' => 'James', 'age' => 20, 'phone' => $phone];
        $entity = new class ($data) extends EntityNew {
            protected $name;
            protected $age;
            protected $phone;
        };
        $expected = ['name' => 'James', 'age' => 20, 'phone' => ['something']];
        $this->assertEquals(json_encode($expected), json_encode($entity));
    }

    /**
     * Tests the extract method
     */
    public function testExtract(): void
    {
        $entity = new class ([
            'id' => 1,
            'title' => 'Foo',
            'author_id' => 3,
        ]) extends EntityNew {
            protected $id;
            protected $title;
            protected $author_id;
        };
        $expected = ['author_id' => 3, 'title' => 'Foo',];
        $this->assertEquals($expected, $entity->extract(['author_id', 'title']));

        $expected = ['id' => 1];
        $this->assertEquals($expected, $entity->extract(['id']));

        $expected = [];
        $this->assertEquals($expected, $entity->extract([]));

        $expected = ['id' => 1, 'craziness' => null];
        $this->assertEquals($expected, $entity->extract(['id', 'craziness']));
    }

    /**
     * Tests isDirty() method on a newly created object
     */
    public function testIsDirty(): void
    {
        $entity = new class ([
            'id' => 1,
            'title' => 'Foo',
            'author_id' => 3,
        ]) extends EntityNew {
            protected $id;
            protected $title;
            protected $author_id;
        };
        $this->assertTrue($entity->isDirty('id'));
        $this->assertTrue($entity->isDirty('title'));
        $this->assertTrue($entity->isDirty('author_id'));

        $this->assertTrue($entity->isDirty());

        $entity->setDirty('id', false);
        $this->assertFalse($entity->isDirty('id'));
        $this->assertTrue($entity->isDirty('title'));

        $entity->setDirty('title', false);
        $this->assertFalse($entity->isDirty('title'));
        $this->assertTrue($entity->isDirty(), 'should be dirty, one field left');

        $entity->setDirty('author_id', false);
        $this->assertFalse($entity->isDirty(), 'all fields are clean.');

        $entity2 = new class ([
            'id' => 1,
            'title' => 'Foo',
        ], ['markClean' => true]) extends EntityNew {
            protected $id;
            protected $title;
        };
        $this->assertFalse($entity2->isDirty());
        $this->assertFalse($entity2->isDirty('title'));

        $entity2->title = 'bar';
        $this->assertTrue($entity2->isDirty('title'));
    }

    /**
     * Test setDirty().
     */
    public function testSetDirty(): void
    {
        $entity = new class ([
            'id' => 1,
            'title' => 'Foo',
            'author_id' => 3,
        ], ['markClean' => true]) extends EntityNew {
            protected $id;
            protected $title;
            protected $author_id;
        };

        $this->assertFalse($entity->isDirty());
        $this->assertSame($entity, $entity->setDirty('title'));
        $this->assertSame($entity, $entity->setDirty('id', false));

        $entity->setErrors(['title' => ['badness']]);
        $entity->setDirty('title', true);
        $this->assertEmpty($entity->getErrors(), 'Making a field dirty clears errors.');
    }

    /**
     * Tests dirty() when altering properties values and adding new ones
     */
    public function testDirtyChangingProperties(): void
    {
        $entity = new class (['title' => 'Foo']) extends EntityNew {
            protected $title;
            protected $something;
        };

        $entity->setDirty('title', false);
        $this->assertFalse($entity->isDirty('title'));

        $entity->set('title', 'Foo');
        $this->assertTrue($entity->isDirty('title'));

        $entity->set('title', 'Foo');
        $this->assertTrue($entity->isDirty('title'));

        $entity->set('something', 'else');
        $this->assertTrue($entity->isDirty('something'));
    }

    /**
     * Tests extract only dirty properties
     */
    public function testExtractDirty(): void
    {
        $entity = new class ([
            'id' => 1,
            'title' => 'Foo',
            'author_id' => 3,
        ]) extends EntityNew {
            protected $id;
            protected $title;
            protected $author_id;
        };
        $entity->setDirty('id', false);
        $entity->setDirty('title', false);
        $expected = ['author_id' => 3];
        $result = $entity->extract(['id', 'title', 'author_id'], true);
        $this->assertEquals($expected, $result);
    }

    /**
     * Tests the getDirty method
     */
    public function testGetDirty(): void
    {
        $entity = new class ([
            'id' => 1,
            'title' => 'Foo',
            'author_id' => 3,
        ]) extends EntityNew {
            protected $id;
            protected $title;
            protected $author_id;
        };

        $expected = [
            'id',
            'title',
            'author_id',
        ];
        $this->assertSame($expected, $entity->getDirty());
    }

    /**
     * Tests the clean method
     */
    public function testClean(): void
    {
        $entity = new class ([
            'id' => 1,
            'title' => 'Foo',
            'author_id' => 3,
        ]) extends EntityNew {
            protected $id;
            protected $title;
            protected $author_id;
        };
        $this->assertTrue($entity->isDirty('id'));
        $this->assertTrue($entity->isDirty('title'));
        $this->assertTrue($entity->isDirty('author_id'));

        $entity->clean();
        $this->assertFalse($entity->isDirty('id'));
        $this->assertFalse($entity->isDirty('title'));
        $this->assertFalse($entity->isDirty('author_id'));
    }

    /**
     * Tests the isNew method
     */
    public function testIsNew(): void
    {
        $entity = new class ([
            'id' => 1,
            'title' => 'Foo',
            'author_id' => 3,
        ]) extends EntityNew {
            protected $id;
            protected $title;
            protected $author_id;
        };
        $this->assertTrue($entity->isNew());

        $entity->setNew(true);
        $this->assertTrue($entity->isNew());

        $entity->setNew(false);
        $this->assertFalse($entity->isNew());
    }

    /**
     * Tests the constructor when passing the markClean option
     */
    public function testConstructorWithClean(): void
    {
        $this->expectNotToPerformAssertions();

        $mock = Mockery::mock(EntityNew::class)->makePartial();
        $mock->shouldReceive('clean')->never();
        $mock->__construct();

        $entity = new class extends EntityNew {
            protected $id;
        };

        $mock = Mockery::mock($entity::class)->makePartial();
        $mock->shouldReceive('clean')->once();
        $mock->__construct([], ['markClean' => true]);

        $mock = Mockery::mock($entity::class)->makePartial();
        $mock->shouldReceive('clean')->once();
        $mock->__construct(['id' => 1], ['markClean' => true]);
    }

    /**
     * Tests the constructor when passing the markClean option
     */
    public function testConstructorWithMarkNew(): void
    {
        $this->expectNotToPerformAssertions();

        $mock = Mockery::mock(EntityNew::class)->makePartial();
        $mock->shouldReceive('setNew')->never();
        $mock->__construct();

        $mock = Mockery::mock(EntityNew::class)->makePartial();
        $mock->shouldReceive('setNew')->once();
        $mock->__construct([], ['markNew' => true]);
    }

    /**
     * Test toArray method.
     */
    public function testToArray(): void
    {
        $data = ['name' => 'James', 'age' => 20, 'phones' => ['123', '457']];
        $entity = new UserProps($data);

        $this->assertEquals($data, $entity->toArray());
    }

    /**
     * Test toArray recursive.
     */
    public function testToArrayRecursive(): void
    {
        $data = ['id' => 1, 'name' => 'James', 'age' => 20, 'phones' => ['123', '457']];
        $user = new class ($data) extends EntityNew {
            protected $id;
            protected $name;
            protected $age;
            protected $phones;
            protected $comments;
            protected $profile;
        };
        $comments = [
            new class (['user_id' => 1, 'body' => 'Comment 1']) extends EntityNew {
                protected $user_id;
                protected $body;
            },
            new class (['user_id' => 1, 'body' => 'Comment 2']) extends EntityNew {
                protected $user_id;
                protected $body;
            },
        ];
        $user->comments = $comments;
        $user->profile = new class (['email' => 'mark@example.com']) extends EntityNew {
            protected $email;
        };

        $expected = [
            'id' => 1,
            'name' => 'James',
            'age' => 20,
            'phones' => ['123', '457'],
            'profile' => ['email' => 'mark@example.com'],
            'comments' => [
                ['user_id' => 1, 'body' => 'Comment 1'],
                ['user_id' => 1, 'body' => 'Comment 2'],
            ],
        ];
        $this->assertEquals($expected, $user->toArray());
    }

    /**
     * Tests that an entity with entities and other misc types can be properly toArray'd
     */
    public function testToArrayMixed(): void
    {
        $test = new class ([
            'id' => 1,
            'foo' => [
                new class (['hi' => 'test']) extends EntityNew {
                    protected $hi;
                },
                'notentity' => 1,
            ],
        ]) extends EntityNew {
            protected $id;
            protected $foo;
        };
        $expected = [
            'id' => 1,
            'foo' => [
                ['hi' => 'test'],
                'notentity' => 1,
            ],
        ];
        $this->assertEquals($expected, $test->toArray());
    }

    /**
     * Test that get accessors are called when converting to arrays.
     */
    public function testToArrayWithPatchableor(): void
    {
        $entity = new class extends EntityNew {
            protected $name {
                get => 'Mr. ' . $this->name;
            }
            protected $email;
        };
        $entity->setAccess('*', true);
        $entity->set(['name' => 'Mark', 'email' => 'mark@example.com']);
        $expected = ['name' => 'Mr. Mark', 'email' => 'mark@example.com'];
        $this->assertEquals($expected, $entity->toArray());
    }

    /**
     * Test that toArray respects hidden properties.
     */
    public function testToArrayHiddenProperties(): void
    {
        $data = ['secret' => 'sauce', 'name' => 'mark', 'id' => 1];
        $entity = new class ($data) extends EntityNew {
            protected $secret;
            protected $name;
            protected $id;
        };
        $entity->setHidden(['secret']);
        $this->assertEquals(['name' => 'mark', 'id' => 1], $entity->toArray());
    }

    /**
     * Tests setting hidden properties.
     */
    public function testSetHidden(): void
    {
        $data = ['secret' => 'sauce', 'name' => 'mark', 'id' => 1];
        $entity = new class ($data) extends EntityNew {
            protected $secret;
            protected $name;
            protected $id;
        };
        $entity->setHidden(['secret']);

        $result = $entity->getHidden();
        $this->assertSame(['secret'], $result);

        $entity->setHidden(['name']);

        $result = $entity->getHidden();
        $this->assertSame(['name'], $result);
    }

    /**
     * Tests setting hidden properties with merging.
     */
    public function testSetHiddenWithMerge(): void
    {
        $data = ['secret' => 'sauce', 'name' => 'mark', 'id' => 1];
        $entity = new class ($data) extends EntityNew {
            protected $secret;
            protected $name;
            protected $id;
        };
        $entity->setHidden(['secret'], true);

        $result = $entity->getHidden();
        $this->assertSame(['secret'], $result);

        $entity->setHidden(['name'], true);

        $result = $entity->getHidden();
        $this->assertSame(['secret', 'name'], $result);

        $entity->setHidden(['name'], true);
        $result = $entity->getHidden();
        $this->assertSame(['secret', 'name'], $result);
    }

    /**
     * Test toArray includes 'virtual' properties.
     */
    public function testToArrayVirtualProperties(): void
    {
        $entity = new class extends EntityNew {
            protected $name {
                get => 'Jose';
            }
            protected $email;
        };
        $entity->setAccess('*', true);
        $entity->set(['email' => 'mark@example.com']);

        $entity->setVirtual(['name']);
        $expected = ['name' => 'Jose', 'email' => 'mark@example.com'];
        $this->assertEquals($expected, $entity->toArray());

        $this->assertEquals(['name'], $entity->getVirtual());

        $entity->setHidden(['name']);
        $expected = ['email' => 'mark@example.com'];
        $this->assertEquals($expected, $entity->toArray());
        $this->assertEquals(['name'], $entity->getHidden());
    }

    /**
     * Tests the getVisible() method
     */
    public function testGetVisible(): void
    {
        $entity = new class extends EntityNew {
            protected $foo;
            protected $bar;
        };
        $entity->foo = 'foo';
        $entity->bar = 'bar';

        $expected = $entity->getVisible();
        $this->assertSame(['foo', 'bar'], $expected);
    }

    /**
     * Tests setting virtual properties with merging.
     */
    public function testSetVirtualWithMerge(): void
    {
        $data = ['virt' => 'sauce', 'name' => 'mark', 'id' => 1];
        $entity = new class ($data) extends EntityNew {
            protected $virt;
            protected $name;
            protected $id;
        };
        $entity->setVirtual(['virt']);

        $result = $entity->getVirtual();
        $this->assertSame(['virt'], $result);

        $entity->setVirtual(['name'], true);

        $result = $entity->getVirtual();
        $this->assertSame(['virt', 'name'], $result);

        $entity->setVirtual(['name'], true);
        $result = $entity->getVirtual();
        $this->assertSame(['virt', 'name'], $result);
    }

    /**
     * Tests error getters and setters
     */
    public function testGetErrorAndSetError(): void
    {
        $entity = new EntityNew();
        $this->assertEmpty($entity->getErrors());

        $entity->setError('foo', 'bar');
        $this->assertEquals(['bar'], $entity->getError('foo'));

        $expected = [
            'foo' => ['bar'],
        ];
        $result = $entity->getErrors();
        $this->assertEquals($expected, $result);

        $indexedErrors = [2 => ['foo' => 'bar']];
        $entity = new EntityNew();
        $entity->setError('indexes', $indexedErrors);

        $expectedIndexed = [
            'indexes' => ['2' => ['foo' => 'bar']],
        ];
        $result = $entity->getErrors();
        $this->assertEquals($expectedIndexed, $result);
    }

    /**
     * Tests reading errors from nested validator
     */
    public function testGetErrorNested(): void
    {
        $entity = new EntityNew();
        $entity->setError('options', ['subpages' => ['_empty' => 'required']]);

        $expected = [
            'subpages' => ['_empty' => 'required'],
        ];
        $this->assertEquals($expected, $entity->getError('options'));

        $expected = ['_empty' => 'required'];
        $this->assertEquals($expected, $entity->getError('options.subpages'));
    }

    /**
     * Tests that it is possible to get errors for nested entities
     */
    public function testErrorsDeep(): void
    {
        $user = new EntityNew();
        $owner = new EntityNew();
        $author = new class ([
            'foo' => 'bar',
            'thing' => 'baz',
            'user' => $user,
            'owner' => $owner,
        ]) extends EntityNew {
            protected $foo;
            protected $thing;
            protected $user;
            protected $owner;
            protected $multiple;
        };
        $author->setError('thing', ['this is a mistake']);
        $user->setErrors(['a' => ['error1'], 'b' => ['error2']]);
        $owner->setErrors(['c' => ['error3'], 'd' => ['error4']]);

        $expected = ['a' => ['error1'], 'b' => ['error2']];
        $this->assertEquals($expected, $author->getError('user'));

        $expected = ['c' => ['error3'], 'd' => ['error4']];
        $this->assertEquals($expected, $author->getError('owner'));

        $author->set('multiple', [$user, $owner]);
        $expected = [
            ['a' => ['error1'], 'b' => ['error2']],
            ['c' => ['error3'], 'd' => ['error4']],
        ];
        $this->assertEquals($expected, $author->getError('multiple'));

        $expected = [
            'thing' => $author->getError('thing'),
            'user' => $author->getError('user'),
            'owner' => $author->getError('owner'),
            'multiple' => $author->getError('multiple'),
        ];
        $this->assertEquals($expected, $author->getErrors());
    }

    /**
     * Tests that check if hasErrors() works
     */
    public function testHasErrors(): void
    {
        $entity = new class extends EntityNew {
            protected $nested;
        };
        $hasErrors = $entity->hasErrors();
        $this->assertFalse($hasErrors);

        $nestedEntity = new class extends EntityNew {
            protected $description;
        };
        $entity->set([
            'nested' => $nestedEntity,
        ]);
        $hasErrors = $entity->hasErrors();
        $this->assertFalse($hasErrors);

        $nestedEntity->setError('description', 'oops');
        $hasErrors = $entity->hasErrors();
        $this->assertTrue($hasErrors);

        $hasErrors = $entity->hasErrors(false);
        $this->assertFalse($hasErrors);

        $entity->clean();
        $hasErrors = $entity->hasErrors();
        $this->assertTrue($hasErrors);
        $hasErrors = $entity->hasErrors(false);
        $this->assertFalse($hasErrors);

        $nestedEntity->clean();
        $hasErrors = $entity->hasErrors();
        $this->assertFalse($hasErrors);

        $entity->setError('foo', []);
        $this->assertFalse($entity->hasErrors());
    }

    /**
     * Test that errors can be read with a path.
     */
    public function testErrorPathReading(): void
    {
        $assoc = new EntityNew();
        $assoc2 = new EntityNew();
        $entity = new class ([
            'field' => 'value',
            'one' => $assoc,
            'many' => [$assoc2],
        ]) extends EntityNew {
            protected $field;
            protected $one;
            protected $many;
        };
        $entity->setError('wrong', 'Bad stuff');
        $assoc->setError('nope', 'Terrible things');
        $assoc2->setError('nope', 'Terrible things');

        $this->assertEquals(['Bad stuff'], $entity->getError('wrong'));
        $this->assertEquals(['Terrible things'], $entity->getError('many.0.nope'));
        $this->assertEquals(['Terrible things'], $entity->getError('one.nope'));
        $this->assertEquals(['nope' => ['Terrible things']], $entity->getError('one'));
        $this->assertEquals([0 => ['nope' => ['Terrible things']]], $entity->getError('many'));
        $this->assertEquals(['nope' => ['Terrible things']], $entity->getError('many.0'));

        $this->assertEquals([], $entity->getError('many.0.mistake'));
        $this->assertEquals([], $entity->getError('one.mistake'));
        $this->assertEquals([], $entity->getError('one.1.mistake'));
        $this->assertEquals([], $entity->getError('many.1.nope'));
    }

    /**
     * Tests that changing the value of a property will remove errors
     * stored for it
     */
    public function testDirtyRemovesError(): void
    {
        $entity = new class ((['a' => 'b'])) extends EntityNew {
            protected $a;
        };
        $entity->setError('a', 'is not good');
        $entity->set('a', 'c');
        $this->assertEmpty($entity->getError('a'));

        $entity->setError('a', 'is not good');
        $entity->setDirty('a', true);
        $this->assertEmpty($entity->getError('a'));
    }

    /**
     * Tests that marking an entity as clean will remove errors too
     */
    public function testCleanRemovesErrors(): void
    {
        $entity = new class ((['a' => 'b'])) extends EntityNew {
            protected $a;
        };
        $entity->setError('a', 'is not good');
        $entity->clean();
        $this->assertEmpty($entity->getErrors());
    }

    /**
     * Tests getAccessible() method
     */
    public function testGetPatchable(): void
    {
        $entity = new EntityNew();
        $entity->setAccess('*', false);
        $entity->setAccess('bar', true);

        $accessible = $entity->getAccessible();
        $expected = [
            '*' => false,
            'bar' => true,
        ];
        $this->assertSame($expected, $accessible);
    }

    /**
     * Tests isAccessible() and setAccess() methods
     */
    public function testIsPatchable(): void
    {
        $entity = new EntityNew();
        $entity->setAccess('*', false);
        $this->assertFalse($entity->isAccessible('foo'));
        $this->assertFalse($entity->isAccessible('bar'));

        $this->assertSame($entity, $entity->setAccess('foo', true));
        $this->assertTrue($entity->isAccessible('foo'));
        $this->assertFalse($entity->isAccessible('bar'));

        $this->assertSame($entity, $entity->setAccess('bar', true));
        $this->assertTrue($entity->isAccessible('foo'));
        $this->assertTrue($entity->isAccessible('bar'));

        $this->assertSame($entity, $entity->setAccess('foo', false));
        $this->assertFalse($entity->isAccessible('foo'));
        $this->assertTrue($entity->isAccessible('bar'));

        $this->assertSame($entity, $entity->setAccess('bar', false));
        $this->assertFalse($entity->isAccessible('foo'));
        $this->assertFalse($entity->isAccessible('bar'));
    }

    /**
     * Tests that an array can be used to set
     */
    public function testPatchableAsArray(): void
    {
        $entity = new EntityNew();
        $entity->setAccess(['foo', 'bar', 'baz'], true);
        $this->assertTrue($entity->isAccessible('foo'));
        $this->assertTrue($entity->isAccessible('bar'));
        $this->assertTrue($entity->isAccessible('baz'));

        $entity->setAccess('foo', false);
        $this->assertFalse($entity->isAccessible('foo'));
        $this->assertTrue($entity->isAccessible('bar'));
        $this->assertTrue($entity->isAccessible('baz'));

        $entity->setAccess(['foo', 'bar', 'baz'], false);
        $this->assertFalse($entity->isAccessible('foo'));
        $this->assertFalse($entity->isAccessible('bar'));
        $this->assertFalse($entity->isAccessible('baz'));
    }

    /**
     * Tests that a wildcard can be used for setting accessible properties
     */
    public function testPatchableWildcard(): void
    {
        $entity = new EntityNew();
        $entity->setAccess(['foo', 'bar', 'baz'], true);
        $this->assertTrue($entity->isAccessible('foo'));
        $this->assertTrue($entity->isAccessible('bar'));
        $this->assertTrue($entity->isAccessible('baz'));

        $entity->setAccess('*', false);
        $this->assertFalse($entity->isAccessible('foo'));
        $this->assertFalse($entity->isAccessible('bar'));
        $this->assertFalse($entity->isAccessible('baz'));
        $this->assertFalse($entity->isAccessible('newOne'));

        $entity->setAccess('*', true);
        $this->assertTrue($entity->isAccessible('foo'));
        $this->assertTrue($entity->isAccessible('bar'));
        $this->assertTrue($entity->isAccessible('baz'));
        $this->assertTrue($entity->isAccessible('newOne2'));
    }

    /**
     * Tests that only accessible properties can be set
     */
    public function testSetWithPatchable(): void
    {
        $entity = new class (['foo' => 1, 'bar' => 2]) extends EntityNew {
            protected $foo;
            protected $bar;
        };
        $options = ['guard' => true];
        $entity->setAccess('*', false);
        $entity->setAccess('foo', true);
        $entity->set('bar', 3, $options);
        $entity->set('foo', 4, $options);
        $this->assertSame(2, $entity->get('bar'));
        $this->assertSame(4, $entity->get('foo'));

        $entity->setAccess('bar', true);
        $entity->set('bar', 3, $options);
        $this->assertSame(3, $entity->get('bar'));
    }

    /**
     * Tests that only accessible properties can be set
     */
    public function testSetWithPatchableWithArray(): void
    {
        $entity = new class (['foo' => 1, 'bar' => 2]) extends EntityNew {
            protected $foo;
            protected $bar;
        };
        $options = ['guard' => true];
        $entity->setAccess('*', false);
        $entity->setAccess('foo', true);
        $entity->set(['bar' => 3, 'foo' => 4], $options);
        $this->assertSame(2, $entity->get('bar'));
        $this->assertSame(4, $entity->get('foo'));

        $entity->setAccess('bar', true);
        $entity->set(['bar' => 3, 'foo' => 5], $options);
        $this->assertSame(3, $entity->get('bar'));
        $this->assertSame(5, $entity->get('foo'));
    }

    /**
     * Test that accessible() and single property setting works.
     */
    public function testSetWithPatchableSingleProperty(): void
    {
        $entity = new class (['foo' => 1, 'bar' => 2]) extends EntityNew {
            protected $foo;
            protected $bar;
            protected $title;
            protected $body;
        };
        $entity->setAccess('*', false);
        $entity->setAccess('title', true);

        $entity->set(['title' => 'test', 'body' => 'Nope']);
        $this->assertSame('test', $entity->title);
        $this->assertNull($entity->body);

        $entity->body = 'Yep';
        $this->assertSame('Yep', $entity->body, 'Single set should bypass guards.');

        $entity->set('body', 'Yes');
        $this->assertSame('Yes', $entity->body, 'Single set should bypass guards.');
    }

    /**
     * Tests the entity's __toString method
     */
    public function testToString(): void
    {
        $entity = new class (['foo' => 1, 'bar' => 2]) extends EntityNew {
            protected $foo;
            protected $bar;
        };
        $this->assertEquals(json_encode($entity, JSON_PRETTY_PRINT), (string)$entity);
    }

    /**
     * Tests __debugInfo
     */
    public function testDebugInfo(): void
    {
        $entity = new class (['foo' => 'bar'], ['markClean' => true]) extends EntityNew {
            protected $foo;
            protected $somethingElse;
        };
        $entity->somethingElse = 'value';
        $entity->setAccess('id', false);
        $entity->setAccess('name', true);
        $entity->setVirtual(['baz']);
        $entity->setDirty('foo', true);
        $entity->setError('foo', ['An error']);
        $entity->setInvalidField('foo', 'a value');
        $entity->setSource('foos');
        $result = $entity->__debugInfo();
        $expected = [
            'foo' => 'bar',
            'somethingElse' => 'value',
            'baz' => null,
            '[new]' => true,
            '[accessible]' => ['*' => true, 'id' => false, 'name' => true],
            '[dirty]' => ['somethingElse' => true, 'foo' => true],
            '[original]' => [],
            '[originalFields]' => ['foo'],
            '[virtual]' => ['baz'],
            '[hasErrors]' => true,
            '[errors]' => ['foo' => ['An error']],
            '[invalid]' => ['foo' => 'a value'],
            '[repository]' => 'foos',
        ];
        $this->assertSame($expected, $result);
    }

    /**
     * Test the source getter
     */
    public function testGetAndSetSource(): void
    {
        $entity = new EntityNew();
        $this->assertSame('', $entity->getSource());
        $entity->setSource('foos');
        $this->assertSame('foos', $entity->getSource());
    }

    /**
     * Provides empty values
     *
     * @return array
     */
    public function emptyNamesProvider(): array
    {
        return [[''], [null]];
    }

    /**
     * Tests that trying to get an empty property name throws exception
     */
    public function testEmptyProperties(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $entity = new EntityNew();
        $entity->get('');
    }

    /**
     * Provides empty values
     */
    public function testIsDirtyFromClone(): void
    {
        $entity = new class (
            ['a' => 1, 'b' => 2],
            ['markNew' => false, 'markClean' => true],
        ) extends EntityNew {
            protected $a;
            protected $b;
        };

        $this->assertFalse($entity->isNew());
        $this->assertFalse($entity->isDirty());

        $cloned = clone $entity;
        $cloned->setNew(true);

        $this->assertTrue($cloned->isDirty());
        $this->assertTrue($cloned->isDirty('a'));
        $this->assertTrue($cloned->isDirty('b'));
    }

    /**
     * Tests getInvalid and setInvalid
     */
    public function testGetSetInvalid(): void
    {
        $entity = new EntityNew();
        $return = $entity->setInvalid([
            'title' => 'albert',
            'body' => 'einstein',
        ]);
        $this->assertSame($entity, $return);
        $this->assertSame([
            'title' => 'albert',
            'body' => 'einstein',
        ], $entity->getInvalid());

        $set = $entity->setInvalid([
            'title' => 'nikola',
            'body' => 'tesla',
        ]);
        $this->assertSame([
            'title' => 'albert',
            'body' => 'einstein',
        ], $set->getInvalid());

        $overwrite = $entity->setInvalid([
            'title' => 'nikola',
            'body' => 'tesla',
        ], true);
        $this->assertSame($entity, $overwrite);
        $this->assertSame([
            'title' => 'nikola',
            'body' => 'tesla',
        ], $entity->getInvalid());
    }

    /**
     * Tests getInvalidField
     */
    public function testGetSetInvalidField(): void
    {
        $entity = new EntityNew();
        $return = $entity->setInvalidField('title', 'albert');
        $this->assertSame($entity, $return);
        $this->assertSame('albert', $entity->getInvalidField('title'));

        $overwrite = $entity->setInvalidField('title', 'nikola');
        $this->assertSame($entity, $overwrite);
        $this->assertSame('nikola', $entity->getInvalidField('title'));
    }

    /**
     * Tests getInvalidFieldNull
     */
    public function testGetInvalidFieldNull(): void
    {
        $entity = new EntityNew();
        $this->assertNull($entity->getInvalidField('foo'));
    }

    /**
     * Test the isEmpty() check
     */
    public function testIsEmpty(): void
    {
        $entity = new class ([
            'array' => ['foo' => 'bar'],
            'emptyArray' => [],
            'object' => new stdClass(),
            'string' => 'string',
            'stringZero' => '0',
            'emptyString' => '',
            'intZero' => 0,
            'intNotZero' => 1,
            'floatZero' => 0.0,
            'floatNonZero' => 1.5,
            'null' => null,
        ]) extends EntityNew {
            protected $array;
            protected $emptyArray;
            protected $object;
            protected $string;
            protected $stringZero;
            protected $emptyString;
            protected $intZero;
            protected $intNotZero;
            protected $floatZero;
            protected $floatNonZero;
            protected $null;
        };

        $this->assertFalse($entity->isEmpty('array'));
        $this->assertTrue($entity->isEmpty('emptyArray'));
        $this->assertFalse($entity->isEmpty('object'));
        $this->assertFalse($entity->isEmpty('string'));
        $this->assertFalse($entity->isEmpty('stringZero'));
        $this->assertTrue($entity->isEmpty('emptyString'));
        $this->assertFalse($entity->isEmpty('intZero'));
        $this->assertFalse($entity->isEmpty('intNotZero'));
        $this->assertFalse($entity->isEmpty('floatZero'));
        $this->assertFalse($entity->isEmpty('floatNonZero'));
        $this->assertTrue($entity->isEmpty('null'));
    }

    /**
     * Test hasValue()
     */
    public function testHasValue(): void
    {
        $entity = new class ([
            'array' => ['foo' => 'bar'],
            'emptyArray' => [],
            'object' => new stdClass(),
            'string' => 'string',
            'stringZero' => '0',
            'emptyString' => '',
            'intZero' => 0,
            'intNotZero' => 1,
            'floatZero' => 0.0,
            'floatNonZero' => 1.5,
            'null' => null,
        ]) extends EntityNew {
            protected $array;
            protected $emptyArray;
            protected $object;
            protected $string;
            protected $stringZero;
            protected $emptyString;
            protected $intZero;
            protected $intNotZero;
            protected $floatZero;
            protected $floatNonZero;
            protected $null;
        };

        $this->assertTrue($entity->hasValue('array'));
        $this->assertFalse($entity->hasValue('emptyArray'));
        $this->assertTrue($entity->hasValue('object'));
        $this->assertTrue($entity->hasValue('string'));
        $this->assertTrue($entity->hasValue('stringZero'));
        $this->assertFalse($entity->hasValue('emptyString'));
        $this->assertTrue($entity->hasValue('intZero'));
        $this->assertTrue($entity->hasValue('intNotZero'));
        $this->assertTrue($entity->hasValue('floatZero'));
        $this->assertTrue($entity->hasValue('floatNonZero'));
        $this->assertFalse($entity->hasValue('null'));
    }

    /**
     * Test isOriginalField()
     */
    public function testIsOriginalField(): void
    {
        $entity = new class (['foo' => null]) extends EntityNew {
            protected $foo;
        };
        $return = $entity->isOriginalField('foo');
        $this->assertSame(true, $return);

        $entity = new class extends EntityNew {
            protected $foo;
        };
        $entity->set('foo', null);
        $return = $entity->isOriginalField('foo');
        $this->assertSame(false, $return);

        $return = $entity->isOriginalField('bar');
        $this->assertSame(false, $return);
    }

    /**
     * Test getOriginalFields()
     */
    public function testGetOriginalFields(): void
    {
        $entity = new class (['foo' => 'foo', 'bar' => 'bar']) extends EntityNew {
            protected $foo;
            protected $bar;
            protected $baz;
        };
        $entity->set('baz', 'baz');
        $return = $entity->getOriginalFields();
        $this->assertEquals(['foo', 'bar'], $return);

        $entity = new class extends EntityNew {
            protected $foo;
            protected $bar;
            protected $baz;
        };
        $entity->set('foo', 'foo');
        $entity->set('bar', 'bar');
        $entity->set('baz', 'baz');
        $return = $entity->getOriginalFields();
        $this->assertEquals([], $return);
    }

    /**
     * Test setOriginalField() inside EntityInterface::setDirty()
     */
    public function testSetOriginalFieldInSetDirty(): void
    {
        $entity = new class extends EntityNew {
            protected $foo;
        };
        $entity->set('foo', 'bar');

        $return = $entity->isOriginalField('foo');
        $this->assertSame(false, $return);

        $entity->setDirty('foo', false);

        $return = $entity->isOriginalField('foo');
        $this->assertSame(true, $return);
    }

    /**
     * Test setOriginalField() inside EntityInterface::clean()
     */
    public function testSetOriginalFieldInClean(): void
    {
        $entity = new class extends EntityNew {
            protected $foo;
        };
        $entity->set('foo', 'bar');

        $return = $entity->isOriginalField('foo');
        $this->assertSame(false, $return);

        $entity->clean();

        $return = $entity->isOriginalField('foo');
        $this->assertSame(true, $return);
    }

    /**
     * Test infinite recursion in getErrors and hasErrors
     * See https://github.com/cakephp/cakephp/issues/17318
     */
    public function testGetErrorsRecursionError(): void
    {
        $entity = new class extends EntityNew {
            protected $child;
        };
        $secondEntity = new class extends EntityNew {
            protected $parent;
        };

        $entity->set('child', $secondEntity);
        $secondEntity->set('parent', $entity);

        $expectedErrors = ['name' => ['_required' => 'Must be present.']];
        $secondEntity->setErrors($expectedErrors);

        $this->assertEquals(['child' => $expectedErrors], $entity->getErrors());
    }

    /**
     * Test infinite recursion in getErrors and hasErrors
     * See https://github.com/cakephp/cakephp/issues/17318
     */
    public function testHasErrorsRecursionError(): void
    {
        $entity = new class extends EntityNew {
            protected $child;
        };
        $secondEntity = new class extends EntityNew {
            protected $parent;
        };

        $entity->set('child', $secondEntity);
        $secondEntity->set('parent', $entity);

        $this->assertFalse($entity->hasErrors());
    }
}
