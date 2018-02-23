# di-container-php
Simple yet effective IoC Container with automatic dependency resolution


## Basic Usage

### Singleton
When using singleton, the `Container->get()` method will always return the same object reference as it will be store internally within the container.
```
$container = new Container();
$container->bindSingleton(SomeComponentInterface::class, function (Container $c, $params = []) {
    return $someComponentImplementation;
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
    return $someComponentImplementation;
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
$container->bindSingleton('some-object', $anObject);
$anObjectInstance = $container->get('some-object');
echo $anObjectInstance === $anObject; // will always be true
```

## Usage with Container Providers
To centralise and or make the bindings of your application more organised, you can use Container Providers.
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

Therefore you application can have multiple ContainerProvider and it helps extending your application 
especially because third party vendors can provide some providers


Please see a more complete example bellow:

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
        $this->mailer = $mailer;
    }
    
    public function emailUser()
    {
        $mail = new Mail();
        $mail->setTo('some-email@gmail.com');
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

    public function handleHttp()
    {
        $controller = $container->get(MyController::class);
        $controller->emailUser();
    }
}

```

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

### Utility Methods

#### get()
Get will try to return an instance/value even if the Interface/Class was not defined. It will do so by using
reflection. It will also use a mixed approach, meaning, if parts of the dependency graph are registered, it
will use it, otherwise it will try to dynamically load it.

```
class SomeComponent
{
    
}

$container->get(SomeComponent::class); // will return a SomeComponent instance.
```

When the object if dynamically loaded it will be always a singleton

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

##### Limitations
When defining a class dependency, only classes and interfaces can be used. Scalar types will result in 
a `ParameterNotInstantiable` exception

#### has()
Has checks whether there is a binding registered for a given key
```
$container->has(SomeInterface::class); // returns true or false
```

If has returns `false` it does not mean a `get(SomeInterface::class)` will throw an exception as it could 
still be loaded dynamically

#### loaded()
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

#### unbind()
Unbind removes all internal reference to a given key

Both has and loaded will return false to a key that has been `unbind()`

If a key does not exists it will not throw any exceptions 