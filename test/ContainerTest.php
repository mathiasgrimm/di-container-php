<?php namespace MathiasGrimm\DiContainerTest;

use Exception;
use InvalidArgumentException;
use MathiasGrimm\DiContainer\Container;
use MathiasGrimm\DiContainer\ContainerFactory;
use MathiasGrimm\DiContainer\ContainerProviderInterface;
use MathiasGrimm\DiContainer\Exception\ComponentFrozen;
use MathiasGrimm\DiContainer\Exception\ComponentNotRegisteredException;
use MathiasGrimm\DiContainer\Exception\ContainerProviderAlreadyRegistered;
use MathiasGrimm\DiContainer\Exception\NotResolvedDependencyException;
use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase
{
    private function getContainer()
    {
        return new Container();
    }

    /**
     * @test
     */
    public function when_binding_is_singleton_it_returns_always_the_same_value()
    {
        $container = $this->getContainer();

        $container->bindSingleton(\SomeClass::class, function () {
            return new \stdClass();
        });

        $i1 = $container->get(\SomeClass::class);
        $i2 = $container->get(\SomeClass::class);

        $this->assertSame($i1, $i2);

        $object = new \stdClass();

        $container->bindSingleton(\SomeOtherClass::class, $object);

        $i1 = $container->get(\SomeOtherClass::class);
        $i2 = $container->get(\SomeOtherClass::class);

        $this->assertSame($i1, $i2);
        $this->assertSame($object, $i1);
    }

    /**
     * @test
     */
    public function when_binding_is_factory_it_returns_always_a_new_instance()
    {
        $container = $this->getContainer();

        $container->bindFactory(\SomeClass::class, function () {
            return new \stdClass();
        });

        $i1 = $container->get(\SomeClass::class);
        $i2 = $container->get(\SomeClass::class);

        $this->assertNotSame($i1, $i2);
    }

    /**
     * @test
     */
    public function when_binding_an_instance_it_returns_always_the_same_instance()
    {
        $container = $this->getContainer();

        $container->bindInstance(\SomeClass::class, $i1 = new \stdClass());

        $i1 = $container->get(\SomeClass::class);
        $i2 = $container->get(\SomeClass::class);

        $this->assertSame($i1, $i2);
    }

    /**
     * @test
     */
    public function singleton_binding_respects_context()
    {
        $container = $this->getContainer();

        $container->bindSingleton('some-key', 10);
        $container->bindSingleton('some-key', 20, \SomeClass::class);

        $this->assertEquals(10, $container->get('some-key'));
        $this->assertEquals(20, $container->get('some-key', [], \SomeClass::class));
    }

    /**
     * @test
     */
    public function factory_binding_respects_context()
    {
        $container = $this->getContainer();

        $container->bindSingleton('some-key', function () {
            return 10;
        });
        $container->bindSingleton('some-key', function () {
            return 20;
        }, \SomeClass::class);

        $this->assertEquals(10, $container->get('some-key'));
        $this->assertEquals(20, $container->get('some-key', [], \SomeClass::class));
    }

    /**
     * @test
     */
    public function instance_binding_respects_context()
    {
        $container = $this->getContainer();

        $container->bindSingleton('some-key', $i1 = new \stdClass());
        $container->bindSingleton('some-key', $i2 = new \stdClass(), \SomeClass::class);

        $this->assertSame($i1, $container->get('some-key'));
        $this->assertSame($i2, $container->get('some-key', [], \SomeClass::class));
    }

    /**
     * @test
     */
    public function has_returns_true_when_binding_was_registered()
    {
        $container = $this->getContainer();

        $container->bindSingleton('some-key', $i1 = new \stdClass());
        $this->assertTrue($container->has('some-key'));

        $container->bindSingleton('some-key-2', $i1 = new \stdClass(), SomeController::class);
        $this->assertTrue($container->has('some-key-2', SomeController::class));

        $container->bindInstance('some-key-3', $i1 = new \stdClass());
        $this->assertTrue($container->has('some-key-3'));

        $container->bindInstance('some-key-3', $i1 = new \stdClass(), SomeController::class);
        $this->assertTrue($container->has('some-key-3', SomeController::class));

        $container->bindFactory('some-key-3', function () {
            return 10;
        });
        $this->assertTrue($container->has('some-key-3'));

        $container->bindFactory('some-key-3', function () {
            return 10;
        }, SomeController::class);
        $this->assertTrue($container->has('some-key-3', SomeController::class));
    }

    /**
     * @test
     */
    public function has_returns_false_when_binding_was_not_registered()
    {
        $container = $this->getContainer();

        $container->bindSingleton('some-key', $i1 = new \stdClass(), SomeController::class);
        $this->assertFalse($container->has('some-key'));
    }

    /**
     * @test
     */
    public function loaded_returns_false_when_using_factory()
    {
        $container = $this->getContainer();

        $container->bindFactory('some-key', function () {
            return 10;
        });
        $container->get('some-key');
        $this->assertFalse($container->loaded('some-key'));
    }

    /**
     * @test
     */
    public function loaded_returns_true_when_using_singleton()
    {
        $container = $this->getContainer();

        $container->bindSingleton('some-key', function () {
            return 10;
        });
        $container->get('some-key');
        $this->assertTrue($container->loaded('some-key'));
    }

    /**
     * @test
     */
    public function loaded_returns_true_when_using_instance()
    {
        $container = $this->getContainer();

        $container->bindInstance('some-key', new \stdClass());
        $container->get('some-key');
        $this->assertTrue($container->loaded('some-key'));
    }

    /**
     * @test
     */
    public function unbind_removes_from_bindings()
    {
        $container = $this->getContainer();

        $container->bindInstance('some-key', new \stdClass());
        $container->unbind('some-key');
        $this->assertFalse($container->loaded('some-key'));
        $this->assertFalse($container->has('some-key'));
    }

    /**
     * @test
     */
    public function when_binding_instance_the_value_has_to_be_an_object()
    {
        $container = $this->getContainer();

        try {
            $container->bindInstance('some-key', 'some value');
            $this->fail('should not allow bind a non-object to an instance');
        } catch (InvalidArgumentException $e) {
        }

        $this->assertEquals("binding some-key expected to be of type instance, string given", $e->getMessage());

    }

    /**
     * @test
     */
    public function when_binding_factory_the_value_has_to_be_a_callable()
    {
        $container = $this->getContainer();

        try {
            $container->bindFactory('some-key', 'some value');
            $this->fail('should not allow bind a non-callable to a factory');
        } catch (InvalidArgumentException $e) {
        }

        $this->assertEquals("binding some-key expected to be of type callable, string given", $e->getMessage());

    }

    /**
     * @test
     */
    public function get_for_non_registered_classes_should_return_object()
    {
        $container = $this->getContainer();
        $this->assertInstanceOf(C1::class, $container->get(C1::class));
    }

    /**
     * @test
     */
    public function it_loads_the_class_dependencies()
    {
        $container = $this->getContainer();
        $container->bindSingleton(C1::class, function () {
            return new C1();
        });

        $this->assertInstanceOf(C4::class, $container->get(C4::class));


        $this->assertInstanceOf(C4::class, $container->get(C4::class));
    }

    /**
     * @test
     */
    public function it_fails_when_dependency_class_does_not_exist()
    {
        $container = $this->getContainer();

        try {
            $container->get(C5::class);
            $this->fail('should fail when one of the dependencies does not exist');
        } catch (NotResolvedDependencyException $e) {
        }

        $er = 'could not resolve dependency for MathiasGrimm\DiContainerTest\C5 with error: '
            . 'Class MathiasGrimm\DiContainerTest\InexistentClass does not exist';
        $this->assertEquals($er, $e->getMessage());

    }

    /**
     * @test
     */
    public function it_fails_when_dependency_parameter_is_non_instantiable()
    {
        $container = $this->getContainer();

        try {
            $container->get(C6::class);
            $this->fail('should fail when one of the dependencies is non instantiable');
        } catch (NotResolvedDependencyException $e) {
        }

        $er = 'could not resolve dependency for MathiasGrimm\DiContainerTest\C6 with error: class '
            . 'MathiasGrimm\DiContainerTest\C6 has a non instantiable dependency. All parameters need to be either a '
            . 'class or an interface';

        $this->assertEquals($er, $e->getMessage());
    }

    /**
     * @test
     */
    public function register_should_register_and_call_register_on_the_container_provider()
    {
        $container = $this->getContainer();
        $containerProvider = $this->getMockBuilder(ContainerProviderInterface::class)
            ->setMethods([])
            ->getMock();

        $containerProvider->expects($this->once())
            ->method('register')
            ->with($container);

        $container->register($containerProvider);
    }

    /**
     * @test
     */
    public function register_should_not_allow_to_register_same_provider_more_than_once()
    {
        $container = $this->getContainer();
        $containerProvider = new DummyContainerProvider();

        $container->register($containerProvider);
        try {
            $container->register($containerProvider);
            $this->fail('should not allow to register provider more than once');
        } catch (ContainerProviderAlreadyRegistered $e) {
        }

        $er = "can't register container provider MathiasGrimm\DiContainerTest\DummyContainerProvider because it's "
            . "already registered";

        $this->assertEquals($er, $e->getMessage());
    }

    /**
     * @test
     */
    public function boot_will_boot_all_registered_container_providers()
    {
        $container = $this->getContainer();
        $containerProvider1 = $this->getMockBuilder(ContainerProviderInterface::class)
            ->setMethods([])
            ->getMock();

        $containerProvider1->expects($this->once())
            ->method('boot')
            ->with($container);

        $containerProvider2 = $this->getMockBuilder(DummyContainerProvider::class)
            ->setMethods([])
            ->getMock();

        $containerProvider2->expects($this->once())
            ->method('boot')
            ->with($container);

        $container->register($containerProvider1);
        $container->register($containerProvider2);

        $container->boot();
    }

    /**
     * @test
     */
    public function dependency_graph_respects_context()
    {
        $container = $this->getContainer();

        // default context
        $container->bindSingleton(Logger::class, function (Container $c, $params = []) {
            return new SlackLogger();
        });

        // default context
        $container->bindSingleton(Logger::class, function (Container $c, $params = []) {
            return new FileLogger();
        }, ControllerB::class);

        $c1 = $container->get(ControllerA::class);
        $c2 = $container->get(ControllerB::class);

        $this->assertInstanceOf(SlackLogger::class, $c1->logger);
        $this->assertInstanceOf(FileLogger::class, $c2->logger);
    }

    /**
     * @test
     */
    public function it_is_possible_to_extend_when_key_exists()
    {
        $container = $this->getContainer();

        $container->bindSingleton('some-key', function (Container $c, $params = []) {
            $o = new \stdClass();
            $o->val = 1;

            return $o;
        });

        $container->extend('some-key', function (Container $c, $old) {
            $old->val++;

            return $old;
        });

        $this->assertEquals(2, $container->get('some-key')->val);
    }

    /**
     * @test
     */
    public function it_throws_exception_when_extending_and_key_does_not_exist()
    {
        $container = $this->getContainer();

        try {
            $container->extend('some-key', function (Container $c, $old) {
                $old->val++;

                return $old;
            });

            $this->fail("it should fail because some-key was never registered");
        } catch (ComponentNotRegisteredException $e) { }

        $this->assertEquals('you cannot extend some-key as it was never registered', $e->getMessage());
    }

    /**
     * @test
     */
    public function it_does_not_allow_extend_if_component_was_already_user()
    {
        $container = $this->getContainer();

        try {
            $container->bindSingleton('some-key', function (Container $c, $params = []) {
                return 10;
            });

            $container->get('some-key');

            $container->extend('some-key', function (Container $c, $old) {
                $old->val++;

                return $old;
            });

            $this->fail("it should fail because some-key was never registered");
        } catch (ComponentFrozen $e) { }

        $this->assertEquals('cannot redefine/extend some-key as it has been already used', $e->getMessage());
    }

    /**
     * @test
     */
    public function it_does_not_allow_to_bind_if_it_has_been_already_used()
    {
        $container = $this->getContainer();

        try {
            $container->bindSingleton('some-key', function (Container $c, $params = []) {
                return 10;
            });

            $container->get('some-key');

            $container->bindSingleton('some-key', function (Container $c, $params = []) {
                return 20;
            });

            $this->fail("it should fail because some-key was already registered");
        } catch (ComponentFrozen $e) { }

        $this->assertEquals('cannot redefine/extend some-key as it has been already used', $e->getMessage());
    }

    /**
     * @test
     */
    public function it_show_not_allow_unbind_if_it_has_been_used_already()
    {
        $container = $this->getContainer();

        try {
            $container->bindSingleton('some-key', function (Container $c, $params = []) {
                return 10;
            });

            $container->get('some-key');
            $container->unbind('some-key');

            $this->fail("it should fail because some-key was already registered");
        } catch (ComponentFrozen $e) { }

        $this->assertEquals('cannot redefine/extend some-key as it has been already used', $e->getMessage());
    }

    /**
     * @test
     */
    public function frozen_respects_context()
    {
        $container = $this->getContainer();
        $e = null;

        try {
            $container->bindSingleton('some-key', function (Container $c, $params = []) {
                return 10;
            }, 'some-context-1');

            $container->bindSingleton('some-key', function (Container $c, $params = []) {
                return 10;
            }, 'some-context-2');

            $container->get('some-key', [], 'some-context-1');
            $container->unbind('some-key', [], 'some-context-2');
        } catch (Exception $e) { }

        $this->assertNull($e);
    }

    /**
     * @test
     */
    public function factories_get_frozen_after_usage()
    {
        $container = $this->getContainer();

        $container->bindFactory('some-key', function (Container $c, $params = []) {
            return new \stdClass();
        });

        $container->get('some-key');
        $this->assertTrue($container->frozen('some-key'));
        $this->assertFalse($container->loaded('some-key'));

        try {
            $container->bindFactory('some-key', function (Container $c, $params = []) {
                return new \stdClass();
            });
            $this->fail("should not allow factory redefinition");
        } catch (ComponentFrozen $e) { }

        $this->assertEquals('cannot redefine/extend some-key as it has been already used', $e->getMessage());


    }
}

class C1
{

}

class C2
{
    public function __construct(C1 $c)
    {

    }
}

class C3
{
    public function __construct(C2 $c)
    {

    }
}

class C4
{
    public function __construct(C1 $c1, C2 $c2, C3 $c3)
    {

    }
}

class C5
{
    public function __construct(C1 $c1, C2 $c2, C3 $c3, InexistentClass $class)
    {

    }
}

class C6
{
    public function __construct(C1 $c1, C2 $c2, C3 $c3, $someValue)
    {

    }
}

class DummyContainerProvider implements ContainerProviderInterface
{

    public function register(Container $container)
    {
        // TODO: Implement register() method.
    }

    public function boot(Container $container)
    {
        // TODO: Implement boot() method.
    }
}

interface I1
{

}

class Impl1 implements I1
{

}

class Impl2 implements I1
{

}

class UsesImpl1
{
    public $impl;

    public function __construct(I1 $impl)
    {
        $this->impl = $impl;
    }
}

class UsesImpl2
{
    public $impl;

    public function __construct(I1 $impl)
    {
        $this->impl = $impl;
    }
}

// --------------------------------------------------------------------------------------------
interface Logger {}

class SlackLogger implements Logger
{

}

class FileLogger implements Logger
{

}

class ControllerA
{
    public $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }
}

class ControllerB
{
    public $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }
}

// --------------------------------------------------------------------------------------------