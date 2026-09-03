<?php

namespace App\Modules\Waybill\Requests;

use App\Modules\Settings\Services\SystemSettingService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class GenerateWaybillBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:'.$this->maximumQuantity()],
            'label_template' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $maximum = $this->maximumQuantity();
        $quantity = $this->input('quantity');

        if (is_numeric($quantity) && (int) $quantity > $maximum) {
            throw new HttpResponseException(response()->json([
                'code' => 'WAYBILL_BATCH_LIMIT_EXCEEDED',
                'message' => 'The requested quantity exceeds the configured batch limit.',
                'errors' => $validator->errors(),
            ], 422));
        }

        parent::failedValidation($validator);
    }

    private function maximumQuantity(): int
    {
        return max(1, (int) app(SystemSettingService::class)->get('max_preprinted_waybill_batch_size', 500));
    }
}
