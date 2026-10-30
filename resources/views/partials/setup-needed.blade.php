{{-- Shown instead of a page whose database table has not been created yet. Pass $what, e.g. "The audit log". --}}
<div class="card border text-center py-5"><div class="card-body">
    <i class="bi bi-database-gear fs-1 text-warning"></i>
    <h2 class="h5 fw-bold mt-2">One database update is needed first</h2>
    <p class="text-secondary mb-3" style="max-width: 520px; margin: 0 auto;">
        {{ $what ?? 'This page' }} needs a database update that has not been run yet. Run this once in the project folder, then reload this page.
    </p>
    <code class="d-inline-block bg-light border rounded px-3 py-2">php artisan migrate --force</code>
</div></div>
