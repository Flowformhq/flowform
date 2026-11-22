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
| **{{ ucwords(str_replace(['_', '-'], ' ', (string) $key)) }}** | {{ is_array($value) ? json_encode($value) : $value }} |
@endforeach
@else
*No form data was provided.*
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
