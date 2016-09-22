<?php

namespace EchoIt\JsonApi;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Pluralizer;
use Illuminate\Database\Eloquent\Relations\Pivot;
use \Illuminate\Support\Collection;
use EchoIt\JsonApi\CacheManager;
use Carbon\Carbon;
use Cache;
use function Stringy\create as s;

abstract class Model extends \Eloquent {

	static protected $allowsModifyingByAllUsers;

	/**
	 * Validation rules
	 *
	 * @var array
	 */
	protected $rules = array();
	
	/**
	 * @var integer Amount time that response should be cached
	 */
	static protected $cacheTime = 60;
	
	/**
	 * Return the rules used when the model is updating
	 */
	protected abstract function getRulesOnUpdate ();

	/**
	 * Validates user input with the rules defined in the "$rules" static property
	 *
	 * @param array $rules
	 *
	 * @return bool
	 */
	public function validate ($rules = array()) {
		if (empty ($rules)) {
			$rules = $this->rules;
		}
		$validator = Validator::make ($this->attributes, $rules, $this->getValidationMessages ());

		if ($validator->passes ()) {
			return true;
		}

		$this->validationErrors = $validator->messages ();

		return false;
	}
	
	/**
	 * @return bool
	 * Validates user input when updating model
	 */
	public function validateOnUpdate () {
		return $this->validate ($this->getRulesOnUpdate ());
	}

	/**
	 * Validation error messages
	 *
	 * @var object
	 */
	protected $validationErrors;

	/**
	 * Returns validation errors if any
	 *
	 * @return object
	 */
	public function getValidationErrors () {
		return $this->validationErrors;
	}

	/**
	 * Validation messages
	 *
	 * @var array
	 */
	protected $validationMessages = array();

	/**
	 * Function that returns validation messages of this Class and parent Class merged
	 *
	 * @return array
	 */
	public function getValidationMessages () {
		return $this->validationMessages;
	}

	/**
	 * Friendly name of the model
	 *
	 * @var string
	 */
	public static $showName = "";

	/**
	 * Friendly name of the model (plural)
	 *
	 * @var string
	 */
	public static $showNamePlural = "";

	/**
	 * Genre: Male = true
	 *
	 * @var bool
	 */
	public static $genre = true;

	/**
	 *
	 * @return mixed
	 */
	public function getResourceType () {
		// return the resource type if it is not null; class name otherwise
		if ($this->resourceType) {
			return $this->resourceType;
		} else {
			$reflectionClass = new \ReflectionClass($this);

			return s ($reflectionClass->getShortName ())->dasherize ()->__toString ();
		}
	}

	/**
	 * @return array
	 */
	public function toArray () {
		if ($this->isChanged ()) {
			return $this->convertToArray ();
		} else {
			if (empty($this->getRelations ())) {
				$key = CacheManager::getArrayCacheKeyForSingleResourceWithoutRelations($this->getResourceType(), $this->getKey());
			} else {
				$key = CacheManager::getArrayCacheKeyForSingleResource($this->getResourceType(), $this->getKey());
			}
			return Cache::remember (
				$key, static::$cacheTime,
				function () {
					return $this->convertToArray ();
				}
			);
		}
	}

	/**
	 * @return array
	 */
	private function convertToArray () {
		$relations = [];
		$arrayableRelations = [];

		// fetch the relations that can be represented as an array
		$arrayableRelations = array_merge ($this->getArrayableRelations (), $arrayableRelations);

		// add the relations to the linked array
		$relations = $this->relationshipsToArray ($arrayableRelations, $relations);

		//add type parameter
		$model_attributes = $this->attributesToArray ();
		$dasherized_model_attributes = array();

		foreach ($model_attributes as $key => $attribute) {
			$dasherized_model_attributes [$this->dasherizeKey($key)] = $attribute;
		}

		unset($dasherized_model_attributes[$this->primaryKey]);

		$attributes = [
			'id'         => $this->getKey (),
			'type'       => $this->getResourceType (),
			'attributes' => $dasherized_model_attributes,
			'links'      => array(
				'self' => $this->getModelURL ()
			)
		];

		if (!count ($relations)) {
			return $attributes;
		}

		$relationships = ['relationships' => $relations];

		return array_merge ($attributes, $relationships);
	}

	/**
	 * @param $arrayableRelations
	 * @param $relations
	 * @return mixed
	 */
	private function relationshipsToArray ($arrayableRelations, $relations) {
		foreach ($arrayableRelations as $relation => $value) {

			if (in_array ($relation, $this->hidden)) {
				continue;
			}

			if ($value instanceof Pivot) {
				continue;
			}

			if ($value instanceof Model) {
				$resourceType = $value->getResourceType ();
				$relations[s ($resourceType)->dasherize ()->__toString ()] = array(
					'data' => array(
						'id'   => s ($value->getKey ())->dasherize ()->__toString (),
						'type' => Pluralizer::plural ($resourceType)
					)
				);
			} elseif ($value instanceof Collection && $value->count () > 0) {
				$resourceType = $value->get (0)->getResourceType ();
				$relation = Pluralizer::plural (s ($resourceType)->dasherize ()->__toString ());
				$relations[$relation] = array();
				$relations[$relation]['data'] = array();
				$value->each (
					function (Model $item) use (&$relations, $relation, $resourceType) {
						array_push ($relations[$relation]['data'], array(
							'id'   => s ($item->getKey ())->dasherize ()->__toString (),
							'type' => Pluralizer::plural ($resourceType)
						));
					}
				);
			}

			// remove models / collections that we loaded from a method
			if (in_array ($relation, $this->relationsFromMethod)) {
				unset($this->$relation);
			}
		}

		return $relations;
	}

	public function getModelURL () {
		return url (sprintf ('%s/%d', Pluralizer::plural($this->getResourceType ()), $this->id));
	}

	/**
	 * Create handler name from request name. Default output: Path\To\Model\ModelName
	 *
	 * @param string $modelName The name of the model
	 * @param bool $isPlural If is needed to convert this to singular
	 * @param bool $short Should return short name (without namespace)
	 * @param bool $toLowerCase Should return lowered case model name
	 * @param bool $capitalizeFirst
	 *
	 * @return string Class name of related resource
	 */
	public static function getModelClassName ($modelName, $namespace, $isPlural = true, $short = false, $toLowerCase = false,
	                                          $capitalizeFirst = true) {
		if ($isPlural) {
			$modelName = Pluralizer::singular ($modelName);
		}

		$className = "";
		if (!$short) {
			$className .= $namespace . '\\';
		}
		$className .= $toLowerCase ? strtolower ($modelName) : ucfirst ($modelName);
		$className = $capitalizeFirst ? s ($className)->upperCamelize ()->__toString () : s ($className)->camelize ()->__toString ();

		return $className;
	}
	
	public function getCreatedAtAttribute ($date) {
		return $this->getFormattedTimestamp ($date);
	}

	public function getUpdatedAtAttribute ($date) {
		return $this->getFormattedTimestamp ($date);
	}

	private function getFormattedTimestamp ($date) {
		if (is_null($date !== false)) {
			return Carbon::createFromFormat("Y-m-d H:i:s", $date)->format('c');
		}
		return null;
	}
	
	public static function allowsModifyingByAllUsers () {
		return static::$allowsModifyingByAllUsers;
	}


	/**
	 * Let's guard these fields per default
	 *
	 * @var array
	 */
	protected $guarded = ['id', 'created_at', 'updated_at'];
	/**
	 * Has this model been changed inother ways than those
	 * specified by the request
	 *
	 * Ref: http://jsonapi.org/format/#crud-updating-responses-200
	 *
	 * @var  boolean
	 */
	protected $changed = false;
	/**
	 * The resource type. If null, when the model is rendered,
	 * the table name will be used
	 *
	 * @var  null|string
	 */
	protected $resourceType = null;
	/**
	 * Expose the resource relations links by default when viewing a
	 * resource
	 *
	 * @var  array
	 */
	protected $defaultExposedRelations = [];
	protected $exposedRelations = [];
	/**
	 * An array of relation names of relations who
	 * simply return a collection, and not a Relation instance
	 *
	 * @var  array
	 */
	protected $relationsFromMethod = [];

	/**
	 * Get the model's default exposed relations
	 *
	 * @return  Array
	 */
	public function defaultExposedRelations () {
		return $this->defaultExposedRelations;
	}

	/**
	 * Get the model's exposed relations
	 *
	 * @return  Array
	 */
	public function exposedRelations () {
		return $this->exposedRelations;
	}

	/**
	 * Set this model's exposed relations
	 *
	 * @param  Array $relations
	 */
	public function setExposedRelations (Array $relations) {
		$this->exposedRelations = $relations;
	}

	/**
	 * Get the model's relations that are from methods
	 *
	 * @return  Array
	 */
	public function relationsFromMethod () {
		return $this->relationsFromMethod;
	}

	/**
	 * mark this model as changed
	 *
	 * @param   bool $changed
	 * @return  void
	 */
	public function markChanged ($changed = true) {
		$this->changed = (bool) $changed;
	}

	/**
	 * has this model been changed
	 *
	 * @return  bool
	 */
	public function isChanged () {
		return $this->changed;
	}

	/**
	 * Validate passed values
	 *
	 * @param  Array $values user passed values (request data)
	 *
	 * @return bool|\Illuminate\Support\MessageBag  True on pass, MessageBag of errors on fail
	 */
	public function validateArray (Array $values) {
		if (count ($this->getValidationRules ())) {
			$validator = Validator::make ($values, $this->getValidationRules ());
			if ($validator->fails ()) {
				return $validator->errors ();
			}
		}
		return True;
	}

	/**
	 * Return model validation rules
	 * Models should overload this to provide their validation rules
	 *
	 * @return Array validation rules
	 */
	public function getValidationRules () {
		return [];
	}
	
	/**
	 * @param $key
	 *
	 * @return string
	 */
	protected function dasherizeKey ($key) {
		return s($key)->dasherize()->__toString();
	}
	
	/**
	 * @return string
	 */
	public function getPrimaryKey () {
		return $this->primaryKey;
	}

}
