<?php namespace EchoIt\JsonApi;

use Illuminate\Support\Collection;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;
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
    const ERROR_UNKNOWN = 128;
    const ERROR_RESERVED_8 = 256;
    const ERROR_RESERVED_9 = 512;

    /**
     * Constructor.
     *
     * @param \EchoIt\JsonApi\Request $request
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
     * @return \EchoIT\JsonApi\Response
     * @throws Exception
     */
    public function fulfillRequest()
    {
        if (! $this->supportsMethod($this->request->method)) {
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
        } elseif ($models instanceof LengthAwarePaginator) {
            $items = new Collection($models->items());
            foreach ($items as $model) {
                $this->loadRelatedModels($model);
            }

            $response = new Response($items, static::successfulHttpStatusCode($this->request->method));

            $response->links = $this->getPaginationLinks($models);
            $response->included = $this->getIncludedModels($items);
            $response->errors = $this->getNonBreakingErrors();
        } else {
            if ($models instanceof Collection) {
                foreach ($models as $model) {
                    $this->loadRelatedModels($model);
                }
            } else {
                $this->loadRelatedModels($models);
            }

            $response = new Response($models, static::successfulHttpStatusCode($this->request->method, $models));

            $response->included = $this->getIncludedModels($models);
            $response->errors = $this->getNonBreakingErrors();
        }

        return $response;
    }

    /**
     * Load a model's relations
     *
     * @param   Model  $model  the model to load relations of
     * @return  void
     */
    protected function loadRelatedModels(Model $model) {
        // get the relations to load
        $relations = $this->exposedRelationsFromRequest($model);

        foreach ($relations as $relation) {
            // if this relation is loaded via a method, then call said method
            if (in_array($relation, $model->relationsFromMethod())) {
                $model->$relation = $model->$relation();
                continue;
            }

            $model->load($relation);
        }
    }

    /**
     * Returns which requested resources are available to include.
     *
     * @param Model $model
     * @return array
     */
    protected function exposedRelationsFromRequest($model = null)
    {
        $exposedRelations = static::$exposedRelations;

        // if no relations are to be included by request
        if (count($this->request->include) == 0) {
            // and if we have a model
            if ($model !== null && $model instanceof Model) {
                // then use the relations exposed by default
                $exposedRelations = array_intersect($exposedRelations, $model->defaultExposedRelations());
                $model->setExposedRelations($exposedRelations);
                return $exposedRelations;
            }

        }

        $exposedRelations = array_intersect($exposedRelations, $this->request->include);
        if ($model !== null && $model instanceof Model) {
            $model->setExposedRelations($exposedRelations);
        }

        return $exposedRelations;
    }

    /**
     * Returns which of the requested resources are not available to include.
     *
     * @return array
     */
    protected function unknownRelationsFromRequest()
    {
        return array_diff($this->request->include, static::$exposedRelations);
    }

    /**
     * Iterate through result set to fetch the requested resources to include.
     *
     * @param  \Illuminate\Database\Eloquent\Collection|\EchoIT\JsonApi\Model $models
     * @return array
     */
    protected function getIncludedModels($models)
    {
        $links = new Collection();
        $models = $models instanceof Collection ? $models : [$models];

        foreach ($models as $model) {
            foreach ($this->exposedRelationsFromRequest($model) as $relationName) {
                $value = static::getModelsForRelation($model, $relationName);

                if (is_null($value)) {
                    continue;
                }

                foreach ($value as $obj) {

                    // Check whether the object is already included in the response on it's ID
                    $duplicate = false;
                    $items = $links->where($obj->getKeyName(), '=', $obj->getKey());
                    if (count($items) > 0) {
                        foreach ($items as $item) {
                            if ($item->getResourceType() === $obj->getResourceType()) {
                                $duplicate = true;
                                break;
                            }
                        }
                        if ($duplicate) {
                            continue;
                        }
                    }

                    //add type property
                    $attributes = $obj->getAttributes();
                    $attributes['type'] = $obj->getResourceType();
                    $obj->setRawAttributes($attributes);

                    $links->push($obj);
                }
            }
        }

        return $links->toArray();
    }

    /**
     * Return pagination links as array
     * @param LengthAwarePaginator $paginator
     * @return array
     */
    protected function getPaginationLinks($paginator)
    {
        $links = [];

        $links['self'] = urldecode($paginator->url($paginator->currentPage()));
        $links['first'] = urldecode($paginator->url(1));
        $links['last'] = urldecode($paginator->url($paginator->lastPage()));

        $links['prev'] = urldecode($paginator->url($paginator->currentPage() - 1));
        if ($links['prev'] === $links['self'] || $links['prev'] === '') {
            $links['prev'] = null;
        }
        $links['next'] = urldecode($paginator->nextPageUrl());
        if ($links['next'] === $links['self'] || $links['next'] === '') {
            $links['next'] = null;
        }
        return $links;
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
                'title' => 'Unknown included resource requested',
                'description' => 'These included resources are not available: ' . implode(', ', $unknownRelations)
            ];
        }

        return $errors;
    }

    /**
     * A method for getting the proper HTTP status code for a successful request
     *
     * @param  string $method "PUT", "POST", "DELETE" or "GET"
     * @param  Model|null $model The model that a PUT request was executed against
     * @return int
     */
    public static function successfulHttpStatusCode($method, $model = null)
    {
        // if we did a put request, we need to ensure that the model wasn't
        // changed in other ways than those specified by the request
        //     Ref: http://jsonapi.org/format/#crud-updating-responses-200
        if (($method === 'PATCH' || $method === 'PUT') && $model instanceof Model) {
            // check if the model has been changed
            if ($model->isChanged()) {
                // return our response as if there was a GET request
                $method = 'GET';
            }
        }

        switch ($method) {
            case 'POST':
                return BaseResponse::HTTP_CREATED;
            case 'PATCH':
            case 'PUT':
            case 'DELETE':
                return BaseResponse::HTTP_NO_CONTENT;
            case 'GET':
                return BaseResponse::HTTP_OK;
        }

        // Code shouldn't reach this point, but if it does we assume that the
        // client has made a bad request.
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
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @param  string $relationKey
     * @return array|\Illuminate\Database\Eloquent\Collection
     * @throws Exception
     */
    protected static function getModelsForRelation($model, $relationKey)
    {
        if (!method_exists($model, $relationKey)) {
            throw new Exception(
                    'Relation "' . $relationKey . '" does not exist in model',
                    static::ERROR_SCOPE | static::ERROR_UNKNOWN_ID,
                    BaseResponse::HTTP_INTERNAL_SERVER_ERROR
                );
        }
        $relationModels = $model->{$relationKey};
        if (is_null($relationModels)) {
            return null;
        }

        if (! $relationModels instanceof Collection) {
            return [ $relationModels ];
        }

        return $relationModels;
    }

    /**
     * This method returns the value from given array and key, and will create a
     * new Collection instance on the key if it doesn't already exist
     *
     * @param  array &$array
     * @param  string $key
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected static function getCollectionOrCreate(&$array, $key)
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }
        return ($array[$key] = new Collection);
    }

    /**
     * The return value of this method will be used as the key to store the
     * linked or included model from a relationship. Per default it will return the plural
     * version of the relation name.
     * Override this method to map a relation name to a different key.
     *
     * @param  string $relationName
     * @return string
     */
    protected static function getModelNameForRelation($relationName)
    {
        return \str_plural($relationName);
    }

    /**
     * Function to handle sorting requests.
     *
     * @param  array $cols list of column names to sort on
     * @param  \EchoIt\JsonApi\Model $model
     * @return \EchoIt\JsonApi\Model
     * @throws Exception
     */
    protected function handleSortRequest($cols, $model)
    {
        foreach ($cols as $col) {
            $directionSymbol = substr($col, 0, 1);
            if ($directionSymbol === "+" || substr($col, 0, 3) === '%2B') {
                $dir = 'asc';
            } elseif ($directionSymbol === "-") {
                $dir = 'desc';
            } else {
                throw new Exception(
                    'Sort direction not specified but is required. Expecting "+" or "-".',
                    static::ERROR_SCOPE | static::ERROR_UNKNOWN_ID,
                    BaseResponse::HTTP_BAD_REQUEST
                );
            }
            $col = substr($col, 1);
            $model = $model->orderBy($col, $dir);
        }
        return $model;
    }

    /**
     * Parses content from request into an array of values.
     *
     * @param  string $content
     * @param  string $type the type the content is expected to be.
     * @return array
     * @throws Exception
     */
    protected function parseRequestContent($content, $type)
    {
        $content = json_decode($content, true);
        if (empty($content['data'])) {
            throw new Exception(
                'Payload either contains misformed JSON or missing "data" parameter.',
                static::ERROR_SCOPE | static::ERROR_INVALID_ATTRS,
                BaseResponse::HTTP_BAD_REQUEST
            );
        }

        $data = $content['data'];
        if (!isset($data['type'])) {
            throw new Exception(
                '"type" parameter not set in request.',
                static::ERROR_SCOPE | static::ERROR_INVALID_ATTRS,
                BaseResponse::HTTP_BAD_REQUEST
            );
        }
        if ($data['type'] !== $type) {
            throw new Exception(
                '"type" parameter is not valid. Expecting ' . $type,
                static::ERROR_SCOPE | static::ERROR_INVALID_ATTRS,
                BaseResponse::HTTP_CONFLICT
            );
        }
        unset($data['type']);

        return $data;
    }

    /**
     * Function to handle pagination requests.
     *
     * @param  \EchoIt\JsonApi\Request $request
     * @param  \EchoIt\JsonApi\Model $model
     * @param integer $total the total number of records
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    protected function handlePaginationRequest($request, $model, $total = null)
    {
        $page = $request->pageNumber;
        $perPage = $request->pageSize;
        if (!$total) {
            $total = $model->count();
        }
        $results = $model->forPage($page, $perPage)->get(array('*'));
        $paginator = new LengthAwarePaginator($results, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'page[number]'
        ]);
        $paginator->appends('page[size]', $perPage);
        if (!empty($request->filter)) {
            foreach ($request->filter as $key=>$value) {
                $paginator->appends($key, $value);
            }
        }
        if (!empty($request->sort)) {
            $paginator->appends('sort', implode(',', $request->sort));
        }

        return $paginator;
    }

    /**
     * Function to handle filtering requests.
     *
     * @param  array $filters key=>value pairs of column and value to filter on
     * @param  \EchoIt\JsonApi\Model $model
     * @return \EchoIt\JsonApi\Model
     */
    protected function handleFilterRequest($filters, $model)
    {
        foreach ($filters as $key=>$value) {
            $model = $model->where($key, '=', $value);
        }
        return $model;
    }

    /**
     * Default handling of GET request.
     * Must be called explicitly in handleGet function.
     *
     * @param  \EchoIt\JsonApi\Request $request
     * @param  \EchoIt\JsonApi\Model $model
     * @return \EchoIt\JsonApi\Model|\Illuminate\Pagination\LengthAwarePaginator
     * @throws Exception
     */
    protected function handleGetDefault(Request $request, $model)
    {
        $total = null;
        if (empty($request->id)) {
            if (!empty($request->filter)) {
                $model = $this->handleFilterRequest($request->filter, $model);
            }
            if (!empty($request->sort)) {
                //if sorting AND paginating, get total count before sorting!
                if ($request->pageNumber) {
                    $total = $model->count();
                }
                $model = $this->handleSortRequest($request->sort, $model);
            }
        } else {
            $model = $model->where($model->getKeyName(), '=', $request->id);
        }

        try {
            if ($request->pageNumber && empty($request->id)) {
                $results = $this->handlePaginationRequest($request, $model, $total);
            } else {
                $results = $model->get();
            }
        } catch (\Illuminate\Database\QueryException $e) {
            throw new Exception(
                'Database Request Failed',
                static::ERROR_SCOPE | static::ERROR_UNKNOWN_ID,
                BaseResponse::HTTP_INTERNAL_SERVER_ERROR,
                array('details' => $e->getMessage())
            );
        }
        return $results;
    }

    /**
     * Validates passed data against a model
     * Validation performed safely and only if model provides rules
     *
     * @param  \EchoIt\JsonApi\Model $model  model to validate against
     * @param  Array                 $values passed array of values
     *
     * @throws Exception\Validation          Exception thrown when validation fails
     *
     * @return Bool                          true if validation successful
     */
    protected function validateModelData(Model $model, Array $values)
    {
        $validationResponse = $model->validateArray($values);

        if ($validationResponse === true) {
            return true;
        }

        throw new Exception\Validation(
            'Bad Request',
            static::ERROR_SCOPE | static::ERROR_HTTP_METHOD_NOT_ALLOWED,
            BaseResponse::HTTP_BAD_REQUEST,
            $validationResponse
        );
    }

    /**
     * Default handling of POST request.
     * Must be called explicitly in handlePost function.
     *
     * @param  \EchoIt\JsonApi\Request $request
     * @param  \EchoIt\JsonApi\Model $model
     * @return \EchoIt\JsonApi\Model
     * @throws Exception
     */
    public function handlePostDefault(Request $request, $model)
    {
        $values = $this->parseRequestContent($request->content, $model->getResourceType());
        $this->validateModelData($model, $values);

        $model->fill($values);

        if (!$model->save()) {
            throw new Exception(
                'An unknown error occurred',
                static::ERROR_SCOPE | static::ERROR_UNKNOWN,
                BaseResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $model;
    }

    /**
     * Default handling of PUT request.
     * Must be called explicitly in handlePut function.
     *
     * @param  \EchoIt\JsonApi\Request $request
     * @param  \EchoIt\JsonApi\Model   $model
     * @return \EchoIt\JsonApi\Model
     * @throws Exception
     */
    public function handlePutDefault(Request $request, $model)
    {
        if (empty($request->id)) {
            throw new Exception(
                'No ID provided',
                static::ERROR_SCOPE | static::ERROR_NO_ID,
                BaseResponse::HTTP_BAD_REQUEST
            );
        }

        $updates = $this->parseRequestContent($request->content, $model->getResourceType());

        $model = $model::find($request->id);
        if (is_null($model)) {
            return null;
        }

        // fetch the original attributes
        $originalAttributes = $model->getOriginal();

        // apply our updates
        $model->fill($updates);

        // ensure we can get a succesful save
        if (!$model->save()) {
            throw new Exception(
                'An unknown error occurred',
                static::ERROR_SCOPE | static::ERROR_UNKNOWN,
                BaseResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        // fetch the current attributes (post save)
        $newAttributes = $model->getAttributes();

        // loop through the new attributes, and ensure they are identical
        // to the original ones. if not, then we need to return the model
        foreach ($newAttributes as $attribute => $value) {
            if (! array_key_exists($attribute, $originalAttributes) || $value !== $originalAttributes[$attribute]) {
                $model->markChanged();
                break;
            }
        }

        return $model;
    }

    /**
     * Default handling of DELETE request.
     * Must be called explicitly in handleDelete function.
     *
     * @param  \EchoIt\JsonApi\Request $request
     * @param  \EchoIt\JsonApi\Model $model
     * @return \EchoIt\JsonApi\Model
     * @throws Exception
     */
    public function handleDeleteDefault(Request $request, $model)
    {
        if (empty($request->id)) {
            throw new Exception(
                'No ID provided',
                static::ERROR_SCOPE | static::ERROR_NO_ID,
                BaseResponse::HTTP_BAD_REQUEST
            );
        }

        $model = $model::find($request->id);
        if (is_null($model)) {
            return null;
        }

        $model->delete();

        return $model;
    }
}
