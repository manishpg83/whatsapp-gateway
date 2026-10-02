{{-- Shown inside the edit page's preview frame when the form has a problem. --}}
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Preview</title></head>
<body style="margin:0;padding:32px;font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;background:#eef3f1;color:#10231c;">
    <div style="max-width:520px;margin:0 auto;padding:20px 22px;background:#fff;border-left:4px solid #dc3545;border-radius:10px;">
        <strong>Can't preview yet — please fix:</strong>
        <ul style="margin:10px 0 0;padding-left:20px;">
            @foreach ($errors as $error)
                <li style="margin-bottom:4px;">{{ $error }}</li>
            @endforeach
        </ul>
    </div>
</body>
</html>
