<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveAnswerRequest extends FormRequest
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
            'question' => ['required', 'string', 'max:1000'],
            'answer' => ['required', 'string', 'max:50000'],
            'source_ids' => ['nullable', 'array', 'max:20'],
            'source_ids.*' => ['integer'],
        ];
    }

    /**
     * The notes the answer cites; when it cites none, all notes it was given.
     *
     * @return list<int>
     */
    public function sourceIds(): array
    {
        $given = collect($this->validated('source_ids', []))->map(fn (int|string $id) => (int) $id);

        preg_match_all('/#(\d+)/', $this->validated('answer'), $matches);
        $cited = $given->intersect(array_map('intval', $matches[1]));

        return ($cited->isNotEmpty() ? $cited : $given)->unique()->values()->all();
    }
}
