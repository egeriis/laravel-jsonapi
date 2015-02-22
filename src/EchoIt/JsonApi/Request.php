<?php namespace EchoIt\JsonApi;

/**
 * A class used to represented a client request to the API.
 *
 * @author Ronni Egeriis Persson <ronni@egeriis.me>
 */
class Request
{
    
    /**
     * Contains the url of the request
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
     * Specifies the page number to return results for
     * @var integer
     */
    public $pageNumber;
    
    /**
     * Specifies the number of results to return per page. Only used if 
     * pagination is requested (ie. pageNumber is not null)
     *
     * @var integer
     */
    public $pageSize = 50;

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
     * @param integer $pageNumber
     * @param integer $pageSize
     */
    public function __construct($url, $method, $id = null, $content = null, $include = [], $sort = [], $filter = [], $pageNumber = null, $pageSize = null)
    {
        $this->url = $url;
        $this->method = $method;
        $this->id = $id;
        $this->content = $content;
        $this->include = $include ?: [];
        $this->sort = $sort ?: [];
        $this->filter = $filter ?: [];
        
        $this->pageNumber = $pageNumber ?: null;
        if ($pageSize) {
            $this->pageSize = $pageSize;
        }
    }
}
