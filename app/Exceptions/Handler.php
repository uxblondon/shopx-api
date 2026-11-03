<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;

class Handler extends ExceptionHandler
{
    protected $dontFlash = ['current_password', 'password', 'password_confirmation'];

    protected function shouldReturnJson($request, \Throwable $e)
    {
        return $request->is('api/*') || parent::shouldReturnJson($request, $e);
    }

    protected function invalidJson($request, ValidationException $exception)
    {
        return response()->json([
            'status' => 'error',
            'message' => $exception->getMessage(),
            'errors' => $exception->errors(),
        ], $exception->status);
    }

    protected function prepareJsonResponse($request, \Throwable $e)
    {
        $response = parent::prepareJsonResponse($request, $e);
        $data = $response->getData(true);
        $data['status'] = 'error';
        $response->setData($data);

        return $response;
    }
}
