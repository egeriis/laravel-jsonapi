<?php namespace EchoIt\JsonApi;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model as BaseModel;

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
     * Convert the model instance to an array. This method overrides that of
     * Eloquent to prevent relations to be serialize into output array.
     *
     * @return array
     */
    public function toArray()
    {
        $relations = [];
        foreach ($this->getArrayableRelations() as $relation => $value) {
            if (in_array($relation, $this->hidden)) {
                continue;
            }

            if ($value instanceof BaseModel) {
                $relations[$relation] = array('id' => $value->getKey(), 'type' => $value->getResourceType());
            } elseif ($value instanceof Collection) {
                $relation = \str_plural($relation);
                $items = [];
                foreach ($value as $item) {
                    $items[] = array('id' => $item->getKey(), 'type' => $item->getResourceType());
                }
                $relations[$relation] = $items;
            }
        }

        //add type parameter
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
