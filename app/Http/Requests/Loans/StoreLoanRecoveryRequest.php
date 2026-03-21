<?php

namespace App\Http\Requests\Loans;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoanRecoveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recovery_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
