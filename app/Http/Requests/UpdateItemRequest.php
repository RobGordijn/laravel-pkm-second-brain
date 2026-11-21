<?php

namespace App\Http\Requests;

use App\Enums\ItemType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'body' => ['sometimes', 'nullable', 'string', 'max:50000'],
            'type' => ['required', Rule::enum(ItemType::class)],
            'tags' => ['nullable', 'string', 'max:500'],
            'why_saved' => ['nullable', 'string', 'max:1000'],
            'projects' => ['nullable', 'array'],
            'projects.*' => ['integer', 'exists:projects,id'],
        ];
    }

    /**
     * Browsers submit textarea line breaks as CRLF; store them as LF so an untouched body compares equal.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('body'))) {
            $this->merge(['body' => str_replace("\r\n", "\n", $this->input('body'))]);
        }
    }

    /**
     * The comma separated tags as a clean list.
     *
     * @return list<string>
     */
    public function tagNames(): array
    {
        return collect(explode(',', (string) $this->validated('tags')))
            ->map(fn (string $tag) => trim($tag))
            ->filter()
            ->values()
            ->all();
    }
}
