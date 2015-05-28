<?php namespace EchoIt\JsonApi;

use Validator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model as BaseModel;
use Illuminate\Database\Eloquent\Relations\Pivot as Pivot;

/**
 * This class is used to extend models from, that will be exposed through
 * a JSON API.
 *
 * @author Ronni Egeriis Persson <ronni@egeriis.me>
 */
class Model extends \Eloquent
{
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
    public function defaultExposedRelations() {
        return $this->defaultExposedRelations;
    }

    /**
     * Get the model's exposed relations
     *
     * @return  Array
     */
    public function exposedRelations() {
        return $this->exposedRelations;
    }

    /**
     * Set this model's exposed relations
     *
     * @param  Array  $relations
     */
    public function setExposedRelations(Array $relations) {
        $this->exposedRelations = $relations;
    }

    /**
     * Get the model's relations that are from methods
     *
     * @return  Array
     */
    public function relationsFromMethod() {
        return $this->relationsFromMethod;
    }

    /**
     * mark this model as changed
     *
     * @return  void
     */
    public function markChanged($changed = true)
    {
        $this->changed = (bool) $changed;
    }

    /**
     * has this model been changed
     *
     * @return  void
     */
    public function isChanged()
    {
        return $this->changed;
    }

    /**
     * Get the resource type of the model
     *
     * @return  string
     */
    public function getResourceType()
    {
        // return the resource type if it is not null; table otherwize
        return ($this->resourceType ?: $this->getTable());
    }

    /**
     * Validate passed values
     *
     * @param  Array  $values  user passed values (request data)
     *
     * @return bool|Illuminate\Support\MessageBag  True on pass, MessageBag of errors on fail
     */
    public function validateArray(Array $values)
    {
        if (count($this->getValidationRules())) {
            $validator = Validator::make($values, $this->getValidationRules());

            if ($validator->fails()) {
                return $validator->errors();
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
    public function getValidationRules()
    {
        return [];
    }

    /**
     * Convert the model instance to an array. This method overrides that of
     * Eloquent to prevent relations to be serialize into output array.
     *
     * @return array
     */
    public function toArray()
    {
        $relations = [];
        $arrayableRelations = [];

        // include any relations exposed by default
       foreach ($this->exposedRelations as $relation) {
            // skip loading a relation if it is from a method
            if (in_array($relation, $this->relationsFromMethod)) {
                // if the relation hasnt been loaded, then load it
                if (!isset($this->$relation)) {
                    $this->$relation = $this->$relation();
                }

                $arrayableRelations[$relation] = $this->$relation;
                continue;
            }

            $this->load($relation);
        }

        // fetch the relations that can be represented as an array
        $arrayableRelations = array_merge($this->getArrayableRelations(), $arrayableRelations);

        // add the relations to the linked array
        foreach ($arrayableRelations as $relation => $value) {
            if (in_array($relation, $this->hidden)) {
                continue;
            }

            if ($value instanceof Pivot) {
                continue;
            }

            if ($value instanceof BaseModel) {
                $relations[$relation] = array('linkage' => array('id' => $value->getKey(), 'type' => $value->getResourceType()));
            } elseif ($value instanceof Collection) {
                $relation = \str_plural($relation);
                $items = ['linkage' => []];
                foreach ($value as $item) {
                    $items['linkage'][] = array('id' => $item->getKey(), 'type' => $item->getResourceType());
                }
                $relations[$relation] = $items;
            }

            // remove models / collections that we loaded from a method
            if (in_array($relation, $this->relationsFromMethod)) {
                unset($this->$relation);
            }
        }

        // add type parameter
        $attributes = $this->attributesToArray();
        $attributes['type'] = $this->getResourceType();

        if (! count($relations)) {
            return $attributes;
        }

        return array_merge(
            $attributes,
            [ 'links' => $relations ]
        );
    }
}
