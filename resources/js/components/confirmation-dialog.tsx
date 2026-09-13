import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { TriangleAlert } from 'lucide-react';

interface ConfirmationDialogProps {
    open: boolean;
    title: string;
    description: string;
    confirmLabel?: string;
    cancelLabel?: string;
    tone?: 'warning' | 'danger';
    onConfirm: () => void;
    onCancel: () => void;
}

/**
 * Dialog penegasan untuk tindakan yang perlu keputusan eksplisit dari pengguna.
 * Kontrol `open` sengaja berada di pemanggil agar komponen ini bisa dipakai untuk
 * navigasi tertunda, penghapusan, maupun tindakan sensitif lainnya.
 */
export default function ConfirmationDialog({
    open,
    title,
    description,
    confirmLabel = 'Lanjutkan',
    cancelLabel = 'Batal',
    tone = 'warning',
    onConfirm,
    onCancel,
}: ConfirmationDialogProps) {
    const bahaya = tone === 'danger';

    return (
        <Dialog open={open} onOpenChange={(terbuka) => !terbuka && onCancel()}>
            <DialogContent className="overflow-hidden border-0 bg-white p-0 shadow-2xl sm:max-w-md sm:rounded-2xl">
                <div className="p-6">
                    <DialogHeader className="pr-7 text-left">
                        <div
                            className={cn(
                                'mb-3 flex size-11 items-center justify-center rounded-full',
                                bahaya ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700',
                            )}
                        >
                            <TriangleAlert size={22} strokeWidth={2.2} />
                        </div>
                        <DialogTitle className="text-lg font-semibold text-gray-900">{title}</DialogTitle>
                        <DialogDescription className="pt-1 text-sm leading-6 text-gray-600">{description}</DialogDescription>
                    </DialogHeader>
                </div>

                <DialogFooter className="gap-2 border-t border-gray-100 bg-gray-50/80 px-6 py-4 sm:space-x-0">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onCancel}
                        className="rounded-xl border-gray-300 bg-white font-semibold text-gray-700"
                    >
                        {cancelLabel}
                    </Button>
                    <Button
                        type="button"
                        onClick={onConfirm}
                        className={cn(
                            'rounded-xl font-semibold text-white',
                            bahaya ? 'bg-red-600 hover:bg-red-700' : 'bg-amber-600 hover:bg-amber-700',
                        )}
                    >
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
