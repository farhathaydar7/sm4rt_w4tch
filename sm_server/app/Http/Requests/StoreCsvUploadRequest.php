<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCsvUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true; // Authentication is handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'csv_file' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:10240', // Max 10MB
                'mimetypes:text/csv,text/plain',
            ],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'csv_file.required' => 'Please upload a CSV file.',
            'csv_file.file' => 'The uploaded file is not valid.',
            'csv_file.mimes' => 'The file must be a CSV file.',
            'csv_file.max' => 'The file size must not exceed 10MB.',
            'csv_file.mimetypes' => 'The file must be a CSV file with proper format.',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->hasFile('csv_file') && $this->file('csv_file')->isValid()) {
                // Additional custom validation can be added here if needed
                // For example, verifying the file is not empty
                $file = $this->file('csv_file');
                if ($file->getSize() === 0) {
                    $validator->errors()->add('csv_file', 'The CSV file is empty.');
                }
            }
        });
    }
}
