<?php namespace MathiasGrimm\DiContainer;

use ReflectionMethod;
use ReflectionParameter;

class DependencyResolver
{
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
}