<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception domain untuk API internal — dirender sebagai
 * {"error": "kode_snake_case", "message": "..."} sesuai format
 * error standar kontrak v1.
 */
class ApiException extends Exception
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }
}
