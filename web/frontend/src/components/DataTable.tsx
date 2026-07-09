import {
  flexRender,
  getCoreRowModel,
  useReactTable,
  type ColumnDef,
} from '@tanstack/react-table'

type DataTableProps<TData extends object> = {
  data: TData[]
  columns: ColumnDef<TData>[]
  emptyText?: string
  className?: string
}

export function DataTable<TData extends object>({ data, columns, emptyText = 'Sin registros', className }: DataTableProps<TData>) {
  const table = useReactTable({
    data,
    columns,
    getCoreRowModel: getCoreRowModel(),
  })

  const headerLabels = table.getLeafHeaders().map((header) => {
    const headerDef = header.column.columnDef.header
    return typeof headerDef === 'string' ? headerDef : header.column.id
  })

  return (
    <div className={className || "table-card"}>
      <div className="table-wrap">
        <table className="data-table">
          <thead>
            {table.getHeaderGroups().map((headerGroup) => (
              <tr key={headerGroup.id}>
                {headerGroup.headers.map((header) => (
                  <th key={header.id}>
                    {header.isPlaceholder ? null : flexRender(header.column.columnDef.header, header.getContext())}
                  </th>
                ))}
              </tr>
            ))}
          </thead>
          <tbody>
            {table.getRowModel().rows.map((row) => (
              <tr key={row.id}>
                {row.getVisibleCells().map((cell, index) => (
                  <td key={cell.id} data-label={headerLabels[index] ?? ''}>{flexRender(cell.column.columnDef.cell, cell.getContext())}</td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {data.length === 0 ? <div className="table-empty">{emptyText}</div> : null}
    </div>
  )
}
