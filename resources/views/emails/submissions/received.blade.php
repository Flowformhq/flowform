<x-mail::message>
# New Submission: {{ $form->name }}

You received a new submission on **{{ $form->name }}** (`{{ $form->slug }}`).

**Submitted At:** {{ $submission->created_at?->toDayDateTimeString() ?? now()->toDayDateTimeString() }}  
**Submission UUID:** `{{ $submission->uuid }}`

---

### Submitted Details

@if (!empty($data) && count($data) > 0)
| Field | Value |
| :--- | :--- |
@php
    $fileUrlPrefix = url("admin/submissions/{$submission->id}/files/");
    $isFile = fn ($item) => is_array($item) && is_string($item['name'] ?? null) && is_string($item['url'] ?? null) && str_starts_with($item['url'], $fileUrlPrefix);
    $fileLink = fn ($file) => '['.e(str_replace(['[', ']'], '', $file['name'])).']('.e($file['url']).')';
@endphp
@foreach ($data as $key => $value)
@php
    if ($isFile($value)) {
        $displayValue = $fileLink($value);
    } elseif (is_array($value) && $value !== [] && collect($value)->every($isFile)) {
        $displayValue = collect($value)->map($fileLink)->implode(', ');
    } else {
        $displayValue = e(is_array($value) ? json_encode($value) : (string) $value);
    }
@endphp
| **{{ ucwords(str_replace(['_', '-'], ' ', (string) $key)) }}** | {!! $displayValue !!} |
@endforeach
@else
*No form data was provided.*
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
