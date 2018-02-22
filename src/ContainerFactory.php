<?php namespace MathiasGrimm\DiContainer;

class ContainerFactory
{
    public function create()
    {
        $dependencyResolver = new DependencyResolver();
        $container = new Container($dependencyResolver);

        return $container;
    }
}