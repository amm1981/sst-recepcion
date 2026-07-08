import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { api } from '../api/client'
import { StatusBadge } from '../components/StatusBadge'
import type { DocumentCounts, MedicalDocument, Paginated } from '../types'
import { ClipboardList, FileText, CheckCircle2, XCircle, FilePlus2, MoreVertical } from 'lucide-react'
import { useAuth } from '../auth/AuthContext'

export function DashboardPage() {
  const { user } = useAuth()
  const documents = useQuery({
    queryKey: ['documents', 'recent'],
    queryFn: async () => (await api.get<Paginated<MedicalDocument>>('/medical-documents?per_page=6')).data,
  })
  const countsQuery = useQuery({
    queryKey: ['medical-documents', 'counts'],
    queryFn: async () => (await api.get<DocumentCounts>('/medical-documents/counts')).data,
  })

  const rows = documents.data?.data ?? []
  const counts = countsQuery.data ?? { pending: 0, received: 0, registered: 0, rejected: 0 }

  return (
    <div>
      <div className="grid hero">
        <div className="card welcome-card">
          <div className="welcome-text">
            <h2>Hola, {user?.name?.split(' ')[0] || 'Usuario'}</h2>
            <p>Bienvenido al sistema de recepción de documentos médicos.</p>
          </div>
          <div className="welcome-ill">
            <svg width="120" height="100" viewBox="0 0 120 100" fill="none" xmlns="http://www.w3.org/2000/svg">
              <rect x="25" y="20" width="50" height="65" rx="8" fill="#e9f2ee" stroke="#047857" strokeWidth="2" transform="rotate(-5 25 20)" />
              <rect x="40" y="10" width="55" height="70" rx="8" fill="#ffffff" stroke="#047857" strokeWidth="2" />
              <line x1="50" y1="30" x2="85" y2="30" stroke="#047857" strokeWidth="2" strokeLinecap="round" />
              <line x1="50" y1="42" x2="75" y2="42" stroke="#047857" strokeWidth="2" strokeLinecap="round" />
              <rect x="70" y="55" width="35" height="35" rx="6" fill="#047857" />
              <path d="M87.5 64v17m-8.5-8.5h17" stroke="#fff" strokeWidth="2" strokeLinecap="round" />
            </svg>
          </div>
        </div>
        
        <div className="card solid-green">
          <div className="action-card">
            <div className="action-icon">
              <FilePlus2 size={28} strokeWidth={2.5} />
            </div>
            <div>
              <h2 style={{ fontSize: 20 }}>Nuevo Registro</h2>
              <p className="muted" style={{ margin: '6px 0 0', fontSize: 13, lineHeight: 1.4 }}>
                Registrar un nuevo<br />documento médico.
              </p>
            </div>
            <Link className="btn" to="/documents/new" style={{ position: 'absolute', opacity: 0, inset: 0 }}>Nuevo Registro</Link>
          </div>
        </div>
      </div>

      <div className="grid stats">
        <div className="card stat-card">
          <div className="stat-icon orange"><ClipboardList size={28} /></div>
          <div className="stat-info">
            <span className="stat-title">Pendientes</span>
            <span className="stat-value">{counts.pending}</span>
            <span className="stat-desc">Documentos</span>
          </div>
        </div>
        <div className="card stat-card">
          <div className="stat-icon blue"><FileText size={28} /></div>
          <div className="stat-info">
            <span className="stat-title">Recepcionados</span>
            <span className="stat-value">{counts.received}</span>
            <span className="stat-desc">Documentos</span>
          </div>
        </div>
        <div className="card stat-card">
          <div className="stat-icon green"><CheckCircle2 size={28} /></div>
          <div className="stat-info">
            <span className="stat-title">Registrados</span>
            <span className="stat-value">{counts.registered}</span>
            <span className="stat-desc">Documentos</span>
          </div>
        </div>
        <div className="card stat-card">
          <div className="stat-icon red"><XCircle size={28} /></div>
          <div className="stat-info">
            <span className="stat-title">Rechazados</span>
            <span className="stat-value">{counts.rejected}</span>
            <span className="stat-desc">Documentos</span>
          </div>
        </div>
      </div>

      <div className="table-card" style={{ marginTop: 24 }}>
        <div className="table-header">
          <h2>Documentos recientes</h2>
          <Link to="/documents" style={{ color: '#047857', fontWeight: 600, fontSize: 14 }}>Ver todos</Link>
        </div>
        <div className="table-wrap" style={{ marginTop: 16 }}>
          <table>
            <thead>
              <tr>
                <th>N° Documento</th>
                <th>Tipo de Documento</th>
                <th>Trabajador</th>
                <th>Fecha</th>
                <th>Estado</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {rows.slice(0, 6).map((doc) => (
                <tr key={doc.id}>
                  <td>2026-{String(doc.id).padStart(4, '0')}</td>
                  <td>{doc.type?.name}</td>
                  <td>{doc.worker?.first_name} {doc.worker?.last_name}</td>
                  <td>{new Date(doc.created_at).toLocaleString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</td>
                  <td><StatusBadge status={doc.status} /></td>
                  <td style={{ textAlign: 'right' }}>
                    <Link to={`/documents/${doc.id}`} style={{ color: '#9ca3af', padding: 8 }}><MoreVertical size={20} /></Link>
                  </td>
                </tr>
              ))}
              {rows.length === 0 && !documents.isLoading ? <tr><td colSpan={6} className="table-empty">Sin documentos recientes</td></tr> : null}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  )
}
