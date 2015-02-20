<?php namespace EchoIt\JsonApi;

/**
 * A class used to represented a client request to the API.
 *
 * @author Ronni Egeriis Persson <ronni@egeriis.me>
 */
class Request
{
    
    /**
     * Contains the URL of the request
     *
     * @var string
     */
    public $url;
    
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
     * Contains any content in request
     *
     * @var string
     */
    public $content;
    
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
     * @param string $url
     * @param string $method
     * @param int    $id
     * @param mixed $content
     * @param array  $include
     * @param array  $sort
     * @param array  $filter
     */
    public function __construct($url, $method, $id = null, $content = null, $include = [], $sort = [], $filter = [])
    {
        $this->url = $url;
        $this->method = $method;
        $this->id = $id;
        $this->content = $content;
        $this->include = $include ?: [];
        $this->sort = $sort ?: [];
        $this->filter = $filter ?: [];
    }
}
