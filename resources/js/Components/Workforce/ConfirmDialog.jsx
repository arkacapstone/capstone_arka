import Dialog from '@/Components/Console/Dialog';
import { ConsoleButton } from '@/Components/Console/Field';

export default function ConfirmDialog({ open, title, body, confirmLabel, danger = false, processing = false, onConfirm, onClose }) {
    return (
        <Dialog open={open} onClose={onClose} title={title}>
            <p className="text-[13px] leading-relaxed text-console-muted">{body}</p>
            <div className="mt-6 flex items-center gap-3">
                <ConsoleButton
                    type="button"
                    onClick={onConfirm}
                    disabled={processing}
                    className={danger ? '!border-console-heading !bg-arka-navy hover:!bg-[#10244a]' : ''}
                >
                    {confirmLabel}
                </ConsoleButton>
                <button type="button" onClick={onClose} className="text-[13px] text-console-muted hover:text-arka-teal">
                    Cancel
                </button>
            </div>
        </Dialog>
    );
}
