import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
    type ColumnDef,
    flexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    type RowData,
    type SortingState,
    useReactTable,
} from '@tanstack/react-table';
import { ChevronDown, ChevronsUpDown, ChevronUp, Minus, Plus } from 'lucide-react';
import { Fragment, useState } from 'react';

declare module '@tanstack/react-table' {
    // Parameter generik harus sama persis dengan deklarasi TanStack meski tidak
    // dipakai langsung oleh properti alignment tambahan ini.
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface ColumnMeta<TData extends RowData, TValue> {
        align?: 'left' | 'center' | 'right';
    }
}

const alignmentClass = {
    left: 'text-left',
    center: 'text-center',
    right: 'text-right',
} as const;

const headerAlignmentClass = {
    left: '',
    center: 'w-full justify-center',
    right: 'w-full justify-end',
} as const;

interface DataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
    searchPlaceholder?: string;
    /**
     * Kontrol penyaring tambahan milik halaman (mis. dropdown tahun ajaran),
     * ditaruh sebaris dengan kotak pencarian. Penyaringannya sendiri tetap
     * urusan halaman - DataTable cuma menyediakan tempatnya, supaya tabel ini
     * tidak perlu tahu apa pun soal isi datanya.
     */
    toolbar?: React.ReactNode;
    /** Kalimat saat tabel kosong. Halaman yang punya penyaring sebaiknya
     *  mengisinya dengan sebab kosongnya, bukan sekadar "tidak ada data". */
    emptyMessage?: string;
    /**
     * Lebar maksimal kotak pencarian. Dipendekkan pada halaman yang menaruh
     * tombol aksi di baris yang sama - kalau tidak, pencarian dan penyaring
     * memenuhi baris sampai mepet ke tombolnya.
     */
    searchWidth?: string;
    /** Gunakan breakpoint lebih lebar untuk tabel dengan banyak kolom. */
    desktopBreakpoint?: 'md' | 'lg';
    /** Identitas stabil baris, dibutuhkan bila baris dapat dibuka. */
    rowId?: (item: TData) => string;
    /** Baris yang langsung dibuka, misalnya setelah kembali dari halaman aksi. */
    initialExpandedRowId?: string | null;
    /** Tampilan ringkas alternatif untuk layar kecil. Tetap memakai hasil
     *  pencarian, pengurutan, dan pagination dari tabel yang sama. */
    mobileHeader?: React.ReactNode;
    renderMobileRow?: (
        item: TData,
        controls: {
            expanded: boolean;
            toggle: () => void;
        },
    ) => React.ReactNode;
    /** Detail yang muncul sebagai satu baris penuh di bawah baris desktop. */
    renderExpandedRow?: (
        item: TData,
        controls: {
            expanded: boolean;
            toggle: () => void;
        },
    ) => React.ReactNode;
}

const responsiveVisibility = {
    md: { mobile: 'md:hidden', desktop: 'hidden md:block' },
    lg: { mobile: 'lg:hidden', desktop: 'hidden lg:block' },
} as const;

export function DataTable<TData, TValue>({
    columns,
    data,
    searchPlaceholder = 'Cari...',
    toolbar,
    emptyMessage = 'Tidak ada data.',
    searchWidth = 'max-w-sm',
    desktopBreakpoint = 'md',
    rowId,
    initialExpandedRowId = null,
    mobileHeader,
    renderMobileRow,
    renderExpandedRow,
}: DataTableProps<TData, TValue>) {
    const [sorting, setSorting] = useState<SortingState>([]);
    const [globalFilter, setGlobalFilter] = useState('');
    const [expandedRowId, setExpandedRowId] = useState<string | null>(initialExpandedRowId);
    const pageSize = 10;
    const expandedRowIndex = initialExpandedRowId && rowId ? data.findIndex((item) => rowId(item) === initialExpandedRowId) : -1;
    const initialPageIndex = expandedRowIndex >= 0 ? Math.floor(expandedRowIndex / pageSize) : 0;
    const visibility = responsiveVisibility[desktopBreakpoint];

    const table = useReactTable({
        data,
        columns,
        state: { sorting, globalFilter },
        onSortingChange: setSorting,
        onGlobalFilterChange: setGlobalFilter,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        getRowId: rowId,
        initialState: { pagination: { pageIndex: initialPageIndex, pageSize } },
    });

    return (
        <div>
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <Input
                    placeholder={searchPlaceholder}
                    value={globalFilter}
                    onChange={(e) => {
                        setGlobalFilter(e.target.value);
                        setExpandedRowId(null);
                    }}
                    className={`${searchWidth} border-gray-200 bg-white shadow-sm`}
                />
                {toolbar}
            </div>

            {renderMobileRow && (
                <div
                    className={`overflow-hidden rounded-2xl bg-white shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)] ${visibility.mobile}`}
                >
                    {mobileHeader}
                    <div className="divide-y divide-gray-100">
                        {table.getRowModel().rows.length ? (
                            table.getRowModel().rows.map((row) => (
                                <div key={row.id}>
                                    {renderMobileRow(row.original, {
                                        expanded: expandedRowId === row.id,
                                        toggle: () => setExpandedRowId((current) => (current === row.id ? null : row.id)),
                                    })}
                                </div>
                            ))
                        ) : (
                            <p className="px-5 py-10 text-center text-sm text-gray-500">{emptyMessage}</p>
                        )}
                    </div>
                </div>
            )}

            <div
                className={`overflow-hidden rounded-2xl bg-white shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)] ${renderMobileRow ? visibility.desktop : ''}`}
            >
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id} className="bg-[#0A3981] hover:bg-[#0A3981]">
                                {renderExpandedRow && <TableHead className="w-12" />}
                                {headerGroup.headers.map((header) => (
                                    <TableHead key={header.id} className={alignmentClass[header.column.columnDef.meta?.align ?? 'left']}>
                                        {header.isPlaceholder ? null : header.column.getCanSort() ? (
                                            <button
                                                className={`flex items-center gap-1 text-xs font-bold tracking-wide text-white uppercase hover:text-[#D4EBF8] ${headerAlignmentClass[header.column.columnDef.meta?.align ?? 'left']}`}
                                                onClick={header.column.getToggleSortingHandler()}
                                            >
                                                {flexRender(header.column.columnDef.header, header.getContext())}
                                                {header.column.getIsSorted() === 'asc' ? (
                                                    <ChevronUp className="h-3.5 w-3.5" />
                                                ) : header.column.getIsSorted() === 'desc' ? (
                                                    <ChevronDown className="h-3.5 w-3.5" />
                                                ) : (
                                                    <ChevronsUpDown className="h-3.5 w-3.5 opacity-60" />
                                                )}
                                            </button>
                                        ) : (
                                            <span
                                                className={`block text-xs font-bold tracking-wide text-white uppercase ${alignmentClass[header.column.columnDef.meta?.align ?? 'left']}`}
                                            >
                                                {flexRender(header.column.columnDef.header, header.getContext())}
                                            </span>
                                        )}
                                    </TableHead>
                                ))}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {table.getRowModel().rows.length ? (
                            table.getRowModel().rows.map((row) => {
                                const expanded = expandedRowId === row.id;
                                const toggle = () => setExpandedRowId((current) => (current === row.id ? null : row.id));

                                return (
                                    <Fragment key={row.id}>
                                        <TableRow className={expanded ? 'bg-[#F8FBFE]' : 'hover:bg-[#F5F9FD]/50'}>
                                            {renderExpandedRow && (
                                                <TableCell className="w-12 pr-0">
                                                    <button
                                                        type="button"
                                                        onClick={toggle}
                                                        aria-expanded={expanded}
                                                        aria-label={`${expanded ? 'Tutup' : 'Buka'} detail baris`}
                                                        className={`flex h-7 w-7 items-center justify-center rounded-full transition-colors ${
                                                            expanded ? 'bg-[#0A3981] text-white' : 'bg-[#E8EEF7] text-[#1F509A] hover:bg-[#D4EBF8]'
                                                        }`}
                                                    >
                                                        {expanded ? (
                                                            <Minus className="h-4 w-4" aria-hidden="true" />
                                                        ) : (
                                                            <Plus className="h-4 w-4" aria-hidden="true" />
                                                        )}
                                                    </button>
                                                </TableCell>
                                            )}
                                            {row.getVisibleCells().map((cell) => (
                                                <TableCell key={cell.id} className={alignmentClass[cell.column.columnDef.meta?.align ?? 'left']}>
                                                    {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                                </TableCell>
                                            ))}
                                        </TableRow>
                                        {renderExpandedRow && expanded && (
                                            <TableRow className="hover:bg-transparent">
                                                <TableCell colSpan={columns.length + 1} className="bg-[#F5F9FD]/30 p-5">
                                                    {renderExpandedRow(row.original, { expanded, toggle })}
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </Fragment>
                                );
                            })
                        ) : (
                            <TableRow>
                                <TableCell colSpan={columns.length + (renderExpandedRow ? 1 : 0)} className="h-24 text-center text-sm text-gray-500">
                                    {emptyMessage}
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-xs text-gray-500">
                    Halaman {table.getState().pagination.pageIndex + 1} dari {table.getPageCount() || 1}
                </p>
                <div className="grid grid-cols-2 gap-2 sm:flex">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => table.previousPage()}
                        disabled={!table.getCanPreviousPage()}
                        className="border-[#1F509A]/40 text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
                    >
                        Sebelumnya
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => table.nextPage()}
                        disabled={!table.getCanNextPage()}
                        className="border-[#1F509A]/40 text-[#1F509A] hover:bg-[#F5F9FD] hover:text-[#0A3981]"
                    >
                        Selanjutnya
                    </Button>
                </div>
            </div>
        </div>
    );
}
