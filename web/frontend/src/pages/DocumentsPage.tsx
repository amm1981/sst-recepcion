import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { ColumnDef } from '@tanstack/react-table'
import { Calendar, FileSpreadsheet, Upload, RefreshCw, ChevronLeft, ChevronRight, Eye } from 'lucide-react'
import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, getErrorMessage } from '../api/client'
import { useAuth } from '../auth/AuthContext'
import { DataTable } from '../components/DataTable'
import { Modal } from '../components/Modal'
import { SearchBar } from '../components/SearchBar'
import { StatusBadge } from '../components/StatusBadge'
import { FilterSelect } from '../components/FilterSelect'
import type { MedicalDocument, Paginated, RegistrarSummary, Status } from '../types'

const QUICK_DATE_RANGES = [
  { label: 'Ultimos 7 dias', days: 7 },
  { label: 'Ultimos 30 dias', days: 30 },
  { label: 'Ultimos 45 dias', days: 45 },
]

function formatDateInput(date: Date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function quickDateRange(days: number) {
  const to = new Date()
  const from = new Date()
  from.setDate(to.getDate() - Math.max(days - 1, 0))
  return { from: formatDateInput(from), to: formatDateInput(to) }
}

export function DocumentsPage() {
  const { can } = useAuth()
  const [status, setStatus] = useState<Status | 'TODOS'>('PENDIENTE')
  const [q, setQ] = useState('')
  const [page, setPage] = useState(1)
  const initialDateRange = useMemo(() => quickDateRange(30), [])
  const [quickRangeDays, setQuickRangeDays] = useState<number | 'custom'>(30)
  const [dateFrom, setDateFrom] = useState(initialDateRange.from)
  const [dateTo, setDateTo] = useState(initialDateRange.to)
  const [createdBy, setCreatedBy] = useState('')
  const [selected, setSelected] = useState<MedicalDocument | null>(null)
  const perPage = 15

  const registrars = useQuery({
    queryKey: ['document-registrars', dateFrom, dateTo],
    queryFn: async () => {
      const params = new URLSearchParams()
      if (dateFrom) params.set('date_from', dateFrom)
      if (dateTo) params.set('date_to', dateTo)
      return (await api.get<RegistrarSummary[]>(`/reports/registrars?${params.toString()}`)).data
    },
    enabled: can('reports.view'),
  })

  const documents = useQuery({
    queryKey: ['documents', status, q, page, dateFrom, dateTo, createdBy],
    queryFn: async () =>
      (
        await api.get<Paginated<MedicalDocument>>('/medical-documents', {
          params: {
            status: status === 'TODOS' ? undefined : status,
            q: q || undefined,
            per_page: perPage,
            page,
            date_from: dateFrom,
            date_to: dateTo || undefined,
            created_by: createdBy || undefined,
          },
        })
      ).data,
  })

  const columns = useMemo<ColumnDef<MedicalDocument>[]>(
    () => [
      { accessorKey: 'id', header: 'N° Documento', cell: (info) => `2026-${String(info.getValue<number>()).padStart(4, '0')}` },
      { header: 'Tipo de Documento', cell: ({ row }) => row.original.type?.name },
      { header: 'DNI', cell: ({ row }) => row.original.worker?.dni },
      {
        header: 'Nombre',
        cell: ({ row }) => `${row.original.worker?.first_name ?? ''} ${row.original.worker?.last_name ?? ''}`,
      },
      { header: 'Fecha', cell: ({ row }) => new Date(row.original.created_at).toLocaleString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) },
      { header: 'Estado', cell: ({ row }) => <StatusBadge status={row.original.status} /> },
      {
        header: 'Acciones',
        cell: ({ row }) => (
          <div className="header-actions" style={{ justifyContent: 'flex-end', gap: 4 }}>
            {can('documents.updateStatus') && ['PENDIENTE', 'RECEPCIONADO'].includes(row.original.status) ? (
              <button className="icon-btn ghost" type="button" onClick={() => setSelected(row.original)} title="Cambiar estado" style={{ color: '#047857' }}>
                <RefreshCw size={18} />
              </button>
            ) : null}
            <Link className="icon-btn ghost" to={`/documents/${row.original.id}`} title="Ver detalle" style={{ color: '#9ca3af' }}>
              <Eye size={20} />
            </Link>
          </div>
        ),
      },
    ],
    [can],
  )

  const meta = documents.data
  const from = meta?.total ? ((meta.current_page - 1) * perPage) + 1 : 0
  const to = meta?.total ? Math.min(meta.current_page * perPage, meta.total) : 0
  const lastPage = meta?.last_page ?? 1
  const currentPage = meta?.current_page ?? 1

  function handleExport() {
    const params = new URLSearchParams()
    if (status !== 'TODOS') params.set('status', status)
    if (q) params.set('q', q)
    if (dateFrom) params.set('date_from', dateFrom)
    if (dateTo) params.set('date_to', dateTo)
    if (createdBy) params.set('created_by', createdBy)
    api.get('/reports/export/excel', { params, responseType: 'blob' }).then((res) => {
      const url = URL.createObjectURL(res.data)
      const anchor = document.createElement('a')
      anchor.href = url
      anchor.download = `documentos_${new Date().toISOString().split('T')[0]}.xlsx`
      anchor.click()
      URL.revokeObjectURL(url)
    })
  }

  function handleExportDetail() {
    const params = new URLSearchParams()
    if (status !== 'TODOS') params.set('status', status)
    if (q) params.set('q', q)
    if (dateFrom) params.set('date_from', dateFrom)
    if (dateTo) params.set('date_to', dateTo)
    if (createdBy) params.set('created_by', createdBy)
    api.get('/reports/export/detail-excel', { params, responseType: 'blob' }).then((res) => {
      const url = URL.createObjectURL(res.data)
      const anchor = document.createElement('a')
      anchor.href = url
      anchor.download = `detalle_documentos_${new Date().toISOString().split('T')[0]}.xlsx`
      anchor.click()
      URL.revokeObjectURL(url)
    })
  }

  function getPaginationPages(): (number | '...')[] {
    const pages: (number | '...')[] = []
    if (lastPage <= 7) {
      for (let i = 1; i <= lastPage; i++) pages.push(i)
    } else {
      pages.push(1)
      if (currentPage > 3) pages.push('...')
      const start = Math.max(2, currentPage - 1)
      const end = Math.min(lastPage - 1, currentPage + 1)
      for (let i = start; i <= end; i++) pages.push(i)
      if (currentPage < lastPage - 2) pages.push('...')
      pages.push(lastPage)
    }
    return pages
  }

  function applyQuickRange(value: string) {
    if (value === 'custom') {
      setQuickRangeDays('custom')
      return
    }

    const days = Number(value)
    const range = quickDateRange(days)
    setQuickRangeDays(days)
    setDateFrom(range.from)
    setDateTo(range.to)
    setPage(1)
  }

  return (
    <div>
      <div className="page-title" style={{ marginBottom: 24 }}>
        <h1 style={{ fontSize: 24 }}>Documentos</h1>
      </div>

      <div className="card" style={{ padding: 0 }}>
        <div className="tabs" style={{ padding: '0 24px', paddingTop: 16 }}>
          <button className={`tab ${status === 'PENDIENTE' ? 'active' : ''}`} onClick={() => { setStatus('PENDIENTE'); setPage(1) }}>Pendientes</button>
          <button className={`tab ${status === 'RECEPCIONADO' ? 'active' : ''}`} onClick={() => { setStatus('RECEPCIONADO'); setPage(1) }}>Recepcionados</button>
          <button className={`tab ${status === 'REGISTRADO' ? 'active' : ''}`} onClick={() => { setStatus('REGISTRADO'); setPage(1) }}>Registrados</button>
          <button className={`tab ${status === 'RECHAZADO' ? 'active' : ''}`} onClick={() => { setStatus('RECHAZADO'); setPage(1) }}>Rechazados</button>
          <button className={`tab ${status === 'TODOS' ? 'active' : ''}`} onClick={() => { setStatus('TODOS'); setPage(1) }}>Todos</button>
          
          <div style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center' }}>
            <button className="btn ghost" style={{ color: '#047857', padding: '8px 12px' }} onClick={handleExport}>
              <Upload size={18} />
              Exportar
            </button>
            <button className="btn ghost" style={{ color: '#047857', padding: '8px 12px' }} onClick={handleExportDetail}>
              <FileSpreadsheet size={18} />
              Detalle
            </button>
          </div>
        </div>

        <div style={{ padding: '0 24px 24px' }}>
          <div className="toolbar documents-filter-toolbar">
            <div className="filters document-filters">
              <div className="field document-filter-search">
                <label>Buscar</label>
                <SearchBar 
                  placeholder="Buscar por N°, tipo, DNI o nombre..."
                  value={q}
                  onChange={(val) => { setQ(val); setPage(1) }}
                />
              </div>
              <div className="field document-filter-date-from">
                <label>Fecha inicio</label>
                <input
                  type="date"
                  value={dateFrom}
                  onChange={(event) => {
                    setDateFrom(event.target.value)
                    setQuickRangeDays('custom')
                    setPage(1)
                  }}
                />
              </div>
              <div className="field document-filter-date-to">
                <label>Fecha fin</label>
                <input
                  type="date"
                  value={dateTo}
                  onChange={(event) => {
                    setDateTo(event.target.value)
                    setQuickRangeDays('custom')
                    setPage(1)
                  }}
                />
              </div>
              <div className="field document-filter-range">
                <label>Rango automatico</label>
                <div className="select-with-leading-icon">
                  <Calendar size={18} color="#6b7280" style={{ position: 'absolute', left: 12, top: '50%', transform: 'translateY(-50%)' }} />
                  <select
                    value={quickRangeDays}
                    onChange={(e) => applyQuickRange(e.target.value)}
                    className="document-range-select"
                  >
                    <option value="custom">Personalizado</option>
                    {QUICK_DATE_RANGES.map((range) => (
                      <option key={range.days} value={range.days}>{range.label}</option>
                    ))}
                  </select>
                </div>
              </div>
              {can('reports.view') ? (
                <div className="field document-filter-registrar">
                  <label>Usuario registrador</label>
                  <FilterSelect
                    value={createdBy}
                    onChange={(value) => { setCreatedBy(value); setPage(1) }}
                    options={registrars.data?.map((user) => ({ value: String(user.id), label: `${user.name} (${user.documents_count ?? 0})` })) ?? []}
                    placeholder="Todos los usuarios"
                  />
                </div>
              ) : null}
            </div>
          </div>

          <DataTable className="" data={documents.data?.data ?? []} columns={columns} />
          
          {meta?.total ? (
            <div className="table-footer">
              <span>Mostrando {from} a {to} de {meta.total} resultados</span>
              <div className="pagination">
                <button
                  className="page-btn"
                  disabled={currentPage <= 1}
                  onClick={() => setPage(currentPage - 1)}
                >
                  <ChevronLeft size={16} />
                </button>
                {getPaginationPages().map((p, idx) =>
                  p === '...' ? (
                    <span key={`dots-${idx}`} className="page-btn" style={{ cursor: 'default', border: 'none' }}>…</span>
                  ) : (
                    <button
                      key={p}
                      className={`page-btn ${p === currentPage ? 'active' : ''}`}
                      onClick={() => setPage(p)}
                    >
                      {p}
                    </button>
                  )
                )}
                <button
                  className="page-btn"
                  disabled={currentPage >= lastPage}
                  onClick={() => setPage(currentPage + 1)}
                >
                  <ChevronRight size={16} />
                </button>
              </div>
            </div>
          ) : null}
        </div>
      </div>
      
      {selected ? <StatusModal document={selected} onClose={() => setSelected(null)} /> : null}
    </div>
  )
}

function StatusModal({ document, onClose }: { document: MedicalDocument; onClose: () => void }) {
  const queryClient = useQueryClient()
  const [nextStatus, setNextStatus] = useState<Status | ''>('')
  const [observation, setObservation] = useState('')
  const [error, setError] = useState('')
  const allowed: Status[] =
    document.status === 'PENDIENTE'
      ? ['RECEPCIONADO', 'RECHAZADO']
      : document.status === 'RECEPCIONADO'
        ? ['REGISTRADO', 'RECHAZADO']
        : []

  const mutation = useMutation({
    mutationFn: async () => api.post(`/medical-documents/${document.id}/status`, { status: nextStatus, observation }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['documents'] })
      onClose()
    },
    onError: (mutationError) => setError(getErrorMessage(mutationError)),
  })

  return (
    <Modal title={`Cambiar estado #${document.id}`} onClose={onClose}>
      <div className="grid">
        <div className="field">
          <label>Nuevo estado</label>
          <select value={nextStatus} onChange={(event) => setNextStatus(event.target.value as Status)}>
            <option value="">Seleccione</option>
            {allowed.map((item) => (
              <option key={item} value={item}>
                {item}
              </option>
            ))}
          </select>
        </div>
        <div className="field">
          <label>Observación</label>
          <textarea value={observation} onChange={(event) => setObservation(event.target.value)} />
        </div>
        {error ? <div className="error">{error}</div> : null}
        <button className="btn" type="button" disabled={!nextStatus || mutation.isPending} onClick={() => mutation.mutate()}>
          Guardar
        </button>
      </div>
    </Modal>
  )
}
