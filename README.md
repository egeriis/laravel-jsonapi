JSON API helpers for Laravel 5
=====

Important!
-----
This is a fork from [egeriis/laravel-jsonapi](https://github.com/egeriis/laravel-jsonapi) repository, I modified it based on my own needs. It works fine for me, but I'm not sure if it will work for you. Is compatible with JSON API 1.0 specs, but not all features are implemented at this time. I'll finish this later, but I won't be able to provide support or actively contribute to finish it. 

Main differences between this fork and the original repo:

1. JSON API 1.0 compilance
2. Caching (tested using redis driver)

Original readme:

[![Build Status](https://travis-ci.org/echo-it/laravel-jsonapi.svg)](https://travis-ci.org/echo-it/laravel-jsonapi)

Make it a breeze to create a [jsonapi.org](http://jsonapi.org/) compliant API with Laravel 5.

This library strives to be up to date with the latest JSON API updates—as the spec is still a work in progress. If you notice that something is missing, please contribute!

Installation
-----

1. Add `echo-it/laravel-jsonapi` to your composer.json dependency list (version 2.0.0 at the minimum for laravel 5 support)

2. Run `composer update`.

### Requirements

* PHP 5.4+
* Laravel 5


Using laravel-jsonapi
-----

This library is made with the concept of exposing models in mind, as found in the RESTful API approach.

In few steps you can expose your models:

1. **Create a route to direct the requests**

    In this example, we use a generic route for all models and HTTP methods:

    ```php
    Route::any('{model}/{id?}', 'ApiController@handleRequest');
    ```

2. **Create your controller to handle the request**

    Your controller is responsible to handling input, instantiating a handler class and returning the response.

    ```php
<?php namespace App\Http\Controllers;

use EchoIt\JsonApi\Request as ApiRequest;
use EchoIt\JsonApi\ErrorResponse as ApiErrorResponse;
use EchoIt\JsonApi\Exception as ApiException;
use Request;

class ApiController extends Controller
{
    /**
     * Create handler name from request name
     *
     * @param $requestedResource string The name of the model (in plural)
     *
     * @return string Class name of related resource
   	 */
   	public function getHandlerClassName($requestedResource) {
   		$modelName = Pluralizer::singular($requestedResource);
  
    	return 'App\\Handlers\\' . ucfirst($modelName) . 'Handler';
    }
    
    public function handleRequest($requestedResource, $id = null)
    {
        /**
         * Create handler name from model name
         * @var string
         */
        $handlerClass = $this->getHandlerClassName($requestedResource);

        if (class_exists($handlerClass)) {
			$url = Request::url();
            $method = Request::method();
            $include = ($i = Request::input('include')) ? explode(',', $i) : $i;
			$sort = ($i = Request::input('sort')) ? explode(',', $i) : $i;
			$filter = ($i = Request::except('sort', 'include', 'page')) ? $i : [];
			$content = Request::getContent();
			
			$page = Request::input('page');
			$pageSize = null;
			$pageNumber = null;
			if($page) {
				if(is_array($page) && !empty($page['size']) && !empty($page['number'])) {
					$pageSize = $page['size'];
					$pageNumber = $page['number'];
				} else {
					 return new ApiErrorResponse(400, 400, 'Expected page[size] and page[number]');
				}
			}
            $request = new ApiRequest(Request::url(), $method, $id, $content, $include, $sort, $filter, $pageNumber, $pageSize);
            $handler = new $handlerClass($request);

            // A handler can throw EchoIt\JsonApi\Exception which must be gracefully handled to give proper response
            try {
                $res = $handler->fulfillRequest();
            } catch (ApiException $e) {
                return $e->response();
            }
			
            return $res->toJsonResponse();
        }

        // If a handler class does not exist for requested model, it is not considered to be exposed in the API
        return new ApiErrorResponse(404, 404, 'Entity not found');
    }
}
    ```

3. **Create a handler for your model**

    A handler is responsible for exposing a single model.

    In this example we have create a handler which supports the following requests:

    * GET /users (ie. handleGet function)
    * GET /users/[id] (ie. handleGet function)
    * PUT /users/[id] (ie. handlePut function)
    
    Requests are automatically routed to appropriate handle functions.

    ```php
<?php namespace App\Handlers;

use Symfony\Component\HttpFoundation\Response;
use App\Models\User;

use EchoIt\JsonApi\Exception as ApiException;
use EchoIt\JsonApi\Request as ApiRequest;
use EchoIt\JsonApi\Handler as ApiHandler;
use Request;

/**
 * Handles API requests for Users.
 */
class UsersHandler extends ApiHandler
{
	const ERROR_SCOPE = 1024;
	
	/*
	* List of relations that can be included in response.
	* (eg. 'friend' could be included with ?include=friend)
	*/
	protected static $exposedRelations = [];
	
	/**
	 * Handles GET requests. 
	 * @param EchoIt\JsonApi\Request $request
	 * @return EchoIt\JsonApi\Model|Illuminate\Support\Collection|EchoIt\JsonApi\Response|Illuminate\Pagination\LengthAwarePaginator
	 */
	public function handleGet(ApiRequest $request)
	{
		//you can use the default GET functionality, or override with your own 
		return $this->handleGetDefault($request, new User);
	}
	
	/**
	 * Handles PUT requests. 
	 * @param EchoIt\JsonApi\Request $request
	 * @return EchoIt\JsonApi\Model|Illuminate\Support\Collection|EchoIt\JsonApi\Response
	 */
	public function handlePut(ApiRequest $request)
	{
		//you can use the default PUT functionality, or override with your own
		return $this->handlePutDefault($request, new User);
	}
}
    ```

    > **Note:** Extend your models from `EchoIt\JsonApi\Model` rather than `Eloquent` to get the proper response for linked resources.

Current features
-----

According to [jsonapi.org](http://jsonapi.org):

* [Resource Representations](http://jsonapi.org/format/#document-structure-resource-representations) as resource objects
* [Resource Relationships](http://jsonapi.org/format/#document-structure-resource-relationships)
   * Only through [Inclusion of Linked Resources](http://jsonapi.org/format/#fetching-includes)
* [Compound Documents](http://jsonapi.org/format/#document-structure-compound-documents)
* [Sorting](http://jsonapi.org/format/#fetching-sorting)
* [Filtering](http://jsonapi.org/format/#fetching-filtering)
* [Pagination] (http://jsonapi.org/format/#fetching-pagination)

The features in the Handler class are each in their own function (eg. handlePaginationRequest, handleSortRequest, etc.), so you can easily override them with your own behaviour if desired. 
	

Wishlist
-----

* Nested requests to fetch relations, e.g. /users/[id]/friends
* [Resource URLs](http://jsonapi.org/format/#document-structure-resource-urls)
* Requests for multiple [individual resources](http://jsonapi.org/format/#urls-individual-resources), e.g. `/users/1,2,3`
* [Sparse Fieldsets](http://jsonapi.org/format/#fetching-sparse-fieldsets)

* Some kind of caching mechanism
