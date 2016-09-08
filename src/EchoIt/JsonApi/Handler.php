<?php

	namespace EchoIt\JsonApi;
	
	use EchoIt\JsonApi\Exception;
	use Illuminate\Database\Eloquent\Relations\BelongsTo;
	use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
	use Illuminate\Database\Eloquent\Relations\MorphMany;
	use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
	use Illuminate\Database\Eloquent\Relations\Relation;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Support\Collection;
	use Illuminate\Http\Response as BaseResponse;
	use Illuminate\Support\Pluralizer;
	use Illuminate\Pagination\LengthAwarePaginator;
	use function Stringy\create as s;
	use Cache;

	abstract class Handler {

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
		
		const HANDLER_WORD_LENGTH = 7;
		const ERROR_UNAUTHORIZED = 256;
	
		protected static $namespace;
		protected static $exposedRelations;
		
		/**
		 * @var Model Class name used by this handler including namespace
		 */
		protected $fullModelName;

		/**
		 * @var integer Amount time that response should be cached
		 */
		static protected $cacheTime = 60;

		/**
		 * @var Model Resource name in lowercase
		 */
		protected $shortModelName;

		/**
		 * @var string Resource name handler
		 */
		protected $resourceName;
		
		protected $modelsNamespace;
		
		/**
		 * BaseHandler constructor. Defines modelName based of HandlerName
		 *
		 * @param Request $request
		 * @param         $modelsNamespace
		 */
		public function __construct (Request $request, $modelsNamespace) {
			$this->request = $request;
			$this->modelsNamespace = $modelsNamespace;
			$this->setResourceName ();
			$this->generateModelName ();
		}

		/**
		 * Generates model names from handler name class
		 */
		private function generateModelName () {
			$shortName = $this->resourceName;
			$this->shortModelName = Model::getModelClassName ($shortName, $this->modelsNamespace, true, true);
			$this->fullModelName = Model::getModelClassName ($shortName, $this->modelsNamespace, true);
		}

		/**
		 * Fulfill the API request and return a response.
		 *
		 * @return \EchoIT\JsonApi\Response
		 * @throws Exception
		 */
		public function fulfillRequest () {
			$request = $this->request;
			$httpMethod = $request->method;

			if (!$this->supportsMethod ($httpMethod)) {
				throw new Exception(
					'Method not allowed',
					static::ERROR_SCOPE | static::ERROR_HTTP_METHOD_NOT_ALLOWED,
					BaseResponse::HTTP_METHOD_NOT_ALLOWED
				);
			}

			/*
			 * Validates if this resource could be updated/deleted/created by all users.
			 */
			if ($httpMethod !== 'GET' && $this->allowsModifyingByAllUsers () === false) {
				throw new Exception(
					'This user cannot modify this resource', static::ERROR_SCOPE | static::ERROR_UNKNOWN | static::ERROR_UNAUTHORIZED,
					BaseResponse::HTTP_FORBIDDEN);
			}

			$models = $this->getModel ($request);

			if (is_null ($models)) {
				throw new Exception(
					'Unknown ID',
					static::ERROR_SCOPE | static::ERROR_UNKNOWN_ID,
					BaseResponse::HTTP_NOT_FOUND
				);
			}

			if ($httpMethod === 'GET') {
				return $this->fulfillCacheableRequest ($models, $request);
			}
			else {
				return $this->fulfillNonCacheableRequest ($models);
			}
		}
		
		/**
		 * Fullfills GET requests
		 *
		 * @param $models
		 * @param $request
		 *
		 * @return mixed
		 */
		private function fulfillCacheableRequest ($models, $request) {
			$id = $request->id;
			if (empty($id)) {
				$key = $this->getResponseCacheForMultipleResources ();
			}
			else {
				$key = $this->getResponseCacheForSingleResource ($id);
			}

			return Cache::remember (
				$key, static::$cacheTime,
				function () use ($models) {
					return $this->generateResponse($models, false);
				}
			);
		}
		
		/**
		 * Fullfills POST, PATCH and DELETE requests
		 *
		 * @param \Illuminate\Http\Request $models
		 *
		 * @return \EchoIt\JsonApi\Response
		 * @internal param $request
		 *
		 */
		private function fulfillNonCacheableRequest ($models) {
			return $this->generateResponse($models);
		}

		/**
		 * @return boolean
		 */
		protected function allowsModifyingByAllUsers () {
			$modelName = $this->fullModelName;
			return $modelName::allowsModifyingByAllUsers ();
		}
		
		/**
		 * Returns handler class name with namespace
		 *
		 * @param      $handlerShortName string The name of the model (in plural)
		 *
		 * @param bool $isPlural
		 * @param bool $short
		 *
		 * @return string Class name of related resource
		 */
		public static function getHandlerFullClassName ($handlerShortName, $isPlural = true, $short = false) {
			$handlerShortName = s ($handlerShortName)->camelize()->__toString();
			
			if ($isPlural) {
				$handlerShortName = Pluralizer::singular ($handlerShortName);
			}
			
			return (!$short ? static::$namespace . '\\' : "") . ucfirst ($handlerShortName) . 'Handler';
		}
		
		/**
		 * Returns handler short class name
		 *
		 * @return string
		 */
		private static function getHandlerShortClassName () {
			$class = explode ('\\', get_called_class ());
			
			return array_pop ($class);
		}

		/**
		 * @param $models
		 * @param $loadRelations
		 * @return JsonResponse
		 */
		private function generateResponse ($models, $loadRelations = true) {
			if ($models instanceof Response) {
				$response = $models;
			}
			elseif ($models instanceof LengthAwarePaginator) {
				$items = new Collection($models->items ());
				foreach ($items as $model) {
					if ($loadRelations) {
						$this->loadRelatedModels ($model);
					}
				}

				$response = new Response($items, static::successfulHttpStatusCode ($this->request->method));

				$response->links = $this->getPaginationLinks ($models);
				$response->included = $this->getIncludedModels ($items);
				$response->errors = $this->getNonBreakingErrors ();
			}
			else {
				if ($models instanceof Collection) {
					foreach ($models as $model) {
						if ($loadRelations) {
							$this->loadRelatedModels ($model);
						}
					}
				}
				else {
					if ($loadRelations) {
						$this->loadRelatedModels ($models);
					}
				}

				$response = new Response($models, static::successfulHttpStatusCode ($this->request->method, $models));

				$response->included = $this->getIncludedModels ($models);
				$response->errors = $this->getNonBreakingErrors ();
			}

			return $response->toJsonResponse();
		}

		/**
		 * @return mixed
		 */
		private function getModel (Request $request) {
			$methodName = static::methodHandlerName ($request->method);
			$models = $this->{$methodName}($request);

			return $models;
		}
		
		/**
		 * Generates resource name from class name (ResourceHandler -> resource)
		 */
		private function setResourceName () {
			$shortClassName = self::getHandlerShortClassName ();
			$resourceNameLength = $shortClassName - self::HANDLER_WORD_LENGTH;
			$this->resourceName = substr ($shortClassName, 0, $resourceNameLength);
		}

		/**
		 * @param Request $request
		 *
		 * @return mixed
		 */
		public function handleGet (Request $request) {
			$id = $request->id;
			if (empty($id)) {
				$models = $this->handleGetAll ($request);

				return $models;
			}

			$modelName = $this->fullModelName;
			$key = $this->getQueryCacheForSingleResource ($id);
			$model = Cache::remember (
				$key, static::$cacheTime,
				function () use ($modelName, $request) {
					$model = $modelName::find ($request->id);
					if ($model) {
						$this->loadRelatedModels ($model);
					}
					return $model;
				}
			);

			return $model;
		}

		/**
		 * @param Request $request
		 *
		 * @return \Illuminate\Database\Eloquent\Collection
		 */
		protected function handleGetAll (Request $request) {
			$key = $this->getQueryCacheForMultipleResources ();
			$modelName = $this->fullModelName;
			$models = Cache::remember (
				$key, static::$cacheTime,
				function () use ($modelName) {
					if (count (static::$exposedRelations) > 0) {
						return forward_static_call_array (array ($modelName, 'with'), static::$exposedRelations)->get ();
					}
					else {
						return $modelName::all ();
					}
				}
			);

			return $models;
		}

		/**
		 * Handle POST requests
		 *
		 * @param Request $request
		 *
		 * @return Model
		 * @throws Exception
		 * @throws Exception\Validation
		 */
		public function handlePost (Request $request) {
			$modelName = $this->fullModelName;
			$data = $this->parseRequestContent ($request->content);
			$this->normalizeAttributes ($data ["attributes"]);
			
			$attributes = $data ["attributes"];
			
			/** @var Model $model */
			$model = new $modelName ($attributes);
			
			//Update relationships twice, first to update belongsTo and then to update polymorphic and others
			$this->updateRelationships ($data, $model, true);
			$this->validateModelData ($model, $attributes);

			if (!$model->save ()) {
				throw new Exception(
					'An unknown error occurred', static::ERROR_SCOPE | static::ERROR_UNKNOWN,
					BaseResponse::HTTP_INTERNAL_SERVER_ERROR);
			}

			$this->updateRelationships ($data, $model, true);
			$model->markChanged ();
			$this->clearCache();

			return $model;
		}
		
		/**
		 * Handle PATCH requests
		 *
		 * @param \EchoIt\JsonApi\Request $request
		 *
		 * @return \EchoIt\JsonApi\Model|null
		 * @throws \EchoIt\JsonApi\Exception
		 */
		public function handlePatch (Request $request) {
			$data = $this->parseRequestContent ($request->content, false);
			$id = $data["id"];

			$modelName = $this->fullModelName;
			/** @var Model $model */
			$model = $modelName::find ($id);
			
			if (is_null ($model)) {
				return null;
			}
			
			$this->verifyUserPermission($request, $model);
			
			$originalAttributes = $model->getOriginal ();

			if (array_key_exists ("attributes", $data)) {
				$this->normalizeAttributes ($data ["attributes"]);
				$attributes = $data ["attributes"];
				
				$model->fill ($attributes);
				$this->validateModelData ($model, $attributes);
			}

			$this->updateRelationships ($data, $model, false);

			// ensure we can get a successful save
			if (!$model->save ()) {
				throw new Exception(
					'An unknown error occurred', static::ERROR_SCOPE | static::ERROR_UNKNOWN,
					BaseResponse::HTTP_INTERNAL_SERVER_ERROR);
			}

			$this->verifyIfModelChanged ($model, $originalAttributes);

			if ($model->isChanged()) {
				$this->clearCache ($id, $model);
			}
			return $model;
		}

		public function handlePut (Request $request) {
			return $this->handlePatch ($request);
		}

		/**
		 * Handle DELETE requests
		 *
		 * @param  \EchoIt\JsonApi\Request $request
		 *
		 * @return \EchoIt\JsonApi\Model
		 * @throws \EchoIt\JsonApi\Exception
		 */
		public function handleDelete (Request $request) {
			if (empty($request->id)) {
				throw new Exception(
					'No ID provided', static::ERROR_SCOPE | static::ERROR_NO_ID, BaseResponse::HTTP_BAD_REQUEST);
			}
			
			$modelName = $this->fullModelName;
			
			/** @var Model $model */
			$model = $modelName::find ($request->id);

			$this->verifyUserPermission($request, $model);
			
			if (is_null ($model)) {
				return null;
			}
			
			$model->delete ();
			
			return $model;
		}

		/**
		 * @param array $attributes
		 * @return array
		 */
		private function normalizeAttributes (array &$attributes) {
			foreach ($attributes as $key => $value) {
				if (is_string ($key)) {
					unset ($attributes[$key]);
					$attributes[ s( $key )->underscored()->__toString() ] = $value;
				}
			}
		}

		/**
		 * Iterate through result set to fetch the requested resources to include.
		 *
		 * @param Model $models
		 * @return array
		 */
		protected function getIncludedModels ($models) {
			$links = new Collection();
			$models = $models instanceof Collection ? $models : [$models];
			
			foreach ($models as $model) {
				foreach ($this->exposedRelationsFromRequest ($model) as $relationName) {
					$value = static::getModelsForRelation ($model, $relationName);
					
					if (is_null ($value)) {
						continue;
					}

					//Each one of the models relations
					/* @var Model $obj*/
					foreach ($value as $obj) {
						// Check whether the object is already included in the response on it's ID
						$duplicate = false;
						$items = $links->where ($obj->getPrimaryKey (), $obj->getKey ());
						
						if (count ($items) > 0) {
							foreach ($items as $item) {
								/** @var $item Model */
								if ($item->getResourceType () === $obj->getResourceType ()) {
									$duplicate = true;
									break;
								}
							}
							if ($duplicate) {
								continue;
							}
						}
						
						//add type property
						$attributes = $obj->getAttributes ();
						
						$obj->setRawAttributes ($attributes);
						
						$links->push ($obj);
					}
				}
			}
			
			return $links->toArray ();
		}
		
		/**
		 * Parses content from request into an array of values.
		 *
		 * @param  string $content
		 * @param bool    $newRecord
		 *
		 * @return array
		 * @throws \EchoIt\JsonApi\Exception
		 * @internal param string $type the type the content is expected to be.
		 */
		protected function parseRequestContent ($content, $newRecord = true) {
			$content = json_decode ($content, true);

			$data = $content['data'];

			if (empty($data)) {
				throw new Exception(
					'Payload either contains misformed JSON or missing "data" parameter.',
					static::ERROR_SCOPE | static::ERROR_INVALID_ATTRS, BaseResponse::HTTP_BAD_REQUEST);
			}
			if (array_key_exists ("type", $data) === false) {
				throw new Exception(
					'"type" parameter not set in request.', static::ERROR_SCOPE | static::ERROR_INVALID_ATTRS,
					BaseResponse::HTTP_BAD_REQUEST);
			}
			if ($data['type'] !== $type = Pluralizer::plural (s ($this->resourceName)->dasherize ()->__toString ())) {
				throw new Exception(
					'"type" parameter is not valid. Expecting ' . $type,
					static::ERROR_SCOPE | static::ERROR_INVALID_ATTRS, BaseResponse::HTTP_CONFLICT);
			}
			if ($newRecord === false && !isset($data['id'])) {
				throw new Exception(
					'"id" parameter not set in request.', static::ERROR_SCOPE | static::ERROR_INVALID_ATTRS,
					BaseResponse::HTTP_BAD_REQUEST);
			}
			
			unset ($content ['type']);
			
			return $data;
		}
		
		/**
		 * Associate models relationships
		 *
		 * @param               $data
		 * @param Model $model
		 *
		 * @throws Exception
		 */
		protected function updateRelationships ($data, Model $model, $creating = false) {
			if (array_key_exists ("relationships", $data)) {
				//If we have a relationship object in the payload
				$relationships = $data ["relationships"];
				
				//Iterate all the relationships object
				foreach ($relationships as $relationshipName => $relationship) {
					if (is_array ($relationship)) {
						//If the relationship object is an array
						if (array_key_exists ('data', $relationship)) {
							//If the relationship has a data object
							$relationshipData = $relationship ['data'];
							if (is_array ($relationshipData)) {
								//One to one
								if (array_key_exists ('type', $relationshipData)) {
									$this->updateSingleRelationship ($model, $relationshipData, $relationshipName, $creating);
								}
								//One to many
								else if (count(array_filter(array_keys($relationshipData), 'is_string')) == 0) {
									$relationshipDataItems = $relationshipData;
									foreach ($relationshipDataItems as $relationshipDataItem) {
										if (array_key_exists ('type', $relationshipDataItem)) {
											$this->updateSingleRelationship ($model, $relationshipDataItem, $relationshipName, $creating);
										}
										else {
											throw new Exception(
												'Relationship type key not present in the request for an item',
												static::ERROR_SCOPE | static::ERROR_INVALID_ATTRS,
												BaseResponse::HTTP_BAD_REQUEST);
										}
									}
								}
								else {
									throw new Exception(
										'Relationship type key not present in the request',
										static::ERROR_SCOPE | static::ERROR_INVALID_ATTRS,
										BaseResponse::HTTP_BAD_REQUEST);
								}
							}
							else if (is_null ($relationshipData)) {
								//If the data object is null, do nothing, nothing to associate
							}
							else {
								//If the data object is not array or null (invalid)
								throw new Exception(
									'Relationship "data" object must be an array or null',
									static::ERROR_SCOPE | static::ERROR_INVALID_ATTRS, BaseResponse::HTTP_BAD_REQUEST);
							}
						}
						else {
							throw new Exception(
								'Relationship must have an object with "data" key',
								static::ERROR_SCOPE | static::ERROR_INVALID_ATTRS, BaseResponse::HTTP_BAD_REQUEST);
						}
					}
					else {
						//If the relationship is not an array, return error
						throw new Exception(
							'Relationship object is not an array', static::ERROR_SCOPE | static::ERROR_INVALID_ATTRS,
							BaseResponse::HTTP_BAD_REQUEST);
					}
				}
			}
		}
		
		/**
		 * @param Model $model
		 * @param               $originalAttributes
		 *
		 * @return Model
		 *
		 */
		public function verifyIfModelChanged (Model $model, $originalAttributes) {
			// fetch the current attributes (post save)
			$newAttributes = $model->getAttributes ();
			
			// loop through the new attributes, and ensure they are identical
			// to the original ones. if not, then we need to return the model
			foreach ($newAttributes as $attribute => $value) {
				if (!array_key_exists ($attribute, $originalAttributes) ||
					$value !== $originalAttributes[$attribute]
				) {
					$model->markChanged ();
					break;
				}
			}
		}

		/**
		 * @param null $id
		 * @param Model $model
		 */
		private function clearCache ($id = null, $model = null) {
			//ID passed = update record
			if ($id !== null && $model !== null) {
				$key = $this->getQueryCacheForSingleResource ($id);
				Cache::forget($key);
				$key = $this->getResponseCacheForSingleResource ($id);
				Cache::forget($key);
				$key = $model->getArrayCacheKeyForSingleResource ();
				Cache::forget($key);
				$key = $model->getArrayCacheKeyForSingleResourceWithoutRelations ();
				Cache::forget($key);
			}
			$key = $this->getQueryCacheForMultipleResources ();
			Cache::forget($key);
			$key = $this->getResponseCacheForMultipleResources ();
			Cache::forget($key);
		}

		/**
		 * @return string
		 */
		private function dasherizedResourceName () {
			return s ($this->resourceName)->dasherize ()->__toString ();
		}

		/**
		 * @return string
		 */
		private function getQueryCacheForMultipleResources () {
			return Pluralizer::plural ($this->dasherizedResourceName ()) . ":query";
		}

		/**
		 * @param $id
		 * @return string
		 */
		private function getQueryCacheForSingleResource ($id) {
			return $this->dasherizedResourceName () . ":query:" . $id;
		}

		/**
		 * @return string
		 */
		private function getResponseCacheForMultipleResources () {
			return Pluralizer::plural ($this->dasherizedResourceName ()) . ":response";
		}

		/**
		 * @param $id
		 * @return string
		 */
		private function getResponseCacheForSingleResource ($id) {
			return $this->dasherizedResourceName () . ":response:" . $id;
		}

		/**
		 * @param Request $request
		 * @param         $model
		 *
		 * @throws Exception
		 */
		abstract protected function verifyUserPermission( Request $request, $model );

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
				/** @var $model Model */
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
					/** @var $model Model */
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
				$dir = 'asc';

				if (substr($col, 0, 1) == '-') {
					$dir = 'desc';
					$col = substr($col, 1);
				}

				$model = $model->orderBy($col, $dir);
			}
			return $model;
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
					array('detail' => $e->getMessage())
				);
			}
			return $results;
		}

		/**
		 * Validates passed data against a model
		 * Validation performed safely and only if model provides rules
		 *
		 * @param  \EchoIt\JsonApi\Model $model  model to validate against
		 * @param  array                 $values passed array of values
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
			$values = $this->parseRequestContent ($request->content);
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

			$updates = $this->parseRequestContent ($request->content);

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
		
		/**
		 * @param \EchoIt\JsonApi\Model $model
		 * @param                       $relationshipData
		 *
		 * @param                       $relationshipName
		 *
		 * @throws \EchoIt\JsonApi\Exception
		 */
		protected function updateSingleRelationship (Model $model, $relationshipData, $relationshipName, $creating) {
			//If we have a type of the relationship data
			$type                  = $relationshipData['type'];
			$relationshipModelName = Model::getModelClassName ($type, $this->modelsNamespace);
			$relationshipName      = s ($relationshipName)->camelize ()->__toString ();
			//If we have an id of the relationship data
			if (array_key_exists ('id', $relationshipData)) {
				/** @var $relationshipModelName Model */
				$relationshipId       = $relationshipData['id'];
				$newRelationshipModel = $relationshipModelName::find ($relationshipId);
				
				if ($newRelationshipModel) {
					//Relationship exists in model
					if (method_exists ($model, $relationshipName)) {
						/** @var Relation $relationship */
						$relationship = $model->$relationshipName ();
						//If creating, only update belongs to before saving. If not creating (updating), update
						if ($relationship instanceof BelongsTo && (($creating && $model->isDirty()) || !$creating)) {
							$relationship->associate ($newRelationshipModel);
						}
						//If creating, only update polymorphic saving. If not creating (updating), update
						else if ($relationship instanceof MorphOneOrMany && (($creating && !$model->isDirty()) || !$creating)) {
							$relationship->save ($newRelationshipModel);
							
						}
					}
					else {
						throw new Exception(
							"Relationship $relationshipName is not invalid",
							static::ERROR_SCOPE | static::ERROR_INVALID_ATTRS,
							BaseResponse::HTTP_BAD_REQUEST);
					}
				}
				else {
					$formattedType = s(Pluralizer::singular($type))->underscored()->humanize()->toLowerCase()->__toString();
					throw new Exception(
						"Model $formattedType with id $relationshipId not found in database",
						static::ERROR_SCOPE | static::ERROR_INVALID_ATTRS,
						BaseResponse::HTTP_BAD_REQUEST);
				}
			}
			else {
				throw new Exception(
					'Relationship id key not present in the request',
					static::ERROR_SCOPE | static::ERROR_INVALID_ATTRS,
					BaseResponse::HTTP_BAD_REQUEST);
			}
		}
	}
