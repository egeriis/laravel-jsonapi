<?php namespace EchoIt\JsonApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\MessageBag as ValidationMessages;

/**
 * MultiErrorResponse represents a HTTP error response containing multiple errors with a JSON API compliant payload.
 *
 * @author Matt <matt@ninjapenguin.co.uk>
 */
class MultiErrorResponse extends JsonResponse
{
    /**
     * Constructor.
     *
     * @param int                            $httpStatusCode   HTTP status code
     * @param mixed                          $errorCode        Internal error code
     * @param string                         $errorTitle       Error description
     * @param \Illuminate\Support\MessageBag $errors           Validation errors
     */
    public function __construct($httpStatusCode, $errorCode, $errorTitle, ValidationMessages $errors = NULL)
    {
        $data = [ 'errors' => [] ];

        if ($errors) {
            foreach ($errors->keys() as $field) {

                foreach ($errors->get($field) as $message) {

                    $data['errors'][] = [
                        'status' => (string) $httpStatusCode,
                        'code'   => (string) $errorCode,
                        'title'  => 'Validation Fail',
                        'detail' => (string) $message,
                        'meta' => [
                          'field' => $field
                        ]
                    ];

                }

            }
        }

        parent::__construct($data, $httpStatusCode);
    }
}
