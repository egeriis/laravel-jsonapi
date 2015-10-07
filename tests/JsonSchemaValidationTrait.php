<?php namespace EchoIt\JsonApi\Tests;

use JsonSchema\RefResolver;
use JsonSchema\Validator;

trait JsonSchemaValidationTrait
{
    /**
     * @param \stdClass $data JSON data decoded into object notation
     * @throws \Exception
     */
    public function assertJsonapiValid($data)
    {
        $schemaFile = dirname(__FILE__) . '/../resources/jsonapi-schema-1.0.json';
        $schema = json_decode(file_get_contents($schemaFile));

        $refResolver = new RefResolver();
        # Minimum depth required to work with jsonapi schema
        $refResolver::$maxDepth = 11;
        $refResolver->resolve($schema);

        $validator = new Validator();
        $validator->check($data, $schema);

        if (!$validator->isValid()) {
            $msg = "Invalid jsonapi reponse:\n";
            foreach ($validator->getErrors() as $error) {
                $msg .= '   path "' . $error['property'] . '" -> ' . $error['message'] . "\n";
            }
            throw new \Exception($msg);
        }
    }
}
