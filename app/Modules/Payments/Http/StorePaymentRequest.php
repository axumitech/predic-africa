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
            'msisdn' => ['required', 'string', 'max:32'],
            'country' => ['exclude_unless:kind,withdraw', 'missing'],
            'wallet_token' => ['exclude_unless:kind,withdraw', 'required', 'string', 'size:64'],
            'current_password' => ['exclude_unless:kind,withdraw', 'required', 'string', 'max:255'],
        ];
    }

    public function operation(): array
    {
        // Payments validates and normalizes once, in the wallet's native units.
        return $this->validated();
    }

    public function messages(): array
    {
        return [
            'correspondent.required' => 'Sélectionnez un opérateur Mobile Money.',
            'msisdn.required' => $this->input('kind') === 'withdraw'
                ? 'Saisissez le numéro Mobile Money du bénéficiaire.'
                : 'Saisissez votre numéro Mobile Money.',
            'wallet_token.required' => 'Rechargez la page du portefeuille, puis recommencez le retrait.',
            'wallet_token.size' => 'Rechargez la page du portefeuille, puis recommencez le retrait.',
        ];
    }
}
