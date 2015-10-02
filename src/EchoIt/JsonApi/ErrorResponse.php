<?php namespace EchoIt\JsonApi;

use Illuminate\Http\JsonResponse;

/**
 * ErrorResponse represents a HTTP error response with a JSON API compliant payload.
 *
 * @author Ronni Egeriis Persson <ronni@egeriis.me>
 */
class ErrorResponse extends JsonResponse
{
    /**
     * Constructor.
     *
     * @param int    $httpStatusCode HTTP status code
     * @param mixed  $errorCode      Internal error code
     * @param string $errorTitle     Error description
     */
    public function __construct($httpStatusCode, $errorCode, $errorTitle, array $additionalAttrs = array())
    {
        $data = [
            'errors' => [ array_merge(
                [
                    'status' => (string) $httpStatusCode,
                    'code'   => (string) $errorCode,
                    'title'  => (string) $errorTitle
                ],
                $additionalAttrs
            ) ]
        ];
        parent::__construct($data, $httpStatusCode);
    }
}
