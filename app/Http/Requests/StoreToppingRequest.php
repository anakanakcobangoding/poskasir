<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreToppingRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', Rule::unique('toppings', 'name')],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'hpp' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'image' => ['nullable', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048'],
            'is_available' => ['sometimes', 'boolean'],
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
            'image.image' => 'File topping harus berupa gambar.',
            'image.max' => 'Ukuran gambar topping maksimal 2 MB.',
            'image.extensions' => 'Ekstensi gambar harus JPG, PNG, atau WebP.',
            'name.required' => 'Nama topping wajib diisi.',
            'name.unique' => 'Nama topping sudah digunakan, pilih nama lain.',
            'name.max' => 'Nama topping maksimal 255 karakter.',
            'price.required' => 'Harga jual wajib diisi.',
            'price.numeric' => 'Harga jual harus berupa angka.',
            'price.min' => 'Harga jual tidak boleh kurang dari 0.',
            'price.max' => 'Harga jual terlalu besar.',
            'hpp.numeric' => 'HPP harus berupa angka.',
            'hpp.min' => 'HPP tidak boleh kurang dari 0.',
            'hpp.max' => 'HPP terlalu besar.',
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
            'name' => 'nama topping',
            'price' => 'harga jual',
            'hpp' => 'hpp',
            'is_available' => 'status tersedia',
        ];
    }
}
