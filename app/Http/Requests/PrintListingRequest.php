<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Single request object backing the whole Print Center. The centre lives in
 * ONE controller action (index) that reads `type`, filters by search/date and
 * paginates the eager-loaded rows -- no per-type endpoints needed.
 */
class PrintListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return hms_can('printout');
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'nullable', 'in:invoice,medicine-slip,case-paper,prescription'],
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'date' => ['sometimes', 'nullable', 'date'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function type(): string
    {
        return $this->validated('type') ?: 'invoice';
    }

    public function search(): ?string
    {
        $q = $this->validated('search') ?: null;

        return $q !== null ? trim($q) : null;
    }

    public function slipDate(): ?string
    {
        return $this->validated('date') ?? today()->toDateString();
    }
}