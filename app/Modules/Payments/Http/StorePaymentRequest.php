<?php

namespace App\Modules\Payments\Http;

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
            'kind' => ['required', Rule::in(['deposit', 'withdraw'])],
            'amount' => ['required'],
            'reference' => ['required', 'uuid'],
            'currency' => ['exclude_unless:kind,withdraw', 'required', 'string', 'size:3'],
            'correspondent' => ['required', 'string', 'max:64'],
            'msisdn' => ['missing_if:kind,withdraw', 'required_if:kind,deposit', 'string', 'max:32'],
            'country' => ['exclude_unless:kind,withdraw', 'missing'],
            'beneficiary' => ['exclude_unless:kind,withdraw', 'required', 'string', 'size:64'],
            'current_password' => ['exclude_unless:kind,withdraw', 'required', 'string', 'max:255'],
        ];
    }

    public function operation(): array
    {
        // Payments validates and normalizes once, in the wallet's native units.
        return $this->validated();
    }
}
