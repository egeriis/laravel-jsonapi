<?php namespace EchoIt\JsonApi;

use Illuminate\Support\Collection;
use Illuminate\Http\Response as BaseResponse;

/**
 * Abstract class used to extend model API handlers from.
 *
 * @author Ronni Egeriis Persson <ronni@egeriis.me>
 */
abstract class Handler
{
    /**
     * Override this const in the extended to distinguish model handlers from each other.
     *
     * See under default error codes which bits are reserved.
     */
    const ERROR_SCOPE = 0;

    /**
     * Default error codes.
     */
    const ERROR_UNKNOWN_ID = 1;
    const ERROR_UNKNOWN_LINKED_RESOURCES = 2;
    const ERROR_NO_ID = 4;
    const ERROR_INVALID_ATTRS = 8;
    const ERROR_HTTP_METHOD_NOT_ALLOWED = 16;
    const ERROR_ID_PROVIDED_NOT_ALLOWED = 32;
    const ERROR_MISSING_DATA = 64;
    const ERROR_RESERVED_7 = 128;
    const ERROR_RESERVED_8 = 256;
    const ERROR_RESERVED_9 = 512;

    /**
     * Constructor.
     *
     * @param JsonApi\Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Check whether a method is supported for a model.
     *
     * @param  string $method HTTP method
     * @return boolean
     */
    public function supportsMethod($method)
    {
        return method_exists($this, static::methodHandlerName($method));
    }

    /**
     * Fulfill the API request and return a response.
     *
     * @return JsonApi\Response
     */
    public function fulfillRequest()
    {
        if ( ! $this->supportsMethod($this->request->method)) {
            throw new Exception(
                'Method not allowed',
                static::ERROR_SCOPE | static::ERROR_HTTP_METHOD_NOT_ALLOWED,
                BaseResponse::HTTP_METHOD_NOT_ALLOWED
            );
        }

        $methodName = static::methodHandlerName($this->request->method);
        $models = $this->{$methodName}($this->request);

        if (is_null($models)) {
            throw new Exception(
                'Unknown ID',
                static::ERROR_SCOPE | static::ERROR_UNKNOWN_ID,
                BaseResponse::HTTP_NOT_FOUND
            );
        }

        if ($models instanceof Response) {
            $response = $models;
        } else {
            $models->load($this->exposedRelationsFromRequest());
            $response = new Response($models, static::successfulHttpStatusCode($this->request->method));
            $response->linked = $this->getLinkedModels($models);
            $response->errors = $this->getNonBreakingErrors();
        }

        return $response;
    }

    /**
     * Returns which requested linked resources are available.
     *
     * @return array
     */
    protected function exposedRelationsFromRequest()
    {
        return array_intersect(static::$exposedRelations, $this->request->include);
    }

    /**
     * Returns which of the requested linked resources are not available.
     *
     * @return array
     */
    protected function unknownRelationsFromRequest()
    {
        return array_diff($this->request->include, static::$exposedRelations);
    }

    /**
     * Iterate through result set to fetch the requested linked resources.
     *
     * @param  Illuminate\Database\Eloquent\Collection|JsonApi\Model $models
     * @return array
     */
    protected function getLinkedModels($models)
    {
        $linked = [];
        $models = $models instanceof Collection ? $models : [$models];

        foreach ($models as $model) {
            foreach ($this->exposedRelationsFromRequest() as $key) {
                $value = static::getModelsForRelation($model, $key);
                if (is_null($value)) continue;

                $links = self::getCollectionOrCreate($linked, $key);

                foreach ($value as $obj) {
                    // Check whether the object is already included in the response on it's ID
                    if (in_array($obj->id, $links->lists('id'))) continue;

                    $links->push($obj);
                }
            }
        }

        return $linked;
    }

    /**
     * Return errors which did not prevent the API from returning a result set.
     *
     * @return array
     */
    protected function getNonBreakingErrors()
    {
        $errors = [];

        $unknownRelations = $this->unknownRelationsFromRequest();
        if (count($unknownRelations) > 0) {
            $errors[] = [
                'code' => static::ERROR_UNKNOWN_LINKED_RESOURCES,
                'title' => 'Unknown linked resources requested',
                'description' => 'These linked resources are not available: ' . implode(', ', $unknownRelations)
            ];
        }

        return $errors;
    }

    /**
     * A method for getting the proper HTTP status code for a successful request
     *
     * @param  string $method "PUT", "POST", "DELETE" or "GET"
     * @return int
     */
    public static function successfulHttpStatusCode($method)
    {
        switch ($method) {
            case 'PUT':
            case 'POST':
            case 'DELETE':
                return BaseResponse::HTTP_NO_CONTENT;

            case 'GET':
                return BaseResponse::HTTP_OK;
        }

        // Code shouldn't reach this point, but if it does we assume that the
        // client has made a bad request, e.g. PATCH
        return BaseResponse::HTTP_BAD_REQUEST;
    }

    /**
     * Convert HTTP method to it's handler method counterpart.
     *
     * @param  string $method HTTP method
     * @return string
     */
    protected static function methodHandlerName($method)
    {
        return 'handle' . ucfirst(strtolower($method));
    }

    /**
     * Returns the models from a relationship. Will always return as array.
     *
     * @param  Illuminate\Database\Eloquent\Model $model
     * @param  string $relationKey
     * @return array|Illuminate\Database\Eloquent\Collection
     */
    protected static function getModelsForRelation($model, $relationKey)
    {
        $relationModels = $model->{$relationKey};
        if (is_null($relationModels)) return null;

        if ( ! $relationModels instanceof Collection) return [ $relationModels ];
        return $relationModels;
    }

    /**
     * This method returns the value from given array and key, and will create a
     * new Collection instance on the key if it doesn't already exist
     *
     * @param  array &$array
     * @param  string $key
     * @return Illuminate\Database\Eloquent\Collection
     */
    protected static function getCollectionOrCreate(&$array, $key)
    {
        if (array_key_exists($key, $array)) return $array[$key];
        return ($array[$key] = new Collection);
    }
}
