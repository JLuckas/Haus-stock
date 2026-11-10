<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveStockRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
        ];
    }
}
