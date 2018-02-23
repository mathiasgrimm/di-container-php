# mathiasgrimm/di-container-php

<a href="https://travis-ci.org/mathiasgrimm/di-container-php?branch=master"><img src="https://travis-ci.org/mathiasgrimm/di-container-php.svg" alt="Build Status"></a>
[![Coverage Status](https://coveralls.io/repos/github/mathiasgrimm/di-container-php/badge.svg)](https://coveralls.io/github/mathiasgrimm/di-container-php)

Simple yet effective IoC Container with automatic dependency resolution


## Basic Usage

### Singleton
When using singleton, the `Container->get()` method will always return the same object reference as it will 
be stored internally within the container.
```
$container = new Container();
$container->bindSingleton(SomeComponentInterface::class, function (Container $c, $params = []) {
    return new SomeComponentImplementation();
});

$aComponent = $container->get(SomeComponentInterface::class);

```

you can also bind non-objects, with arbitrary keys
```
$container = new Container();
$container->bindSingleton('db.credentials', [
    'user'     => getenv('db.username'),
    'password' => getenv('db.password')
]);

$dbCredentials = $container->get('db.credentials');

// OR
$container->bindSingleton('some-key', 'some-value');
$container->get('some-key');

```


### Factory
When using a factory binding, the `Container->get()` will always return a new instance of what was bound.
the `bindFactory` expects a callable (closure, or a class that implements the __invoke magic method)

```
$container = new Container();
$container->bindFactory(SomeComponentInterface::class, function (Container $c, $params = []) {
    return new SomeComponentImplementation();
});

$aComponent = $container->get(SomeComponentInterface::class);

```

### Instance
When using an instance binding it behaves the same as the singleton binding except it accepts only instances
 
```
$anObject  = new SomeObject();
$container = new Container();
$container->bindInstance(SomeInterface::class, $anObject);

$anObjectInstance = $container->get(SomeInterface::class);
echo $anObjectInstance === $anObject; // will always be true

// OR
$container->bindInstance('some-object', $anObject);
$anObjectInstance = $container->get('some-object');
echo $anObjectInstance === $anObject; // will always be true
```

### Extends
Extends will replace the original value with the new value and will pass the old value via param.
This way you could decorate your component.

The new binding will be of the same type. So if it was a factory it will still be a factory, if singleton it will still
be a singleton and so on. For this reason, you cannot extend a component that hasn't been defined. If you try to do so
you will get an ComponentNotRegisteredException exception

```

$container->bindSingleto(SomeInterface::class, function (Container $container, $params = []) {
    return new FileLogger();
});

$container->extend(SomeInterface::class, function (Container $container, $oldValue) {
    $logger = new DecoratorLogger($oldValue);
    return $logger;
});

```

### General Rules for all bindings
- you cannot bind a component that has been already used
- you cannot unbind a compoentn that has been already user
- you cannot extend a component that has never been defined
- you cannot extend a component that has been already used

## Usage with Container Providers
To centralise and/or make the bindings of your application more organised, you can use container providers.
To use them you need to register them in the container.
Every Container Provider has to implement the `ContainerProviderInterface`

```
class MyContainerProvider implements ContainerProviderInterface
{
    public function register(Container $container)
    {
        $container->bindSingleton(MailerInterface::class, function (Container $container, $params) {
            return new LocalMailer();
        });    
    }
    
    
    public function boot(Container $container)
    {
    
    }
}

$container->register(new MyContainerProvider());
```

Therefore you application can have multiple container providers and it helps extending your application 
especially because third party vendors can provide some providers

#### The `register` method 
This is where you can register your bindings and possibly not do anything else.
If you try to have other functionalities in this method it could be that another container provider is not
yet registered.

#### The `boot` method 
This is called only when all container providers have been registered and is safe to have some logic here
as at this point container providers are loaded



#### Please see a more complete example bellow

```
class MyContainerProvider implements ContainerProviderInterface
{
    public function register(Container $container)
    {
        $container->bindSingleton(MailerInterface::class, function (Container $container, $params) {
            return new LocalMailer();
        });    
    }
    
    
    public function boot(Container $container)
    {

    }
}

interface MailerInterface
{
    public function send(Mail $mail);
}

class LocalMailer implements MailerInterface
{
    public function send(Mail $mail)
    {
        file_put_contents('somefolder/mailer.log');
    }
}

class MyController
{
    protected $mailer;
    
    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer; // this will be the LocalMailer instance
    }
    
    public function emailUser($userId)
    {
        // ...
        $mail = new Mail();
        $mail->setTo($user->getEmail());
        // ...
        $this->mailer->send($mail);
    }
}

class HttpHandler
{
    protected $container;
    
    public function __construct(Container $container) 
    {
        $this->container = $container;
    }

    public function handle()
    {
        // gets controllerName, method and params based on the route
        // $controllerName = MyController::class;
        // $mthod          = emailUser
        // $params         = ['user' => 1];
        
        $controller = $this->container->get($controllerName);
        
        $response = call_user_func_array([$controller, $method], $params);
        // same as $controller->emailUser(1);
    }
}

```

#### boot method
The container `boot` method has to be called by your application so that all container providers will be booted
```
$container = new Container();
// $container->register(...);
// $container->register(...);
// $container->register(...);
$container->boot();
```

This does nothing more than loop through all Service Containers and call the `boot` on each one 

### Definition Order
It should not matter in which order you define your bindings as they are deferred until the moment they are needed.

For Example:
```
$container->bindSingleton(SomeInterfaceA::class, function (Container $c, $params = []) {
    $b = $container->get(SomeInterfaceB::class);
    $a = new SomeImplA($b);
    return $a;
});

$container->bindSingleton(SomeInterfaceB::class, function (Container $c, $params = []) {
    return new SomeImplB();
}); 

$container->get(SomeInterfaceA::class);

``` 

Even thought `SomeInterfaceA::class` depends on `SomeInterfaceB::class` and `SomeInterfaceB::class` 
was defined after `SomeInterfaceA::class` it will work just fine

### Overwriting implementation


## Utility Methods

### get()
Get will try to return an instance/value even if the Interface/Class was not defined. It will do so by using
reflection. It will also use a mixed approach, meaning, if parts of the dependency graph are registered, it
will use it, otherwise it will try to dynamically load it.

```
class SomeComponent
{
    
}

$container->get(SomeComponent::class); // will return a SomeComponent instance.
```

When the object is dynamically loaded it will be always a singleton

Concrete dependencies can be resolved automatically
```
class A {}
class B {}

class SomeComponent
{
    public function __construct(A $a, B $b)
    {
    
    }
}

$container->get(SomeComponent::class); // will return a SomeComponent instance and provide the dependencies
automatically
```

if the request class/interface does not exist a `NotResolvedDependencyException` exception will be thrown

#### Limitations
When defining a class dependency, only classes and interfaces can be used. Scalar types will result in 
a `ParameterNotInstantiable` exception

### has()
Has checks whether there is a binding registered for a given key
```
$container->has(SomeInterface::class); // returns true or false
```

If has returns `false` it does not mean a `get(SomeInterface::class)` will throw an exception as it could 
still be loaded dynamically

### loaded()
Checks whether a key is loaded into the container, that would happen after you issue a get for a singleton or
instance binding

```
$container->bindSingleton('some-key', 'some-key');
$container->loaded('some-key'); // return false
$container->get('some-key');
$container->loaded('some-key'); // returns true
```

For a factory binding it will always return false

If a key was never registered it will also return `false` 

### unbind()
Unbind removes all internal references to a given key

Both `has` and `loaded` will return false to a key that has been `unbind()`

If a key does not exists it will not throw any exceptions 

### frozen()
Checks whether a key is frozen on not.

#### frozen() vs. loaded()
A key can be frozen and not be loaded. This is the case for factories 

## Contextual Binding
Every utility method, including the `get`, have a possibility to pass a context.

There are some cases where you have components that depend on the same interface but actually use two different
implementations

```
$container->bindSingleton(LoggerInterface::class, function () {
    return new SlackLogger();
}, ControllerA::class)

$container->bindSingleton(LoggerInterface::class, function () {
    return new FileLogger();
}, ControllerB::class)

// you can explicitly inform the context to get the loggers
$container->get(LoggerInterface::class, [], ControllerA::class); // will return SlackLogger
$container->get(LoggerInterface::class, [], ControllerB::class); // will return FileLogger

// and you can also call the controller classes and the dependencies will be injected as you need
$container->get(ControllerA::class);
$container->get(ControllerB::class);

// ------------------------------------------------------
// controllers
// ------------------------------------------------------ 
class ControllerA 
{
    public function __construct(LoggerInterface $logger)
    {
        // logger will be the SlackLogger
    }
}

class ControllerB 
{
    public function __construct(LoggerInterface $logger)
    {
        // logger will be the FileLogger
    }
}
```

Contextual binding is a way to define: whe loading this class, please provide this implementation.