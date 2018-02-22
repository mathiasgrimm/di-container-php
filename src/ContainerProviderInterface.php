<?php namespace MathiasGrimm\DiContainer;

interface ContainerProviderInterface
{
    public function register(Container $container);
    public function boot(Container $container);
}