<?php

namespace Hertz\BasalModel\Transformer;

/**
 * Map Data from one structure to another structure
 *
 * @since 05.08.18
 * @copyright 37Hertz 2020
 * @author Tim Kirbach <t.kirbach@kirbach-design.de>
 */
abstract class AbstractTransformer
{
    const TRANSFORM_DROP = 0;
    const TRANSFORM_COPY = 1;

    const SET_ON_ENTITY = 1;
    const GET_FROM_ENTITY = 2;

    /**
     * mapping from entity property name to other data key and transformation function
     *
     * add the property mapping in the object constructor like this
     *
     *
     * $this->mappingDefinition = [
     *   // key on Entity           key on other data source    transformation from                transformation to
     *   //                                                     other data source to this entity   other data from this entity
     *   'id'                    => ['userId',                  static::TRANSFORM_COPY,            static::TRANSFORM_COPY],
     *   'createdAt'             => ['created',                 static::TRANSFORM_DATE,            static::TRANSFORM_DATE]
     * ];
     *
     * @var array
     */
    protected $mappingDefinition;

    /**
     * list of available transformations
     *
     * add your custom transformation functions in the object constructure like this
     *
     * $this->transformations = [
     *    static::TRANSFORM_FLOAT = function($input, $entity, $otherData) { return floatval($input); }
     * ]
     *
     * @var callable[]
     */
    protected $transformations;

    /**
     * applys transformation of type $type on data in $value
     *
     * @param $type static::TRANSFORM_*
     * @param $value mixed
     * @param $entity array|object
     * @param $otherData array|object
     * @return mixed
     */
    protected function transform($type, $value, $entity, $otherData)
    {
        // have some basic and typical transformations here (saves another function call)
        switch ($type) {
            case static::TRANSFORM_DROP:
                return null;
            case static::TRANSFORM_COPY:
                return $value;
            default:
                if (isset($this->transformations[$type])) {
                    return call_user_func($this->transformations[$type], $value, $entity, $otherData);
                }
        }

        return null;
    }

    /**
     * for a given property name from the $entity, it gets the source key and the transform definition
     *
     * @param $targetKey string
     * @return mixed
     */
    protected function getMapping($targetKey)
    {
        if (isset($this->mappingDefinition[$targetKey])) {
            return $this->mappingDefinition[$targetKey];
        }
        return false;
    }

    /**
     * the actual work horse:
     * - from array or object
     * - to array or object
     * - using mapping
     * - using transform functions
     *
     * @param array|object $thisEntity
     * @param array|object $otherEntity
     * @param integer $methodIndex has to be 1 or 2, to define direction
     * @return array|object
     * @throws \Exception
     */
    protected function doTransform(&$thisEntity, &$otherEntity, $direction)
    {

        if (!is_object($thisEntity) && !is_array($thisEntity)) {
            throw new \Exception(sprintf("'\$thisEntity' passed to %s is neither object nor array", __FUNCTION__));
        }

        if (!is_object($otherEntity) && !is_array($otherEntity)) {
            throw new \Exception(sprintf("'\$otherEntity' passed to %s is neither object nor array", __FUNCTION__));
        }

        $thisEntityIsObject = is_object($thisEntity);
        $otherEntityIsObject = is_object($otherEntity);

        $propertyList = $thisEntityIsObject ? array_keys(get_object_vars($thisEntity)) : array_keys($thisEntity);
        $otherEntityPropertyList = $otherEntityIsObject ? array_keys(get_object_vars($otherEntity)) : array_keys($otherEntity);

        foreach ($propertyList as $propertyName) {
            // get the maping information
            $mapping = $this->getMapping($propertyName);
            if (!$mapping) {
                continue;
            }

            // set the transformer information
            $otherEntityKey = $mapping[0];
            $transformer = $mapping[$direction];

            if ($direction == static::SET_ON_ENTITY) { // setting data on this entity
                if (in_array($otherEntityKey, $otherEntityPropertyList)) { // only if the other one has this property and a value
                    $otherEntityValue = $otherEntityIsObject ? $otherEntity->$otherEntityKey : $otherEntity[$otherEntityKey];
                    $newValue = $this->transform($transformer, $otherEntityValue, $thisEntity, $otherEntity);
                    if ($thisEntityIsObject) {
                        $thisEntity->$propertyName = $newValue;
                    } else {
                        $thisEntity[$propertyName] = $newValue;
                    }
                }
            } else { // getting data from this entity
                $entityValue = $thisEntityIsObject ? $thisEntity->$propertyName : $thisEntity[$propertyName];
                $outputValue = $this->transform($transformer, $entityValue, $thisEntity, $otherEntity);
                if ($outputValue) {
                    if ($otherEntityIsObject) {
                        $otherEntity->$propertyName = $outputValue;
                    } else {
                        $otherEntity[$propertyName] = $outputValue;
                    }
                }

            }
        }

        return $direction === static::SET_ON_ENTITY ? $thisEntity : $otherEntity;
    }

    /**
     * Populates all properties in $entity with the data from $inputData, according to the mapping definition in
     * self::mappingDefinition[] and the transformations methods in self::transformations[]
     *
     * from array to $entity, with defined mapping and defined transformation functions
     *
     * @param object|array $entity
     * @param object|array $inputData
     * @return object|array
     * @throws \Exception
     */
    public function set(&$entity, &$inputData)
    {
        $this->doTransform($entity, $inputData, static::SET_ON_ENTITY);
    }

    /**
     * Populates $outputData with all properties in $entity according to the mapping definition in
     * self::mappingDefinition[] and the transformations methods in self::transformations[]
     *
     * from $entity to array, with defined mapping and defined transformation functions
     *
     * @param object|array $entity
     * @return array
     * @throws \Exception
     */
    public function get(&$entity, &$outputData)
    {
        $this->doTransform($entity, $outputData, static::GET_FROM_ENTITY);
    }
}