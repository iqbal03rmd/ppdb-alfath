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
    type SortingState,
    useReactTable,
} from '@tanstack/react-table';
import { ChevronDown, ChevronsUpDown, ChevronUp } from 'lucide-react';
import { useState } from 'react';

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
}

export function DataTable<TData, TValue>({
    columns,
    data,
    searchPlaceholder = 'Cari...',
    toolbar,
    emptyMessage = 'Tidak ada data.',
    searchWidth = 'max-w-sm',
    mobileHeader,
    renderMobileRow,
}: DataTableProps<TData, TValue>) {
    const [sorting, setSorting] = useState<SortingState>([]);
    const [globalFilter, setGlobalFilter] = useState('');
    const [expandedMobileRow, setExpandedMobileRow] = useState<string | null>(null);

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
        initialState: { pagination: { pageSize: 10 } },
    });

    return (
        <div>
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <Input
                    placeholder={searchPlaceholder}
                    value={globalFilter}
                    onChange={(e) => setGlobalFilter(e.target.value)}
                    className={`${searchWidth} border-gray-200 bg-white shadow-sm`}
                />
                {toolbar}
            </div>

            {renderMobileRow && (
                <div className="overflow-hidden rounded-2xl bg-white shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)] md:hidden">
                    {mobileHeader}
                    <div className="divide-y divide-gray-100">
                        {table.getRowModel().rows.length ? (
                            table.getRowModel().rows.map((row) => (
                                <div key={row.id}>
                                    {renderMobileRow(row.original, {
                                        expanded: expandedMobileRow === row.id,
                                        toggle: () => setExpandedMobileRow((current) => (current === row.id ? null : row.id)),
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
                className={`overflow-hidden rounded-2xl bg-white shadow-[0_1px_3px_rgba(10,57,129,0.06),0_8px_24px_-8px_rgba(10,57,129,0.08)] ${renderMobileRow ? 'hidden md:block' : ''}`}
            >
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id} className="bg-[#0A3981] hover:bg-[#0A3981]">
                                {headerGroup.headers.map((header) => (
                                    <TableHead key={header.id}>
                                        {header.isPlaceholder ? null : header.column.getCanSort() ? (
                                            <button
                                                className="flex items-center gap-1 text-xs font-bold tracking-wide text-white uppercase hover:text-[#D4EBF8]"
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
                                            <span className="text-xs font-bold tracking-wide text-white uppercase">
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
                            table.getRowModel().rows.map((row) => (
                                <TableRow key={row.id} className="hover:bg-[#F5F9FD]/50">
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell key={cell.id}>{flexRender(cell.column.columnDef.cell, cell.getContext())}</TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell colSpan={columns.length} className="h-24 text-center text-sm text-gray-500">
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
