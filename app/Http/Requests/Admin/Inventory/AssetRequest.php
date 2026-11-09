<?php

namespace App\Http\Requests\Admin\Inventory;

use App\Models\Asset;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $asset = $this->route('asset');
        $assetId = $asset instanceof Asset ? $asset->getKey() : null;

        return [
            'item_id' => [$assetId ? 'sometimes' : 'required', 'integer', 'exists:items,id'],
            'asset_code' => ['required', 'string', 'max:255', Rule::unique('assets', 'asset_code')->ignore($assetId)],
            'serial_number' => ['nullable', 'string', 'max:255', Rule::unique('assets', 'serial_number')->ignore($assetId)],
            'condition' => ['required', Rule::in(['good', 'damaged'])],
            'status' => ['required', Rule::in(['available', 'borrowed', 'damaged', 'maintenance', 'lost', 'inactive'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
