<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use App\Models\Category;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $categories = Category::where('status', 'published')->pluck('id');

        return [
            'category_id' => [
                'nullable',
                'numeric',
                Rule::in($categories),
                'required',
            ],
            'title' => 'nullable|required',
            'standfirst' => 'nullable',
            'feature_image' => 'nullable|image',
            'description' => 'nullable',
            'tags' => 'nullable',
            'status' => [
                Rule::in(['draft', 'published']),
            ],
            'meta_description' => 'nullable',
            'meta_keywords' => 'nullable',
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
            'errors' => $validator->errors()
        );

        throw new HttpResponseException(response()->json($data, 422));
    }

}
