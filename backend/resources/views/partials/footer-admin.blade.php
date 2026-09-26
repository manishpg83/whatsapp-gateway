{{-- Admin panel footer (layouts/app.blade.php): just copyright + powered
     by, no links. Everyone else gets partials/footer.blade.php. --}}
<footer class="admin-footer">
    <div class="px-3 px-md-4 d-flex flex-column flex-sm-row justify-content-between gap-1">
        <span>&copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.</span>
        <span>Powered by <strong>BriskBrain Technologies</strong></span>
    </div>
</footer>
