import { CloseIcon } from '@/Components/Icons';
import { useEffect } from 'react';

/**
 * Modal. `side` renders it as a right-hand slide-over for forms.
 */
export default function Dialog({ open, onClose, title, description, side = false, wide = false, children }) {
    useEffect(() => {
        if (!open) return undefined;

        const onKey = (event) => event.key === 'Escape' && onClose();
        document.addEventListener('keydown', onKey);
        document.body.style.overflow = 'hidden';

        return () => {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = '';
        };
    }, [open, onClose]);

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 font-barlow" role="dialog" aria-modal="true" aria-labelledby="dialog-title">
            <button type="button" aria-label="Close" className="absolute inset-0 bg-arka-navy/40" onClick={onClose} />

            <div
                className={
                    side
                        ? 'absolute inset-y-0 right-0 flex w-full max-w-md flex-col border-l border-console-line bg-console-panel shadow-2xl shadow-arka-navy/20'
                        : `absolute left-1/2 top-1/2 max-h-[calc(100vh-2rem)] w-[calc(100%-2rem)] -translate-x-1/2 -translate-y-1/2 overflow-y-auto border border-console-line bg-console-panel shadow-2xl shadow-arka-navy/20 ${wide ? 'max-w-3xl' : 'max-w-md'}`
                }
            >
                <div className="flex items-start justify-between gap-4 border-b border-console-line px-6 py-5">
                    <div>
                        <h2 id="dialog-title" className="font-condensed text-[22px] font-bold leading-tight text-console-heading">
                            {title}
                        </h2>
                        {description && <p className="mt-1 text-sm text-console-muted">{description}</p>}
                    </div>
                    <button type="button" onClick={onClose} className="p-1 text-console-muted transition-colors hover:text-arka-teal" aria-label="Close">
                        <CloseIcon />
                    </button>
                </div>
                <div className={side ? 'flex-1 overflow-y-auto px-6 py-6' : 'px-6 py-6'}>{children}</div>
            </div>
        </div>
    );
}
