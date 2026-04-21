<?php

namespace App\Exception;

/**
 * Thrown when an external service (Chorus Pro, banking API, S3, etc.) fails.
 */
class ExternalServiceException extends \RuntimeException
{
}
