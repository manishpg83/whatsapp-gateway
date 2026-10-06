{{-- A code sample with a Copy button (same look and copy script as the
     API docs). The slot is printed as-is, so write "<" as "&lt;" in it. --}}
@props(['label'])
<div class="dc-code my-3">
    <div class="dc-code-head"><span>{{ $label }}</span><button type="button" class="dc-copy" data-dc-copy><i class="bi bi-clipboard"></i> Copy</button></div>
<pre><code>{{ $slot }}</code></pre>
</div>
