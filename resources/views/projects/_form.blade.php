@php
    $project ??= null;
    $fixedKind ??= null;
    $showErrors = $fixedKind === null || old('kind') === $fixedKind->value;
    $value = fn (string $field, mixed $current = null) => $fixedKind === null || old('kind') === $fixedKind->value ? old($field, $current) : $current;
@endphp

<div @class(['grid items-start gap-3', 'sm:grid-cols-[1fr_auto_auto]' => $fixedKind === null, 'sm:grid-cols-[1fr_10rem]' => $fixedKind === \App\Enums\ProjectKind::Project])>
    <label class="block">
        <span class="field-label">Name</span>
        <input name="name" value="{{ $value('name', $project?->name) }}" required maxlength="100" class="field mt-1">
        @if ($showErrors)
            @error('name') <span class="field-error">{{ $message }}</span> @enderror
        @endif
    </label>
    @if ($fixedKind)
        <input type="hidden" name="kind" value="{{ $fixedKind->value }}">
    @else
    <label class="block">
        <span class="field-label">Kind</span>
        <select name="kind" class="field mt-1 w-auto">
            @foreach ($kinds as $kind)
                <option value="{{ $kind->value }}" @selected(old('kind', $project?->kind->value ?? 'project') === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </select>
    </label>
    @endif
    @if ($fixedKind !== \App\Enums\ProjectKind::Area)
    <label class="block">
        <span class="field-label">Deadline</span>
        <input type="date" name="due_on" value="{{ $value('due_on', $project?->due_on?->toDateString()) }}" class="field mt-1">
    </label>
    @endif
</div>
<label class="mt-3 block">
    <span class="field-label">Goal or description <span class="font-normal text-ink-400">· helps the AI file notes here</span></span>
    <textarea name="goal" rows="2" maxlength="1000" class="field mt-1">{{ $value('goal', $project?->goal) }}</textarea>
</label>
