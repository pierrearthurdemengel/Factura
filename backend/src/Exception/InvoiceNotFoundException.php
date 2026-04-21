<?php

namespace App\Exception;

/**
 * Thrown when a required invoice or its mandatory relations (seller, buyer) are not found.
 */
class InvoiceNotFoundException extends \RuntimeException
{
}
