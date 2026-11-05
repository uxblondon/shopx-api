<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;


class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if (auth()->user()->admin === 1) {
            return true;
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $user_id = FormRequest::segment(4);
        return [
            'name' => ['string', 'max:255'],
            'disabled' => ['boolean'],
            'email' => [
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user_id),
            ],
            'password' => ['string', 'confirmed', new \App\Rules\AccountPassword],
        ];
    }

    /**
     * Throws the validation errors
     * @return JSON 
     */
    protected function failedValidation(Validator $validator)
    {
        $data = array(
            'status' => 'error',
            'message' => 'Invalid Request',
            'errors' => $validator->errors(),
        );

        throw new HttpResponseException(response()->json($data, 422));
    }
}
