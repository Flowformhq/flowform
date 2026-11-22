<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $confirmed ? 'Notification Email Verified' : 'Confirm Notification Email' }} — FlowForm</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #0f172a;
            color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 1.5rem;
            box-sizing: border-box;
        }
        .card {
            background-color: #1e293b;
            border: 1px solid #334155;
            border-radius: 0.75rem;
            padding: 2.5rem;
            max-width: 28rem;
            width: 100%;
            text-align: center;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);
        }
        .icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #38bdf8;
        }
        p {
            color: #94a3b8;
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 1.5rem;
        }
        .button {
            background-color: #38bdf8;
            color: #0f172a;
            border: 0;
            border-radius: 0.375rem;
            padding: 0.75rem 1.25rem;
            font-weight: 600;
            cursor: pointer;
        }
        .badge {
            display: inline-block;
            background-color: #0f172a;
            color: #cbd5e1;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-family: monospace;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="card">
        @if ($confirmed)
            <h1>Notification Email Verified</h1>
            <p>Your email address has been verified. You will now receive form submissions for <strong>{{ $form->name ?? 'your form' }}</strong>.</p>
            <div class="badge">{{ $recipient->email }}</div>
        @else
            <h1>Confirm Notification Email</h1>
            <p>Receive form submissions for <strong>{{ $form->name ?? 'this form' }}</strong> at this address?</p>
            <div class="badge">{{ $recipient->email }}</div>
            <form method="POST" action="{{ $confirmUrl }}">
                @csrf
                <button type="submit" class="button">Yes, send me submissions</button>
            </form>
        @endif
    </div>
</body>
</html>
