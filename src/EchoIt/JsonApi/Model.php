<?php namespace EchoIt\JsonApi;

use Illuminate\Database\Eloquent\Collection;

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
        $relations = array_map(function($models) {
            return array_pluck($models, 'id');
        }, $this->relationsToArray());

        return array_merge($this->attributesToArray(), $relations);
    }
}
