<?php

declare(strict_types=1);

namespace App\Http\Requests\Campaign;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $id = $this->route('campaign')?->id ?? null;

        return [
            'name'       => ['sometimes', 'string', 'min:1', 'max:120', Rule::unique('campaigns', 'name')->ignore($id)],
            'start_date' => ['sometimes', 'date', 'date_format:Y-m-d'],
            'end_date'   => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'is_active'  => ['sometimes', 'boolean'],
        ];
    }
}
