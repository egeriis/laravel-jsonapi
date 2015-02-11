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
     * Convert the model instance to an array. This method overrides that of
     * Eloquent to prevent relations to be serialize into output array.
     *
     * @return array
     */
    public function toArray()
    {
        $relations = [];
        foreach ($this->getArrayableRelations() as $relation => $value) {
            if (in_array($relation, $this->hidden)) continue;

            if ($value instanceof BaseModel) {
                $relations[$relation] = $value->getKey ();
            } else if ($value instanceof Collection) {
                $relation = \str_plural($relation);
                $relations[$relation] = array_pluck($value, $value->first()->primaryKey);
            }
        }

        if ( ! count($relations)) {
            return $this->attributesToArray();
        }

        return array_merge(
            $this->attributesToArray(),
            [ 'links' => $relations ]
        );
    }
}
