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
@foreach ($data as $key => $value)
@php
    $displayValue = $value;
    if (is_array($value)) {
        if (isset($value['name'], $value['url'])) {
            $displayValue = "[{$value['name']}]({$value['url']})";
        } elseif (! empty($value) && isset($value[0]['name'], $value[0]['url'])) {
            $displayValue = collect($value)->map(fn ($f) => "[{$f['name']}]({$f['url']})")->implode(', ');
        } else {
            $displayValue = json_encode($value);
        }
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
