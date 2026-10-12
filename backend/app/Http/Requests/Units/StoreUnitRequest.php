<?php

namespace App\Http\Requests\Units;

use Illuminate\Foundation\Http\FormRequest;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'block_id' => ['required', 'integer', 'exists:blocks,id'],
            'street_id' => ['required', 'integer', 'exists:streets,id'],
            'unit_number' => ['required', 'string', 'max:50'],
            'full_address' => ['required', 'string'],
            'unit_category_id' => ['required', 'integer', 'exists:unit_categories,id'],
            'tariff_type_id' => ['required', 'integer', 'exists:tariff_types,id'],
            'residence_status' => ['required', 'in:owner,tenant'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'occupant_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
