<?php namespace EchoIt\JsonApi;

/**
 * A class used to represented a client request to the API.
 *
 * @author Ronni Egeriis Persson <ronni@egeriis.me>
 */
class Request
{
    /**
     * Contains the HTTP method of the request
     *
     * @var string
     */
    public $method;

    /**
     * Contains an optional model ID from the request
     *
     * @var int
     */
    public $id;

    /**
     * Contains an array of linked resource collections to load
     *
     * @var array
     */
    public $include;

    /**
     * Requested offset
     *
     * @var int
     */
    public $offset;

    /**
     * Requested limit of entities returned
     *
     * @var int
     */
    public $limit;


    /**
     * Constructor.
     *
     * @param string $method
     * @param int    $id
     * @param array  $include
     */
    public function __construct($method, $id = null, $include = [], $offset = 0, $limit = 0)
    {
        $this->method = $method;
        $this->id = $id;
        $this->include = $include ?: [];
        $this->offset = $offset;
        $this->limit = $limit;
    }
}
