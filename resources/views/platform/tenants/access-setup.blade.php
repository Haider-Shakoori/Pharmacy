<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Darmaltoon - Set password</title></head>
<body style="font-family:system-ui;max-width:440px;margin:10vh auto;padding:20px">
<h1>Darmaltoon</h1>
@if (!empty($complete))
<p>Your password has been saved. You can now sign in to your pharmacy.</p>
@else
<h2>Set your pharmacy password</h2>
@if ($errors->any())<p style="color:#b91c1c">{{ $errors->first() }}</p>@endif
<form method="POST" action="{{ request()->fullUrl() }}">
@csrf
<label>New password (at least 12 characters)</label>
<input name="password" type="password" minlength="12" required autocomplete="new-password" style="display:block;width:100%;padding:10px;margin:8px 0 18px">
<label>Confirm password</label>
<input name="password_confirmation" type="password" minlength="12" required autocomplete="new-password" style="display:block;width:100%;padding:10px;margin:8px 0 18px">
<button type="submit" style="background:#0f766e;color:white;padding:12px 20px;border:0;border-radius:6px">Save password</button>
</form>
@endif
</body></html>
