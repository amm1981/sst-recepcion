import { useQuery } from '@tanstack/react-query'
import { api } from '../api/client'
import { Link } from 'react-router-dom'
import { FilterSelect } from '../components/FilterSelect'
import { FileText, Clock, Inbox, CheckCircle2, XCircle, FileSpreadsheet, Eye, ChevronLeft, ChevronRight } from 'lucide-react'
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Cell, LabelList, LineChart, Line } from 'recharts'
import { useMemo, useState } from 'react'
import { Modal } from '../components/Modal'
import { SearchBar } from '../components/SearchBar'
import { StatusBadge } from '../components/StatusBadge'
import type { Paginated, RegisteredWorker, RegistrarSummary } from '../types'

type ReportSummary = {
  total: number
  by_status: { status: string; total: number }[]
  by_type: { name: string; total: number; pendientes: number; recepcionados: number; registrados: number; rechazados: number }[]
  monthly: { month: string; total: number }[]
  by_creator: { id: number; name: string; user?: string; email: string; total: number }[]
}

type Catalogs = {
  medical_document_types: { id: number; name: string }[]
  sectors: { id: number; name: string }[]
  managements: { id: number; name: string }[]
}

export function ReportsPage() {
  const [filters, setFilters] = useState({
    q: '',
    from: '',
    to: '',
    type_id: '',
    status: '',
    management_id: '',
    sector_id: '',
    created_by: ''
  })
  const [historyPage, setHistoryPage] = useState(1)
  const [selectedWorker, setSelectedWorker] = useState<RegisteredWorker | null>(null)
  const historyPerPage = 8

  const updateFilter = (key: keyof typeof filters, value: string) => {
    setFilters(current => ({ ...current, [key]: value }))
    setHistoryPage(1)
  }
  
  const catalogs = useQuery({
    queryKey: ['catalogs'],
    queryFn: async () => (await api.get<Catalogs>('/sync/catalogs')).data
  })

  const report = useQuery({
    queryKey: ['reports', filters],
    queryFn: async () => {
      const params = buildReportParams(filters)
      return (await api.get<ReportSummary>(`/reports/summary?${params.toString()}`)).data
    },
  })

  const registrars = useQuery({
    queryKey: ['report-registrars', filters.q, filters.from, filters.to, filters.type_id, filters.status, filters.management_id, filters.sector_id],
    queryFn: async () => {
      const params = buildReportParams({ ...filters, created_by: '' })
      return (await api.get<RegistrarSummary[]>(`/reports/registrars?${params.toString()}`)).data
    },
  })

  const workersHistory = useQuery({
    queryKey: ['report-workers-history', filters, historyPage],
    queryFn: async () => {
      const params = buildReportParams(filters)
      params.set('page', String(historyPage))
      params.set('per_page', String(historyPerPage))
      return (await api.get<Paginated<RegisteredWorker>>(`/reports/workers-history?${params.toString()}`)).data
    },
  })

  const getStatusCount = (status: string) => {
    return report.data?.by_status.find(s => s.status === status)?.total ?? 0
  }

  const chartData = useMemo(() => {
    return [
      { name: 'Pendientes', total: getStatusCount('PENDIENTE'), color: '#ea580c' },
      { name: 'Recepcionados', total: getStatusCount('RECEPCIONADO'), color: '#2563eb' },
      { name: 'Registrados', total: getStatusCount('REGISTRADO'), color: '#16a34a' },
      { name: 'Rechazados', total: getStatusCount('RECHAZADO'), color: '#dc2626' },
    ]
  }, [report.data])

  const totals = useMemo(() => {
    if (!report.data?.by_type) return { pendientes: 0, recepcionados: 0, registrados: 0, rechazados: 0 }
    return report.data.by_type.reduce((acc, curr) => ({
      pendientes: acc.pendientes + Number(curr.pendientes),
      recepcionados: acc.recepcionados + Number(curr.recepcionados),
      registrados: acc.registrados + Number(curr.registrados),
      rechazados: acc.rechazados + Number(curr.rechazados),
    }), { pendientes: 0, recepcionados: 0, registrados: 0, rechazados: 0 })
  }, [report.data])

  const handleExportExcel = async () => {
    try {
      const params = buildReportParams(filters)
      
      const response = await api.get(`/reports/export/excel?${params.toString()}`, { responseType: 'blob' })
      const url = window.URL.createObjectURL(new Blob([response.data]))
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', 'reporte_documentos.xlsx')
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)
    } catch (e) {
      console.error('Error exporting excel', e)
    }
  }

  const handleExportDetailExcel = async () => {
    try {
      const params = buildReportParams(filters)

      const response = await api.get(`/reports/export/detail-excel?${params.toString()}`, { responseType: 'blob' })
      const url = window.URL.createObjectURL(new Blob([response.data]))
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', 'detalle_documentos.xlsx')
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)
    } catch (e) {
      console.error('Error exporting detail excel', e)
    }
  }

  const monthlyData = useMemo(
    () => report.data?.monthly.map(item => ({
      month: item.month,
      total: Number(item.total),
    })) ?? [],
    [report.data],
  )

  const historyMeta = workersHistory.data
  const historyFrom = historyMeta?.total ? ((historyMeta.current_page - 1) * historyPerPage) + 1 : 0
  const historyTo = historyMeta?.total ? Math.min(historyMeta.current_page * historyPerPage, historyMeta.total) : 0
  const historyLastPage = historyMeta?.last_page ?? 1
  const historyCurrentPage = historyMeta?.current_page ?? 1

  return (
    <div className="reports-page">
      <div className="breadcrumb">
        <Link to="/dashboard">Inicio</Link> &gt; <span>Reportes</span>
      </div>
      
      <div className="page-title" style={{ marginBottom: 24, marginTop: 8 }}>
        <h1 style={{ fontSize: 24 }}>Reportes</h1>
      </div>

      <div className="report-filters">
        <div className="report-filters-row">
          <div className="field report-filter-search">
            <label>Buscar</label>
            <SearchBar
              placeholder="DNI, nombre, Nro o tipo..."
              value={filters.q}
              onChange={value => updateFilter('q', value)}
            />
          </div>
          <div className="field report-filter-date">
            <label>Fecha Desde</label>
            <input type="date" value={filters.from} onChange={e => updateFilter('from', e.target.value)} />
          </div>
          <div className="field report-filter-date">
            <label>Fecha Hasta</label>
            <input type="date" value={filters.to} onChange={e => updateFilter('to', e.target.value)} />
          </div>
          <div className="field report-filter-type">
            <label>Tipo de Documento</label>
            <FilterSelect 
              value={filters.type_id}
              onChange={val => updateFilter('type_id', val)}
              options={catalogs.data?.medical_document_types?.map(t => ({ value: String(t.id), label: t.name })) || []}
              placeholder="Todos los tipos"
            />
          </div>
          <div className="field report-filter-status">
            <label>Estado</label>
            <FilterSelect 
              value={filters.status}
              onChange={val => updateFilter('status', val)}
              options={[
                { value: 'PENDIENTE', label: 'Pendiente' },
                { value: 'RECEPCIONADO', label: 'Recepcionado' },
                { value: 'REGISTRADO', label: 'Registrado' },
                { value: 'RECHAZADO', label: 'Rechazado' }
              ]}
              placeholder="Todos los estados"
            />
          </div>
          <div className="field report-filter-registrar">
            <label>Usuario Registrador</label>
            <FilterSelect
              value={filters.created_by}
              onChange={val => updateFilter('created_by', val)}
              options={registrars.data?.map(user => ({ value: String(user.id), label: `${user.name} (${user.documents_count ?? 0})` })) || []}
              placeholder="Todos los usuarios"
            />
          </div>
          <div className="field report-filter-management">
            <label>Gerencia</label>
            <FilterSelect 
              value={filters.management_id}
              onChange={val => updateFilter('management_id', val)}
              options={catalogs.data?.managements?.map(t => ({ value: String(t.id), label: t.name })) || []}
              placeholder="Todas las gerencias"
            />
          </div>
          <div className="field report-filter-sector">
            <label>Sector</label>
            <FilterSelect 
              value={filters.sector_id}
              onChange={val => updateFilter('sector_id', val)}
              options={catalogs.data?.sectors?.map(t => ({ value: String(t.id), label: t.name })) || []}
              placeholder="Todos los sectores"
            />
          </div>
        </div>
        <div className="report-filters-actions">
          <button className="btn btn-export" onClick={handleExportExcel}><FileSpreadsheet size={18} /> Exportar Excel</button>
          <button className="btn btn-export" onClick={handleExportDetailExcel}><FileSpreadsheet size={18} /> Exportar Detalle</button>
        </div>
      </div>

      <div className="grid reports-stats">
        <div className="card stat-card report-stat-card">
          <div className="stat-icon" style={{ background: '#ecfdf5', color: '#047857' }}><FileText size={28} /></div>
          <div className="stat-info">
            <span className="stat-title">Total Documentos</span>
            <span className="stat-value">{report.data?.total ?? 0}</span>
            <span className="stat-desc">Todos los documentos</span>
          </div>
        </div>
        <div className="card stat-card report-stat-card">
          <div className="stat-icon orange"><Clock size={28} /></div>
          <div className="stat-info">
            <span className="stat-title">Pendientes</span>
            <span className="stat-value">{getStatusCount('PENDIENTE')}</span>
            <span className="stat-desc">Por recepcionar</span>
          </div>
        </div>
        <div className="card stat-card report-stat-card">
          <div className="stat-icon blue"><Inbox size={28} /></div>
          <div className="stat-info">
            <span className="stat-title">Recepcionados</span>
            <span className="stat-value">{getStatusCount('RECEPCIONADO')}</span>
            <span className="stat-desc">En revisión</span>
          </div>
        </div>
        <div className="card stat-card report-stat-card">
          <div className="stat-icon green"><CheckCircle2 size={28} /></div>
          <div className="stat-info">
            <span className="stat-title">Registrados</span>
            <span className="stat-value">{getStatusCount('REGISTRADO')}</span>
            <span className="stat-desc">Completados</span>
          </div>
        </div>
        <div className="card stat-card report-stat-card">
          <div className="stat-icon red"><XCircle size={28} /></div>
          <div className="stat-info">
            <span className="stat-title">Rechazados</span>
            <span className="stat-value">{getStatusCount('RECHAZADO')}</span>
            <span className="stat-desc">Devueltos</span>
          </div>
        </div>
      </div>

      <div className="report-charts-grid">
        <div className="report-panel">
          <h2>Documentos por Estado</h2>
          <div className="report-panel-content">
            <ResponsiveContainer width="100%" height={280}>
              <BarChart data={chartData} margin={{ top: 30, right: 30, left: 0, bottom: 5 }} barSize={48}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} />
                <XAxis dataKey="name" axisLine={false} tickLine={false} tick={{ fontSize: 13, fill: '#6b7280' }} dy={10} />
                <YAxis axisLine={false} tickLine={false} tick={{ fontSize: 13, fill: '#6b7280' }} />
                <Tooltip cursor={{fill: 'transparent'}} contentStyle={{ borderRadius: '8px', border: 'none', boxShadow: '0 4px 6px -1px rgba(0,0,0,0.1)' }} />
                <Bar dataKey="total" radius={[6, 6, 0, 0]}>
                  {chartData.map((entry, index) => (
                    <Cell key={`cell-${index}`} fill={entry.color} />
                  ))}
                  <LabelList dataKey="total" position="top" style={{ fontSize: 14, fontWeight: 700, fill: '#111827' }} dy={-10} />
                </Bar>
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>
        <div className="report-panel">
          <h2>Tendencia mensual</h2>
          <div className="report-panel-content">
            <ResponsiveContainer width="100%" height={280}>
              <LineChart data={monthlyData} margin={{ top: 30, right: 30, left: 0, bottom: 5 }}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} />
                <XAxis dataKey="month" axisLine={false} tickLine={false} tick={{ fontSize: 13, fill: '#6b7280' }} dy={10} />
                <YAxis axisLine={false} tickLine={false} tick={{ fontSize: 13, fill: '#6b7280' }} allowDecimals={false} />
                <Tooltip cursor={{ stroke: '#d1d5db' }} contentStyle={{ borderRadius: '8px', border: 'none', boxShadow: '0 4px 6px -1px rgba(0,0,0,0.1)' }} />
                <Line type="monotone" dataKey="total" stroke="#047857" strokeWidth={3} dot={{ r: 4, fill: '#047857' }} activeDot={{ r: 6 }} />
              </LineChart>
            </ResponsiveContainer>
          </div>
        </div>
      </div>

      <div className="report-section-grid">
        <div className="report-panel report-panel-wide">
          <h2>Resumen por Tipo</h2>
          <div className="report-panel-content report-table-wrap">
            <table className="report-table">
              <thead>
                <tr>
                  <th>Tipo de Documento</th>
                  <th>Total</th>
                  <th>Pendientes</th>
                  <th>Recepcionados</th>
                  <th>Registrados</th>
                  <th>Rechazados</th>
                </tr>
              </thead>
              <tbody>
                {report.data?.by_type.map(item => (
                  <tr key={item.name}>
                    <td>{item.name}</td>
                    <td className="num">{item.total}</td>
                    <td className="num text-orange">{item.pendientes || 0}</td>
                    <td className="num text-blue">{item.recepcionados || 0}</td>
                    <td className="num text-green">{item.registrados || 0}</td>
                    <td className="num text-red">{item.rechazados || 0}</td>
                  </tr>
                ))}
                {report.data?.by_type && report.data.by_type.length > 0 && (
                  <tr className="total-row">
                    <td>Total</td>
                    <td className="num">{report.data.total ?? 0}</td>
                    <td className="num text-orange">{totals.pendientes}</td>
                    <td className="num text-blue">{totals.recepcionados}</td>
                    <td className="num text-green">{totals.registrados}</td>
                    <td className="num text-red">{totals.rechazados}</td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div className="report-section-grid">
        <div className="report-panel report-panel-wide">
          <h2>Documentos por Usuario Registrador</h2>
          <div className="report-panel-content report-table-wrap">
            <table className="report-table">
              <thead>
                <tr>
                  <th>Usuario</th>
                  <th>Correo</th>
                  <th>Total documentos</th>
                </tr>
              </thead>
              <tbody>
                {report.data?.by_creator.map(item => (
                  <tr key={item.id}>
                    <td>{item.name}</td>
                    <td>{item.email}</td>
                    <td className="num">{item.total}</td>
                  </tr>
                ))}
                {report.data?.by_creator?.length === 0 ? (
                  <tr><td colSpan={3}>Sin resultados para los filtros seleccionados.</td></tr>
                ) : null}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div className="report-section-grid">
        <div className="report-panel report-panel-wide">
          <h2>Historial de Documentos por Trabajador</h2>
          <div className="report-panel-content report-table-wrap">
            <table className="report-table">
              <thead>
                <tr>
                  <th>Trabajador</th>
                  <th>Area/Gerencia</th>
                  <th>Sector</th>
                  <th>Documentos</th>
                  <th>Ultimo estado</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                {workersHistory.data?.data.map(worker => {
                  const latestDocument = worker.documents?.[0]
                  return (
                    <tr key={worker.id}>
                      <td>
                        <strong>{worker.first_name} {worker.last_name}</strong>
                        <div className="muted-text">DNI {worker.dni}</div>
                      </td>
                      <td>{worker.area ?? worker.management?.name ?? '-'}</td>
                      <td>{worker.sector?.name ?? '-'}</td>
                      <td className="num">{worker.documents_count}</td>
                      <td>{latestDocument ? <StatusBadge status={latestDocument.status} /> : '-'}</td>
                      <td>
                        <button className="icon-btn ghost" type="button" title="Ver historial" onClick={() => setSelectedWorker(worker)}>
                          <Eye size={18} />
                        </button>
                      </td>
                    </tr>
                  )
                })}
                {workersHistory.data?.data.length === 0 ? (
                  <tr><td colSpan={6}>Sin trabajadores con documentos para los filtros seleccionados.</td></tr>
                ) : null}
              </tbody>
            </table>

            {historyMeta?.total ? (
              <div className="table-footer">
                <span>Mostrando {historyFrom} a {historyTo} de {historyMeta.total} trabajadores</span>
                <div className="pagination">
                  <button className="page-btn" disabled={historyCurrentPage <= 1} onClick={() => setHistoryPage(historyCurrentPage - 1)}>
                    <ChevronLeft size={16} />
                  </button>
                  <span className="page-btn active">{historyCurrentPage}</span>
                  <button className="page-btn" disabled={historyCurrentPage >= historyLastPage} onClick={() => setHistoryPage(historyCurrentPage + 1)}>
                    <ChevronRight size={16} />
                  </button>
                </div>
              </div>
            ) : null}
          </div>
        </div>
      </div>

      {selectedWorker ? <WorkerHistoryModal worker={selectedWorker} onClose={() => setSelectedWorker(null)} /> : null}
    </div>
  )
}

function buildReportParams(filters: {
  q: string
  from: string
  to: string
  type_id: string
  status: string
  management_id: string
  sector_id: string
  created_by: string
}) {
  const params = new URLSearchParams()
  if (filters.q) params.append('q', filters.q)
  if (filters.from) params.append('from', filters.from)
  if (filters.to) params.append('to', filters.to)
  if (filters.type_id) params.append('type_id', filters.type_id)
  if (filters.status) params.append('status', filters.status)
  if (filters.management_id) params.append('management_id', filters.management_id)
  if (filters.sector_id) params.append('sector_id', filters.sector_id)
  if (filters.created_by) params.append('created_by', filters.created_by)
  return params
}

function WorkerHistoryModal({ worker, onClose }: { worker: RegisteredWorker; onClose: () => void }) {
  return (
    <Modal title={`${worker.first_name} ${worker.last_name}`} onClose={onClose}>
      <div className="grid two" style={{ marginBottom: 18 }}>
        <Info label="DNI" value={worker.dni} />
        <Info label="Cargo" value={worker.position} />
        <Info label="Area/Gerencia" value={worker.area ?? worker.management?.name} />
        <Info label="Sector" value={worker.sector?.name} />
      </div>

      <div className="timeline">
        {worker.documents.map(document => (
          <div className="file-row" key={document.id} style={{ alignItems: 'flex-start' }}>
            <div style={{ display: 'grid', gap: 8, minWidth: 0 }}>
              <strong>#{document.id} - {document.type?.name ?? 'Documento medico'}</strong>
              <span className="muted-text">{new Date(document.created_at).toLocaleString('es-PE')}</span>
              <span className="muted-text">Registrador: {document.creator?.name ?? '-'}</span>
              {document.history?.length ? (
                <div className="timeline" style={{ gap: 8, marginTop: 4 }}>
                  {document.history.map(item => (
                    <div className="timeline-item" key={item.id}>
                      <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
                        <StatusBadge status={item.to_status} />
                        <span className="muted-text">{new Date(item.created_at).toLocaleString('es-PE')}</span>
                        <span className="muted-text">{item.user?.name ?? '-'}</span>
                      </div>
                      {item.observation ? <div className="muted-text" style={{ marginTop: 4 }}>{item.observation}</div> : null}
                    </div>
                  ))}
                </div>
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
