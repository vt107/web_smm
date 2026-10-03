@php
    $demoText = array_merge(['lang' => 'vi', 'blocked_title' => 'Bản demo chỉ xem', 'back' => 'Quay lại'], (array) config('demo.texts', []));
@endphp
<!DOCTYPE html>
<html lang="{{ $demoText['lang'] }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $demoText['blocked_title'] }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 16px; box-sizing: border-box;
               font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f6f5f2; color: #1c1b19; }
        .box { max-width: 420px; text-align: center; }
        h1 { font-size: 20px; margin: 0 0 8px; }
        p { margin: 0 0 20px; color: #5b5853; line-height: 1.5; }
        a { display: inline-block; padding: 10px 18px; border-radius: 10px; background: #1c1b19; color: #fff; text-decoration: none; font-weight: 600; }
        @media (prefers-color-scheme: dark) { body { background: #161514; color: #f1efe9; } p { color: #a9a59d; } a { background: #f1efe9; color: #161514; } }
    </style>
</head>
<body>
    <div class="box">
        <h1>{{ $demoText['blocked_title'] }}</h1>
        <p>{{ $message }}</p>
        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}">{{ $demoText['back'] }}</a>
    </div>
</body>
</html>
