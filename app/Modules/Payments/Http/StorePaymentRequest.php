<?php

namespace App\Modules\Payments\Http;

use App\Modules\Payments\Capabilities;
use App\Modules\Payments\PaymentPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && app(PaymentPolicy::class)->deposit($this->user())
            && ($this->input('kind') !== 'withdraw' || app(PaymentPolicy::class)->payout($this->user()));
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(['deposit'])],
            'amount' => ['required'],
            'reference' => ['required', 'uuid'],
            'correspondent' => ['required', 'string', 'max:64'],
            'msisdn' => ['required', 'string', 'max:32'],
        ];
    }

    public function operation(): array
    {
        // Payments validates and normalizes once, in the wallet's native units.
        return $this->validated();
    }
}
