<?php
namespace Hertz\BasalModel\Entity;

/**
 * Abstract Entity. By default all defined properties can get accessed with
 * get<PropertyName>(), set<PropertyName>()
 *
 * These can get type hinted in the implementing class.
 * They can get overridden to implement custom getters and setters
 *
 * @package Hertz\BasalModel\Entity
 * @since 26.06.2017
 * @revised 06.08.18
 * @copyright 37Hertz 2020
 * @author Tim Kirbach <coder@37hertz.com>
 */
abstract class AbstractEntity implements \ArrayAccess
{

    /**
     * checks if the requested $offset is a valid property:
     * * not starting with _
     * * defined in this object
     *
     * @param $offset
     * @return bool
     */
    protected function validProperty($offset)
    {
        if ("_" == substr($offset, 0, 1)) {
            throw new \InvalidArgumentException(sprintf("No access to protected property '%s' in class '%s'",
                $offset, get_called_class()));
        }
        if (!property_exists($this, $offset)) {
            throw new \InvalidArgumentException(sprintf("No property with name '%s' exists in class '%s'",
                $offset, get_called_class()));
        }
        return true;
    }

    /**
     * checks if the requested offset is a valid getter method
     *
     * @param $offset
     * @return bool
     */
    protected function validCalculatedProperty($offset){
        return method_exists($this, 'get' . lcfirst($offset));
    }

    public function offsetExists($offset)
    {
        return $this->validCalculatedProperty($offset) || $this->validProperty($offset);
    }

    public function offsetGet($offset)
    {
        if($this->validCalculatedProperty($offset)){
            $accessor = 'get' . lcfirst($offset);
            return $this->$accessor();
        }
        $this->validProperty($offset);
        return $this->$offset;
    }

    public function offsetSet($offset, $value)
    {
        $accessor = 'set' . lcfirst($offset);
        if(method_exists($this, $accessor)){
            $this->$accessor($value);
        } else {
            $this->validProperty($offset);
            $this->$offset = $value;
        }
    }

    public function offsetUnset($offset)
    {
        $this->validProperty($offset);
        unset($this->$offset);
    }

    /**
     * Universal get and set for entity properties.
     * This way we can use "@method \DateTime getUpdatedAt()" and "@method setUserId(int $userId)"
     * to type hint the getters and setters
     *
     * @param  string $name
     * @param  array $arguments
     * @return mixed
     * @throws \BadMethodCallException If no mutator/accessor can be found
     */
    public function __call($name, $arguments)
    {
        $prefix = substr($name, 0, 3);
        if (('set' == $prefix) || ('get' == $prefix)) {
            $property = lcfirst(substr($name, 3));

            // do not need to check for invalid properties here, that happens in offsetGet and offsetSet

            if ('set' == $prefix) {
                $this[$property] = array_shift($arguments);
                return $this;
            } else {
                return $this[$property];
            }
        }

        throw new \BadMethodCallException(sprintf(
            "No method like '%s->%s()' exists", get_called_class(), $name
        ));
    }


    /**
     * sets multiple values
     * @param $values
     */
    public function set($values)
    {
        foreach ($values as $key => $value) {
            $this->offsetSet($key, $value);
        }
    }

    /**
     * gets multiple values
     * @param $keys
     * @return array
     */
    public function get(array $keys){
        $values = [];
        foreach($keys as $key){
            $values[$key] = $this->offsetGet($key);
        }
        return $values;
    }

    /**
     * return all property keys
     * @return array
     */
    public function getKeys(){
        $keys = get_object_vars($this);
        $keys = array_filter(array_keys($keys), function($key){
            return substr($key, 0, 1) !== "_";
        });
        return $keys;
    }

}