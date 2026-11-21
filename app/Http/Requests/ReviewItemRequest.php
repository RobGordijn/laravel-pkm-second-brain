<?php

namespace App\Http\Requests;

use App\Enums\ProjectKind;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReviewItemRequest extends FormRequest
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
            'decision' => ['required', Rule::in(['project', 'new_project', 'resource', 'archive'])],
            'project_id' => [
                'required_if:decision,project',
                'nullable',
                'integer',
                Rule::exists('projects', 'id')->whereNull('archived_at'),
            ],
            'name' => ['required_if:decision,new_project', 'nullable', 'string', 'max:100'],
            'kind' => ['required_if:decision,new_project', 'nullable', Rule::enum(ProjectKind::class)],
        ];
    }

    /**
     * A new project may reuse an active one with the same name, but not revive an archived one silently.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->input('decision') !== 'new_project' || blank($this->input('name'))) {
                    return;
                }

                $isArchived = Project::query()
                    ->where('slug', Str::slug(Str::squish((string) $this->input('name'))))
                    ->whereNotNull('archived_at')
                    ->exists();

                if ($isArchived) {
                    $validator->errors()->add('name', 'An archived project or area already has this name.');
                }
            },
        ];
    }
}
