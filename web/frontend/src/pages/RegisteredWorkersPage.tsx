import { useQuery } from '@tanstack/react-query'
import type { ColumnDef } from '@tanstack/react-table'
import { ChevronLeft, ChevronRight, Eye, FileText } from 'lucide-react'
import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api/client'
import { DataTable } from '../components/DataTable'
import { FilterSelect } from '../components/FilterSelect'
import { Modal } from '../components/Modal'
import { SearchBar } from '../components/SearchBar'
import { StatusBadge } from '../components/StatusBadge'
import type { Catalogs, Paginated, RegisteredWorker, RegistrarSummary } from '../types'

export function RegisteredWorkersPage() {
  const [q, setQ] = useState('')
  const [page, setPage] = useState(1)
  const [createdBy, setCreatedBy] = useState('')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [managementId, setManagementId] = useState('')
  const [sectorId, setSectorId] = useState('')
  const [selected, setSelected] = useState<RegisteredWorker | null>(null)
  const perPage = 15

  const catalogs = useQuery({
    queryKey: ['catalogs'],
    queryFn: async () => (await api.get<Catalogs>('/sync/catalogs')).data,
  })

  const registrars = useQuery({
    queryKey: ['report-registrars', dateFrom, dateTo],
    queryFn: async () => {
      const params = new URLSearchParams()
      if (dateFrom) params.set('date_from', dateFrom)
      if (dateTo) params.set('date_to', dateTo)
      return (await api.get<RegistrarSummary[]>(`/reports/registrars?${params.toString()}`)).data
    },
  })

  const workers = useQuery({
    queryKey: ['registered-workers', q, page, createdBy, dateFrom, dateTo, managementId, sectorId],
    queryFn: async () =>
      (
        await api.get<Paginated<RegisteredWorker>>('/workers-registered-documents', {
          params: {
            q: q || undefined,
            page,
            per_page: perPage,
            created_by: createdBy || undefined,
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
            management_id: managementId || undefined,
            sector_id: sectorId || undefined,
          },
        })
      ).data,
  })

  const columns = useMemo<ColumnDef<RegisteredWorker>[]>(
    () => [
      {
        header: 'Trabajador',
        cell: ({ row }) => (
          <div style={{ display: 'grid', gap: 3 }}>
            <strong>{row.original.first_name} {row.original.last_name}</strong>
            <span className="muted-text">DNI {row.original.dni}</span>
          </div>
        ),
      },
      { header: 'Sector', cell: ({ row }) => row.original.sector?.name ?? '-' },
      { header: 'Area', cell: ({ row }) => row.original.area ?? row.original.management?.name ?? '-' },
      { header: 'Cargo', cell: ({ row }) => row.original.position ?? '-' },
      { header: 'Fundo', cell: ({ row }) => row.original.fundo ?? '-' },
      { header: 'Telefono', cell: ({ row }) => row.original.phone ?? '-' },
      { header: 'Documentos', cell: ({ row }) => <strong>{row.original.documents_count}</strong> },
      {
        header: 'Acciones',
        cell: ({ row }) => (
          <button className="icon-btn ghost" type="button" title="Ver documentos" onClick={() => setSelected(row.original)}>
            <Eye size={20} />
          </button>
        ),
      },
    ],
    [],
  )

  const meta = workers.data
  const from = meta?.total ? ((meta.current_page - 1) * perPage) + 1 : 0
  const to = meta?.total ? Math.min(meta.current_page * perPage, meta.total) : 0
  const lastPage = meta?.last_page ?? 1
  const currentPage = meta?.current_page ?? 1

  return (
    <div>
      <div className="breadcrumb">
        <Link to="/dashboard">Inicio</Link> &gt; <span>Trabajadores registrados</span>
      </div>

      <div className="page-title" style={{ marginBottom: 24, marginTop: 8 }}>
        <h1 style={{ fontSize: 24 }}>Trabajadores registrados</h1>
      </div>

      <div className="card" style={{ padding: 0 }}>
        <div style={{ padding: 24 }}>
          <div className="toolbar">
            <div className="filters">
              <SearchBar
                placeholder="Buscar por DNI, nombre o cargo..."
                value={q}
                onChange={(value) => { setQ(value); setPage(1) }}
              />
              <div className="field" style={{ minWidth: 180 }}>
                <label>Desde</label>
                <input type="date" value={dateFrom} onChange={(event) => { setDateFrom(event.target.value); setPage(1) }} />
              </div>
              <div className="field" style={{ minWidth: 180 }}>
                <label>Hasta</label>
                <input type="date" value={dateTo} onChange={(event) => { setDateTo(event.target.value); setPage(1) }} />
              </div>
              <div className="field" style={{ minWidth: 220 }}>
                <label>Usuario registrador</label>
                <FilterSelect
                  value={createdBy}
                  onChange={(value) => { setCreatedBy(value); setPage(1) }}
                  options={registrars.data?.map((user) => ({ value: String(user.id), label: `${user.name} (${user.documents_count ?? 0})` })) ?? []}
                  placeholder="Todos los usuarios"
                />
              </div>
              <div className="field" style={{ minWidth: 180 }}>
                <label>Area</label>
                <FilterSelect
                  value={managementId}
                  onChange={(value) => { setManagementId(value); setPage(1) }}
                  options={catalogs.data?.managements.map((item) => ({ value: String(item.id), label: item.name })) ?? []}
                  placeholder="Todas"
                />
              </div>
              <div className="field" style={{ minWidth: 180 }}>
                <label>Sector</label>
                <FilterSelect
                  value={sectorId}
                  onChange={(value) => { setSectorId(value); setPage(1) }}
                  options={catalogs.data?.sectors.map((item) => ({ value: String(item.id), label: item.name })) ?? []}
                  placeholder="Todos"
                />
              </div>
            </div>
          </div>

          <DataTable data={workers.data?.data ?? []} columns={columns} emptyText="Sin trabajadores con documentos registrados" />

          {meta?.total ? (
            <div className="table-footer">
              <span>Mostrando {from} a {to} de {meta.total} resultados</span>
              <div className="pagination">
                <button className="page-btn" disabled={currentPage <= 1} onClick={() => setPage(currentPage - 1)}>
                  <ChevronLeft size={16} />
                </button>
                <span className="page-btn active">{currentPage}</span>
                <button className="page-btn" disabled={currentPage >= lastPage} onClick={() => setPage(currentPage + 1)}>
                  <ChevronRight size={16} />
                </button>
              </div>
            </div>
          ) : null}
        </div>
      </div>

      {selected ? <WorkerDocumentsModal worker={selected} onClose={() => setSelected(null)} /> : null}
    </div>
  )
}

function WorkerDocumentsModal({ worker, onClose }: { worker: RegisteredWorker; onClose: () => void }) {
  return (
    <Modal title={`${worker.first_name} ${worker.last_name}`} onClose={onClose}>
      <div className="grid two" style={{ marginBottom: 18 }}>
        <Info label="DNI" value={worker.dni} />
        <Info label="Telefono" value={worker.phone} />
        <Info label="Correo" value={worker.email} />
        <Info label="Cargo" value={worker.position} />
        <Info label="Sector" value={worker.sector?.name} />
        <Info label="Area" value={worker.area ?? worker.management?.name} />
        <Info label="Fundo" value={worker.fundo} />
        <Info label="Fecha ingreso" value={worker.hire_date} />
      </div>

      <div className="timeline">
        {worker.documents.map((document) => (
          <div className="file-row" key={document.id} style={{ alignItems: 'flex-start' }}>
            <div style={{ display: 'grid', gap: 6 }}>
              <strong>#{document.id} - {document.type?.name ?? 'Documento medico'}</strong>
              <span className="muted-text">{new Date(document.created_at).toLocaleString('es-PE')}</span>
              <span className="muted-text">Registrador: {document.creator?.name ?? '-'}</span>
              <span className="muted-text">Entregante: {document.deliverer_name}</span>
              {document.observation ? <span className="muted-text">Observacion: {document.observation}</span> : null}
              {document.files?.length ? (
                <span className="muted-text"><FileText size={14} /> {document.files.map((file) => file.original_name).join(', ')}</span>
              ) : null}
            </div>
            <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
              <StatusBadge status={document.status} />
              <Link className="icon-btn ghost" title="Abrir detalle" to={`/documents/${document.id}`}>
                <Eye size={18} />
              </Link>
            </div>
          </div>
        ))}
      </div>
    </Modal>
  )
}

function Info({ label, value }: { label: string; value?: string | null }) {
  return (
    <div>
      <div className="muted-text">{label}</div>
      <strong>{value || '-'}</strong>
    </div>
  )
}
