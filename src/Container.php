<?php namespace MathiasGrimm\DiContainer;

use Exception;
use InvalidArgumentException;
use MathiasGrimm\ArrayPath\ArrayPath;
use MathiasGrimm\DiContainer\Exception\ContainerProviderAlreadyRegistered;
use MathiasGrimm\DiContainer\Exception\NotResolvedDependencyException;
use MathiasGrimm\DiContainer\Exception\ParameterNotInstantiable;
use ReflectionClass;
use ReflectionMethod;

class Container
{
    /** @var ContainerProviderInterface[] */
    protected $providers = [];

    /** @var Bind[] */
    protected $bindings = [];

    protected $loadedBindings = [];

    public function __construct()
    {

    }

    /**
     * @param ContainerProviderInterface $containerProvider
     * @throws ContainerProviderAlreadyRegistered
     */
    public function register(ContainerProviderInterface $containerProvider)
    {
        $key = get_class($containerProvider);

        if (isset($this->providers[$key])) {
            throw new ContainerProviderAlreadyRegistered(
                "can't register container provider $key because it's already registered"
            );
        }

        $this->providers[$key] = $containerProvider;
        $containerProvider->register($this);
    }

    public function boot()
    {
        foreach ($this->providers as $provider) {
            $provider->boot($this);
        }
    }

    public function bindSingleton($key, $value, $context = null)
    {
        $context = $this->getContext($key, $context);
        $bind = new Bind($key, $value, Bind::TYPE_SINGLETON, $context);
        $this->bind($bind);
    }

    public function bindFactory($key, $value, $context = null)
    {
        if (!is_callable($value)) {
            throw new InvalidArgumentException(
                "binding {$key} expected to be of type callable, " . gettype($value) . " given"
            );
        }

        $context = $this->getContext($key, $context);
        $bind = new Bind($key, $value, Bind::TYPE_FACTORY, $context);
        $this->bind($bind);
    }

    public function bindInstance($key, $value, $context = null)
    {
        if (!is_object($value)) {
            throw new InvalidArgumentException(
                "binding {$key} expected to be of type instance, " . gettype($value) . " given"
            );
        }

        $context = $this->getContext($key, $context);
        $bind = new Bind($key, $value, Bind::TYPE_INSTANCE, $context);
        $this->bind($bind);
    }

    protected function bind(Bind $bind)
    {
        $sep = ArrayPath::getSeparator();
        ArrayPath::set($this->bindings, "{$bind->getKey()}{$sep}{$bind->getContext()}", $bind);
    }

    public function get($key, $params = [], $context = null)
    {
        $context = $this->getContext($key, $context);

        $sep = ArrayPath::getSeparator();

        if (ArrayPath::exists($this->loadedBindings, "{$key}{$sep}{$context}")) {
            return ArrayPath::get($this->loadedBindings, "{$key}{$sep}{$context}");
        }

        // if key was not registered we will try to dynamically load it using reflection
        if (!$this->has($key, $context)) {
            $ret = $this->resolve($key, $context);
            ArrayPath::set($this->loadedBindings, "{$key}{$sep}{$context}", $ret);
            return $ret;
        }

        /** @var Bind $bind */
        $bind  = ArrayPath::get($this->bindings, "{$key}{$sep}{$context}");
        $value = $bind->getValue();
        $ret   = null;

        switch ($bind->getType()) {
            case Bind::TYPE_SINGLETON: {
                if (is_callable($value)) {
                    $ret = $value($this, $params);
                } else {
                    $ret = $value;
                }
                break;
            }

            case Bind::TYPE_INSTANCE: {
                $ret = $value;
                break;
            }

            case Bind::TYPE_FACTORY: {
                return $value($this, $params);;
            }
        }

        ArrayPath::set($this->loadedBindings, "{$key}{$sep}{$context}", $ret);

        return $ret;

    }

    public function loaded($key, $context = null)
    {
        $context = $this->getContext($key, $context);

        $sep = ArrayPath::getSeparator();
        return ArrayPath::exists($this->loadedBindings, "{$key}{$sep}{$context}");
    }

    public function has($key, $context = null)
    {
        $context = $this->getContext($key, $context);

        $sep = ArrayPath::getSeparator();
        return ArrayPath::exists($this->bindings, "{$key}{$sep}{$context}");
    }

    public function unbind($key, $context = null)
    {
        $context = $this->getContext($key, $context);

        $sep = ArrayPath::getSeparator();

        ArrayPath::remove($this->bindings       , "{$key}{$sep}{$context}");
        ArrayPath::remove($this->loadedBindings , "{$key}{$sep}{$context}");
    }

    /**
     * resolves recursively the class dependencies
     *
     * @param string $class
     * @param $context
     * @return null|object
     * @throws NotResolvedDependencyException
     */
    protected function resolve($class, $context)
    {
        try {
            if ($this->has($class, $context)) {
                return $this->get($class, [], $context);
            } elseif ($this->has($class)) {
                return $this->get($class);
            }

            if ($dependencies = $this->getMethodDependencies($class, '__construct')) {
                $args = [];
                foreach ($dependencies as $dependency) {
                    if (!$dependency->getClass()) {
                        $e = "class {$class} has a non instantiable dependency. All parameters need to be either a "
                            ."class or an interface";

                        throw new ParameterNotInstantiable($e);
                    }

                    $args[] = $object = $this->resolve($dependency->getClass()->getName(), $class);
                }

                $refObject = new ReflectionClass($class);
                $object    = $refObject->newInstanceArgs($args);
            } else {
                $object = new $class;
            }

            return $object;
        } catch (Exception $e) {
            $err = "could not resolve dependency for {$class} with error: {$e->getMessage()}";
            throw new NotResolvedDependencyException($err, 0, $e);
        }
    }

    /**
     * @param string $class
     * @param string $method
     * @return ReflectionParameter[]
     */
    public function getMethodDependencies(string $class, string $method = '__construct')
    {
        $parameters = [];

        if (!method_exists($class, $method)) {
            return $parameters;
        }

        $refMethod  = new ReflectionMethod($class, $method);

        foreach ($refMethod->getParameters() as $parameter) {
            $parameters[] = $parameter;
        }

        return $parameters;
    }

    protected function getContext($key, $context)
    {
        return $context ? $context : $key;
    }
}