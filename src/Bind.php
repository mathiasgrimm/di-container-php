<?php namespace MathiasGrimm\DiContainer;


class Bind
{
    const TYPE_FACTORY   = 'FACTORY';
    const TYPE_SINGLETON = 'SINGLETON';
    const TYPE_INSTANCE  = 'INSTANCE';

    const CONTEXT_DEFAULT = 'DEFAULT';

    private $key;
    private $value;
    private $context;
    private $type;

    /**
     * @return mixed
     */
    public function getKey()
    {
        return $this->key;
    }


    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return mixed
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }


    public function __construct($key, $value, $type, $context)
    {
        $this->key     = $key;
        $this->value   = $value;
        $this->type    = $type;
        $this->context = $context;
    }
}