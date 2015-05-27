<?php namespace EchoIt\JsonApi\Exception;

use EchoIt\JsonApi\Exception;
use EchoIt\JsonApi\MultiErrorResponse;
use Illuminate\Support\MessageBag as ValidationMessages;

/**
 * Validation represents an Exception that can be thrown in the event of a validation failure where a JSON response may be expected.
 *
 * @author Matt <matt@ninjapenguin.co.uk>
 */
class Validation extends Exception
{
    protected $httpStatusCode;
    protected $validationMessages;

    /**
     * Constructor.
     *
     * @param string                        $message        The Exception message to throw
     * @param int                           $code           The Exception code
     * @param int                           $httpStatusCode HTTP status code which can be used for broken request
     * @param Illuminate\Support\MessageBag $errors         Validation errors
     */
    public function __construct($message = '', $code = 0, $httpStatusCode = 500, ValidationMessages $messages = NULL)
    {
        parent::__construct($message, $code);

        $this->httpStatusCode = $httpStatusCode;
        $this->validationMessages = $messages;
    }

    /**
     * This method returns a HTTP response representation of the Exception
     *
     * @return JsonApi\ErrorResponse
     */
    public function response()
    {
        return new MultiErrorResponse($this->httpStatusCode, $this->code, $this->message, $this->validationMessages);
    }
}
