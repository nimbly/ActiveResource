<?php
/**
 * Created by PhpStorm.
 * User: brent
 * Date: 1/9/17
 * Time: 4:18 PM
 */

namespace ActiveResource;


class Collection implements \IteratorAggregate, \Countable, \ArrayAccess
{

    /** @var  Request */
    protected $request;

    /** @var ResponseAbstract  */
    protected $response;

    /** @var Model[] */
    protected $objects = [];

    /** @var int  */
    protected $index = 0;

    /**
     * Collection constructor.
     * @param string $className
     * @param array $data
     * @param Request|null $request
     * @param ResponseAbstract|null $response
     */
    public function __construct($className, array $data = [], Request $request = null, ResponseAbstract $response = null)
    {
        foreach($data as $object)
        {
            $this->objects[] = new $className($object, true);
        }

        $this->request = $request;
        $this->response = $response;
    }

    /**
     * Get the Request object from the last API call
     *
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Get the Response object from the last API call
     *
     * @return ResponseAbstract
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @param ResponseAbstract $response
     */
    public function setResponse(ResponseAbstract $response)
    {
        $this->response = $response;
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->objects);
    }

    public function first()
    {
        if( $this->offsetExists(0) ){
            return $this->offsetGet(0);
        }

        return null;
    }

    public function toArray()
    {
        $objects = [];
        foreach( $this->objects as $object ){
            if( $object instanceof Model ){
                $objects[] = $object->toArray();
            }
        }

        return $objects;
    }

    public function toJson()
    {
        return json_encode($this->toArray());
    }

    public function current()
    {
        return $this->objects[$this->index];
    }

    public function seek($position)
    {
        if( !($this->offsetExists($position)) ){
            throw new \OutOfBoundsException('Offset does not exist');
        }

        $this->index = $position;
    }

    public function next()
    {
        $this->index++;
    }

    public function rewind()
    {
        $this->index = 0;
    }

    public function count()
    {
        return count($this->objects);
    }

    public function valid()
    {
        return (($this->index+1) > $this->count());
    }

    public function key()
    {
        return $this->index;
    }

    public function offsetGet($offset)
    {
        return $this->objects[$offset];
    }

    public function offsetExists($offset)
    {
        return (array_key_exists($offset, $this->objects));
    }

    public function offsetSet($offset, $value)
    {
        if( $offset == null ){
            $this->objects[] = $value;
        }
        else {
            $this->objects[$offset] = $value;
        }
    }

    public function offsetUnset($offset)
    {
        unset($this->objects[$offset]);
    }
}