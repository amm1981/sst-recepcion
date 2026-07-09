import { useQuery } from '@tanstack/react-query'
import { api } from '../api/client'
import { Link } from 'react-router-dom'
import { FilterSelect } from '../components/FilterSelect'
import { FileText, Clock, Inbox, CheckCircle2, XCircle, FileSpreadsheet, Upload } from 'lucide-react'
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Cell, LabelList, LineChart, Line } from 'recharts'
import { useMemo, useState } from 'react'

type ReportSummary = {
  total: number
  by_status: { status: string; total: number }[]
  by_type: { name: string; total: number; pendientes: number; recepcionados: number; registrados: number; rechazados: number }[]
  monthly: { month: string; total: number }[]
}

type Catalogs = {
  medical_document_types: { id: number; name: string }[]
  sectors: { id: number; name: string }[]
  managements: { id: number; name: string }[]
}

export function ReportsPage() {
  const [filters, setFilters] = useState({
    from: '',
    to: '',
    type_id: '',
    status: '',
    management_id: '',
    sector_id: ''
  })
  
  const catalogs = useQuery({
    queryKey: ['catalogs'],
    queryFn: async () => (await api.get<Catalogs>('/sync/catalogs')).data
  })

  const report = useQuery({
    queryKey: ['reports', filters],
    queryFn: async () => {
      const params = new URLSearchParams()
      if (filters.from) params.append('from', filters.from)
      if (filters.to) params.append('to', filters.to)
      if (filters.type_id) params.append('type_id', filters.type_id)
      if (filters.status) params.append('status', filters.status)
      if (filters.management_id) params.append('management_id', filters.management_id)
      if (filters.sector_id) params.append('sector_id', filters.sector_id)
      return (await api.get<ReportSummary>(`/reports/summary?${params.toString()}`)).data
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

  const handleGenerate = () => {
    report.refetch()
  }

  const handleExportPdf = async () => {
    try {
      const params = buildReportParams(filters)
      const response = await api.get(`/reports/export/pdf?${params.toString()}`, { responseType: 'blob' })
      const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }))
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', 'reporte_documentos.pdf')
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)
    } catch (e) {
      console.error('Error exporting pdf', e)
    }
  }

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

  const monthlyData = useMemo(
    () => report.data?.monthly.map(item => ({
      month: item.month,
      total: Number(item.total),
    })) ?? [],
    [report.data],
  )

  return (
    <div>
      <div className="breadcrumb">
        <Link to="/dashboard">Inicio</Link> &gt; <span>Reportes</span>
      </div>
      
      <div className="page-title" style={{ marginBottom: 24, marginTop: 8 }}>
        <h1 style={{ fontSize: 24 }}>Reportes</h1>
      </div>

      <div className="report-filters">
        <div className="report-filters-row">
          <div className="field">
            <label>Fecha Desde</label>
            <input type="date" value={filters.from} onChange={e => setFilters({...filters, from: e.target.value})} />
          </div>
          <div className="field">
            <label>Fecha Hasta</label>
            <input type="date" value={filters.to} onChange={e => setFilters({...filters, to: e.target.value})} />
          </div>
          <div className="field">
            <label>Tipo de Documento</label>
            <FilterSelect 
              value={filters.type_id}
              onChange={val => setFilters({...filters, type_id: val})}
              options={catalogs.data?.medical_document_types?.map(t => ({ value: String(t.id), label: t.name })) || []}
              placeholder="Todos los tipos"
            />
          </div>
          <div className="field">
            <label>Estado</label>
            <FilterSelect 
              value={filters.status}
              onChange={val => setFilters({...filters, status: val})}
              options={[
                { value: 'PENDIENTE', label: 'Pendiente' },
                { value: 'RECEPCIONADO', label: 'Recepcionado' },
                { value: 'REGISTRADO', label: 'Registrado' },
                { value: 'RECHAZADO', label: 'Rechazado' }
              ]}
              placeholder="Todos los estados"
            />
          </div>
          <div className="field">
            <label>Gerencia</label>
            <FilterSelect 
              value={filters.management_id}
              onChange={val => setFilters({...filters, management_id: val})}
              options={catalogs.data?.managements?.map(t => ({ value: String(t.id), label: t.name })) || []}
              placeholder="Todas las gerencias"
            />
          </div>
          <div className="field">
            <label>Sector</label>
            <FilterSelect 
              value={filters.sector_id}
              onChange={val => setFilters({...filters, sector_id: val})}
              options={catalogs.data?.sectors?.map(t => ({ value: String(t.id), label: t.name })) || []}
              placeholder="Todos los sectores"
            />
          </div>
        </div>
        <div className="report-filters-actions">
          <button className="btn" onClick={handleGenerate}><Upload size={18} style={{ transform: 'rotate(180deg)' }} /> Generar Reporte</button>
          <button className="btn btn-export" onClick={handleExportExcel}><FileSpreadsheet size={18} /> Exportar Excel</button>
          <button className="btn btn-export" onClick={handleExportPdf}><FileText size={18} /> Exportar PDF</button>
        </div>
      </div>

      <div className="grid reports-stats">
        <div className="card stat-card" style={{ padding: '20px' }}>
          <div className="stat-icon" style={{ background: '#ecfdf5', color: '#047857' }}><FileText size={28} /></div>
          <div className="stat-info">
            <span className="stat-title">Total Documentos</span>
            <span className="stat-value">{report.data?.total ?? 0}</span>
            <span className="stat-desc">Todos los documentos</span>
          </div>
        </div>
        <div className="card stat-card" style={{ padding: '20px' }}>
          <div className="stat-icon orange"><Clock size={28} /></div>
          <div className="stat-info">
            <span className="stat-title">Pendientes</span>
            <span className="stat-value">{getStatusCount('PENDIENTE')}</span>
            <span className="stat-desc">Por recepcionar</span>
          </div>
        </div>
        <div className="card stat-card" style={{ padding: '20px' }}>
          <div className="stat-icon blue"><Inbox size={28} /></div>
          <div className="stat-info">
            <span className="stat-title">Recepcionados</span>
            <span className="stat-value">{getStatusCount('RECEPCIONADO')}</span>
            <span className="stat-desc">En revisión</span>
          </div>
        </div>
        <div className="card stat-card" style={{ padding: '20px' }}>
          <div className="stat-icon green"><CheckCircle2 size={28} /></div>
          <div className="stat-info">
            <span className="stat-title">Registrados</span>
            <span className="stat-value">{getStatusCount('REGISTRADO')}</span>
            <span className="stat-desc">Completados</span>
          </div>
        </div>
        <div className="card stat-card" style={{ padding: '20px' }}>
          <div className="stat-icon red"><XCircle size={28} /></div>
          <div className="stat-info">
            <span className="stat-title">Rechazados</span>
            <span className="stat-value">{getStatusCount('RECHAZADO')}</span>
            <span className="stat-desc">Devueltos</span>
          </div>
        </div>
      </div>

      <div className="grid hero" style={{ marginTop: 24 }}>
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

      <div className="grid hero" style={{ marginTop: 24 }}>
        <div className="report-panel">
          <h2>Resumen por Tipo</h2>
          <div className="report-panel-content" style={{ justifyContent: 'flex-start', overflow: 'auto' }}>
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
    </div>
  )
}

function buildReportParams(filters: {
  from: string
  to: string
  type_id: string
  status: string
  management_id: string
  sector_id: string
}) {
  const params = new URLSearchParams()
  if (filters.from) params.append('from', filters.from)
  if (filters.to) params.append('to', filters.to)
  if (filters.type_id) params.append('type_id', filters.type_id)
  if (filters.status) params.append('status', filters.status)
  if (filters.management_id) params.append('management_id', filters.management_id)
  if (filters.sector_id) params.append('sector_id', filters.sector_id)
  return params
}
