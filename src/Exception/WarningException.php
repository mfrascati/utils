<?php
namespace Entheos\Utils\Exception;

use Cake\Http\Exception\HttpException;

/**
 * Da usare per errori da mostrare all'utente ma che non devono essere loggati 
 * Inserire nelle eccezioni sul logger in app.php
 */
class WarningException extends HttpException
{

    /**
     * Constructor
     *
     * @param string|null $message If no message is given 'Internal Server Error' will be the message
     * @param int $code Status code, defaults to 500
     * @param \Exception|null $previous The previous exception.
     */
    public function __construct($message = null, $code = null, $previous = null)
    {
        if (empty($message)) {
            $message = 'Internal Server Error';
        }
        parent::__construct($message, $code, $previous);
    }
}
