@if (session('success') || session('error') || $errors->any())
    <div class="toasts" aria-live="polite">
        @if (session('success'))
            <div class="toast toast--success">
                <span>{{ session('success') }}</span>
                <button type="button" aria-label="Dismiss">&times;</button>
            </div>
        @endif

        @if (session('error'))
            <div class="toast toast--error">
                <span>{{ session('error') }}</span>
                <button type="button" aria-label="Dismiss">&times;</button>
            </div>
        @endif

        @if ($errors->any())
            <div class="toast toast--error">
                <span>{{ $errors->count() }} field(s) need your attention.</span>
                <button type="button" aria-label="Dismiss">&times;</button>
            </div>
        @endif
    </div>
@endif
