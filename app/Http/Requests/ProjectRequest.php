<?php

namespace App\Http\Requests;

use App\Enums\ProjectKind;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Creating and updating a project or area share the same rules.
 */
class ProjectRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', Rule::enum(ProjectKind::class)],
            'goal' => ['nullable', 'string', 'max:1000'],
            'due_on' => ['nullable', 'date'],
        ];
    }

    /**
     * Names must stay distinguishable, because the analyzer picks a project by name.
     * Compared by slug, so case and spacing do not count as a difference.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $isTaken = Project::query()
                    ->where('slug', Str::slug(Str::squish((string) $this->input('name'))))
                    ->when($this->route('project'), fn ($query, Project $project) => $query->whereKeyNot($project->id))
                    ->exists();

                if ($isTaken) {
                    $validator->errors()->add('name', 'A project or area with this name already exists.');
                }
            },
        ];
    }
}
