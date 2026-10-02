import { fileSize } from '@/lib/format';
import { useRef } from 'react';

/**
 * Labelled file input that shows the chosen file's name and size, e.g. "wallpaper.png · 1.8 MB".
 */
export default function FilePicker({ id, label, hint, accept, file, onChange, error }) {
    const input = useRef(null);

    return (
        <div>
            <label htmlFor={id} className="block text-[11px] font-medium uppercase tracking-[0.18em] text-console-muted">
                {label}
            </label>
            <div className="mt-2 flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    onClick={() => input.current?.click()}
                    className="border border-console-line px-3 py-1.5 text-sm text-console-heading transition-colors hover:border-arka-teal hover:text-arka-teal"
                >
                    {file ? 'Change file' : 'Choose file'}
                </button>
                {file ? (
                    <span className="flex min-w-0 items-center gap-2 text-sm">
                        <span className="truncate text-console-text">{file.name}</span>
                        <span className="shrink-0 font-mono text-xs text-console-muted">· {fileSize(file.size)}</span>
                        <button
                            type="button"
                            onClick={() => {
                                if (input.current) input.current.value = '';
                                onChange(null);
                            }}
                            className="shrink-0 text-xs text-console-muted hover:text-arka-teal"
                        >
                            Remove
                        </button>
                    </span>
                ) : (
                    <span className="text-sm text-console-dim">No file chosen</span>
                )}
                <input ref={input} id={id} type="file" accept={accept} className="hidden" onChange={(e) => onChange(e.target.files[0] ?? null)} />
            </div>
            {hint && <p className="mt-1.5 text-xs text-console-dim">{hint}</p>}
            {error && <p className="mt-2 text-xs text-console-error">{error}</p>}
        </div>
    );
}
