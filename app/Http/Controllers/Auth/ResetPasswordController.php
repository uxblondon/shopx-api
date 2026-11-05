<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\PasswordResetLinkRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Services\PasswordReset;

class ResetPasswordController extends Controller
{
    public function sendResetLinkEmail(PasswordResetLinkRequest $request, PasswordReset $resets)
    {
        $resets->request($request->input('email'));
        return response()->json(['status' => 'success',
            'message' => 'If an eligible account exists, a password reset link will be emailed shortly.']);
    }

    public function reset(ResetPasswordRequest $request, PasswordReset $resets)
    {
        // Match the route and body so contradictory credentials cannot be accepted.
        if (! hash_equals((string) $request->route('token'), $request->input('token'))
            || ! $resets->reset($request->input('email'), $request->input('token'), $request->input('password'))) {
            return response()->json(['status' => 'error', 'message' => 'This reset link is invalid or has expired. Please request a new one.'], 422);
        }
        return response()->json(['status' => 'success', 'message' => 'Password reset successful.']);
    }
}
