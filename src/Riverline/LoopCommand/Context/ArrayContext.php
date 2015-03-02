<?php

namespace Riverline\LoopCommand\Context;

/**
 * Class ArrayContext
 * @package Riverline\LoopCommand\Context
 */
class ArrayContext implements LoopCommandContextInterface
{
    /**
     * @var array
     */
    private $data;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->data = array();
    }

    /**
     * Magic getter
     *
     * @param string $name
     * @return mixed
     */
    public function &__get ($name)
    {
        if (isset($this->data[$name])) {
            return $this->data[$name];
        }

        return null;
    }

    /**
     * Magic setter
     *
     * @param string $name
     * @param mixed  $value
     */
    public function __set ($name, $value)
    {
        $this->data[$name] = $value;
    }

    /**
     * Magic checker
     *
     * @param string $name
     * @return boolean
     */
    public function __isset ($name)
    {
        return isset($this->data[$name]);
    }

    /**
     * Magic unsetter
     *
     * @param string $name
     */
    public function __unset ($name)
    {
        unset($this->data[$name]);
    }
}
