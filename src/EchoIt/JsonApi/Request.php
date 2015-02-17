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
     * Contains an array of column names to sort on
     *
     * @var array
     */
    public $sort;
	
	/**
     * Contains an array of key/value pairs to filter on
     *
     * @var array
     */
    public $filter;

    /**
     * Constructor.
     *
     * @param string $method
     * @param int    $id
     * @param array  $include
     * @param array  $sort
     * @param array  $filter
     */
    public function __construct($method, $id = null, $include = [], $sort = [], $filter = [])
    {
        $this->method = $method;
        $this->id = $id;
        $this->include = $include ?: [];
		$this->sort = $sort ?: [];
		$this->filter = $filter ?: [];
    }
}
