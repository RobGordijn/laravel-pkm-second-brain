<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class StoreDigestProjectRequest extends FormRequest
{
    /**
     * Each suggestion has its own form on the page, so errors go to a bag per suggestion.
     */
    protected function prepareForValidation(): void
    {
        $this->errorBag = 'suggestion'.$this->route('suggestion');
    }

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
            'name' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * Notes are added to an active project with the same name, but never slipped into the archive.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $isArchived = Project::query()
                    ->where('slug', Str::slug(Str::squish((string) $this->input('name'))))
                    ->whereNotNull('archived_at')
                    ->exists();

                if ($isArchived) {
                    $validator->errors()->add('name', 'An archived project or area already has this name. Choose another name or restore it first.');
                }
            },
        ];
    }
}
