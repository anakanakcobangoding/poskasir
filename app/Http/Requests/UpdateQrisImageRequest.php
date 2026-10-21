<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateQrisImageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'qris_image' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'qris_image.required' => 'Pilih file gambar QRIS terlebih dahulu.',
            'qris_image.file' => 'Unggahan QRIS tidak valid.',
            'qris_image.mimetypes' => 'Gambar QRIS harus berupa file JPG, PNG, atau WEBP.',
            'qris_image.mimes' => 'Gambar QRIS harus berformat JPG, JPEG, PNG, atau WEBP.',
            'qris_image.max' => 'Ukuran gambar QRIS maksimal 2 MB.',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'qris_image' => 'gambar QRIS',
        ];
    }
}
