<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_name' => ['required', 'string', 'max:100'],
            'pos_name' => ['required', 'string', 'max:100'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'main_title' => ['nullable', 'string', 'max:150'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'store_name.required' => 'Nama toko wajib diisi.',
            'store_name.max' => 'Nama toko maksimal 100 karakter.',
            'pos_name.required' => 'Nama POS wajib diisi.',
            'pos_name.max' => 'Nama POS maksimal 100 karakter.',
            'subtitle.max' => 'Subjudul maksimal 255 karakter.',
        ];
    }

    public function attributes(): array
    {
        return [
            'store_name' => 'nama toko',
            'pos_name' => 'nama POS',
            'subtitle' => 'subjudul',
        ];
    }
}
