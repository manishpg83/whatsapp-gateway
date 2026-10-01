{{-- Admin panel footer (layouts/app.blade.php): just copyright + powered
     by, no links. Everyone else gets partials/footer.blade.php. --}}
<footer class="admin-footer">
    <div class="px-3 px-md-4 d-flex flex-column flex-sm-row justify-content-between gap-1">
        <span class="d-flex align-items-center gap-2"><x-brand-logo size="sm" /> &copy; {{ now()->year }}. All rights reserved.</span>
        <span>Powered by <strong>BriskBrain Technologies</strong></span>
    </div>
</footer>
