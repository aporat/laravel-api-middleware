<?php

namespace Aporat\Laravel\ApiMiddleware\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SslRequiredException extends Exception
{
    public function __construct(string $message, int $code, Request $request)
    {
        parent::__construct($message, $code);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => $this->getMessage(),
            'code' => $this->getCode(),
        ], $this->getCode());
    }
}
