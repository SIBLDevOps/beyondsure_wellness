{{--
    A rich-text description box (Quill). Pass: name (the field), value (what to start with), optional id, placeholder, height.
    The editor writes its HTML into a hidden field on submit; the server cleans it again before saving (App\Support\RichText).
--}}
@php
    $rtId = $id ?? $name;
    $rtHeight = $height ?? 140;
@endphp

@once
    @push('styles')
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css">
        <style>
            .rich-editor-wrap .ql-toolbar.ql-snow { border-radius: .5rem .5rem 0 0; background: var(--bs-tertiary-bg, #f8f9fa); }
            .rich-editor-wrap .ql-container.ql-snow { border-radius: 0 0 .5rem .5rem; font: inherit; font-size: .92rem; }
            .rich-editor-wrap.is-invalid .ql-toolbar, .rich-editor-wrap.is-invalid .ql-container { border-color: var(--bs-danger, #dc3545); }
            .rich-editor-wrap .ql-editor { line-height: 1.55; }
            .rich-editor-wrap .ql-editor.ql-blank::before { font-style: normal; color: var(--bs-secondary-color, #6c757d); }
        </style>
    @endpush
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
        <script>
            document.querySelectorAll('[data-rich-editor]').forEach(function (box) {
                var input = document.getElementById(box.dataset.richEditor);
                var quill = new Quill(box, {
                    theme: 'snow',
                    placeholder: box.dataset.placeholder || '',
                    modules: { toolbar: [[{ header: [2, 3, false] }], ['bold', 'italic', 'underline', 'strike'], [{ list: 'ordered' }, { list: 'bullet' }], ['blockquote', 'link'], ['clean']] },
                    formats: ['header', 'bold', 'italic', 'underline', 'strike', 'list', 'blockquote', 'link'],
                });
                if (input.value) { quill.clipboard.dangerouslyPasteHTML(input.value); }
                var sync = function () { input.value = quill.getText().trim() === '' && quill.root.querySelectorAll('li, blockquote').length === 0 ? '' : quill.root.innerHTML; };
                quill.on('text-change', sync);
                if (input.form) { input.form.addEventListener('submit', sync); }
            });
        </script>
    @endpush
@endonce

<div class="rich-editor-wrap @error($name) is-invalid @enderror">
    <div id="{{ $rtId }}-editor" data-rich-editor="{{ $rtId }}" data-placeholder="{{ $placeholder ?? '' }}" style="height: {{ (int) $rtHeight }}px;"></div>
    <input type="hidden" name="{{ $name }}" id="{{ $rtId }}" value="{{ \App\Support\RichText::forEditor($value ?? '') }}">
</div>
@error($name) <div class="text-danger small mt-1">{{ $message }}</div> @enderror
