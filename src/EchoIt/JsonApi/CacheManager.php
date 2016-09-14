<?php
	/**
	 * Class CacheManager
	 *
	 * @package EchoIt\JsonApi
	 * @author  Julian Acosta <iam@julianacosta.me>
	 */
	
	namespace EchoIt\JsonApi;
	
	use Illuminate\Support\Pluralizer;
	
	class CacheManager {
		
		/**
		 * @param $resourceName
		 *
		 * @return string
		 */
		public static function getQueryCacheForMultipleResources($resourceName) {
			return Pluralizer::plural($resourceName) . ":query";
		}
		
		/**
		 * @param $id
		 * @param $resourceName
		 *
		 * @return string
		 */
		public static function getQueryCacheForSingleResource($id, $resourceName) {
			return $resourceName . ":query:" . $id;
		}
		
		/**
		 * @param $resourceName
		 *
		 * @return string
		 */
		public static function getResponseCacheForMultipleResources($resourceName) {
			return Pluralizer::plural($resourceName) . ":response";
		}
		
		/**
		 * @param $id
		 * @param $resourceName
		 *
		 * @return string
		 */
		public static function getResponseCacheForSingleResource($id, $resourceName) {
			return $resourceName . ":response:" . $id;
		}
		
		/**
		 * @param $resourceType
		 * @param $key
		 *
		 * @return string
		 */
		public static function getArrayCacheKeyForSingleResource($resourceType, $key) {
			return $resourceType . ":array:" . $key . ":relations";
		}
		
		public static function getArrayCacheKeyForSingleResourceWithoutRelations($resourceType, $key) {
			return $resourceType . ":array:" . $key . ":no_relations";
		}
	}