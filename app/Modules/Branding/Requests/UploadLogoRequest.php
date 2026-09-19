<?php

namespace App\Modules\Branding\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization is checked via permission middleware & policy
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'logo' => [
                'required',
                'file',
                'image',
                'mimes:png,jpg,jpeg,webp',
                'max:4096',
                'dimensions:min_width=64,min_height=64,max_width=4096,max_height=4096',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.required' => 'Please select a logo image file to upload.',
            'logo.file' => 'The uploaded logo must be a valid file.',
            'logo.image' => 'The logo must be an image (PNG, JPG, or WebP).',
            'logo.mimes' => 'Only PNG, JPG, and WebP image formats are accepted.',
            'logo.max' => 'The logo image may not be greater than 4 MB (4096 KB).',
            'logo.dimensions' => 'The logo dimensions must be between 64×64 and 4096×4096 pixels.',
        ];
    }
}
