<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
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
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'hpp' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'image' => ['nullable', 'image', 'max:2048', 'mimes:jpeg,png,jpg,webp', 'extensions:jpg,jpeg,png,webp'],
            'remove_image' => ['sometimes', 'boolean'],
            'is_available' => ['sometimes', 'boolean'],
            'topping_ids' => ['nullable', 'array'],
            'topping_ids.*' => ['integer', 'distinct', 'exists:toppings,id'],
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
            'category_id.required' => 'Kategori wajib dipilih.',
            'category_id.exists' => 'Kategori yang dipilih tidak ditemukan.',
            'name.required' => 'Nama produk wajib diisi.',
            'name.max' => 'Nama produk maksimal 255 karakter.',
            'price.required' => 'Harga jual wajib diisi.',
            'price.numeric' => 'Harga jual harus berupa angka.',
            'price.min' => 'Harga jual tidak boleh kurang dari 0.',
            'price.max' => 'Harga jual terlalu besar.',
            'hpp.numeric' => 'HPP harus berupa angka.',
            'hpp.min' => 'HPP tidak boleh kurang dari 0.',
            'hpp.max' => 'HPP terlalu besar.',
            'image.image' => 'File harus berupa gambar.',
            'image.max' => 'Ukuran gambar maksimal 2 MB.',
            'image.mimes' => 'Format gambar harus JPG, PNG, atau WebP.',
            'topping_ids.array' => 'Daftar topping tidak valid.',
            'topping_ids.*.integer' => 'Topping yang dipilih tidak valid.',
            'topping_ids.*.distinct' => 'Topping yang sama tidak boleh dipilih dua kali.',
            'topping_ids.*.exists' => 'Ada topping yang dipilih tidak ditemukan.',
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
            'category_id' => 'kategori',
            'name' => 'nama produk',
            'price' => 'harga jual',
            'hpp' => 'hpp',
            'image' => 'gambar produk',
            'is_available' => 'status tersedia',
            'topping_ids' => 'topping',
        ];
    }
}
