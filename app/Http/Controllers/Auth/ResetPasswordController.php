<?php

namespace App\Http\Controllers\Auth;


use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\ResetsPasswords;
use App\Http\Requests\PasswordResetLinkRequest;
use App\Http\Requests\ResetPasswordRequest;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Mail\PasswordResetLink;
use App\Models\User;

class ResetPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */

    use ResetsPasswords;

    /**
     * Where to redirect users after resetting their password.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;


    /**
     * Send a reset link to the given user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function sendResetLinkEmail(PasswordResetLinkRequest $request)
    {

        $user = User::where('email', $request->get('email'))->first();

        if(!$user) {
            return response()->json(['status' => 'error', 'message' => 'No account found with this email address.']);
        }


        try {
            $user = User::where('email', $request->get('email'))->first();
            User::where('id', $user->id)
            ->update([
                'password_reset_token' => Str::random(60),
                'password_reset_token_created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to send password reset email.']);
        }
        return response()->json(['status' => 'success', 'message' => 'Password reset email successfully sent.']);
    }


    /**
     * Reset the given user's password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function reset(ResetPasswordRequest $request)
    {
        $user = User::where('password_reset_token', $request->get('token'))
        ->where('email', $request->get('email'))
        ->first();

        if(!$user) {
            return response()->json([
                'status' => 'error', 
            'message' => 'Invalid token supplied.'
        ]);
        }

        try {
            User::where('id', $user->id)->update([
                'password' => Hash::make($request->get('password')),
                'password_reset_token' => NULL,
                'password_reset_token_created_at' => NULL,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error', 
            'message' => 'Failed to reset the password. Please try again.'
        ]);
        }

        return response()->json([
            'status' => 'success', 
            'message' => 'Password reset successful.']);
    }
}
