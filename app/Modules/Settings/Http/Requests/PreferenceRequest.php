<?php

namespace App\Modules\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pengaturan pribadi tiap pengguna (bukan pengaturan usaha), jadi semua role boleh mengubah
 * miliknya sendiri. Boleh mengirim sebagian saja, misalnya hanya { theme: 'dark' }.
 */
class PreferenceRequest extends FormRequest
{
    public const DISPLAY_SIZES = ['normal', 'besar', 'sangat-besar'];

    public const THEMES = ['system', 'light', 'dark'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'display_size' => ['sometimes', Rule::in(self::DISPLAY_SIZES)],
            'theme' => ['sometimes', Rule::in(self::THEMES)],
            'sound' => ['sometimes', 'boolean'],
            'simple_mode' => ['sometimes', 'boolean'],
            'tour_done' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'display_size.in' => 'Pilih ukuran tampilan: Normal, Besar, atau Sangat Besar.',
            'theme.in' => 'Pilih tema: Ikuti HP, Terang, atau Gelap.',
        ];
    }
}
