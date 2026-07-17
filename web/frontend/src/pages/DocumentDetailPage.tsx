import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Download, Eye, RefreshCw, X, FileText, Pencil, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { api, getErrorMessage } from '../api/client'
import { useAuth } from '../auth/AuthContext'
import { Modal } from '../components/Modal'
import { StatusBadge } from '../components/StatusBadge'
import type { MedicalDocument, MedicalDocumentFile, Status } from '../types'

const ALL_STATUSES: Status[] = ['PENDIENTE', 'RECEPCIONADO', 'REGISTRADO', 'RECHAZADO']

function allowedStatusTransitions(current: Status, isAdmin: boolean): Status[] {
  if (isAdmin) {
    return ALL_STATUSES.filter((item) => item !== current)
  }

  return current === 'PENDIENTE'
    ? ['RECEPCIONADO', 'RECHAZADO']
    : current === 'RECEPCIONADO'
      ? ['REGISTRADO', 'RECHAZADO']
      : []
}

function isPreviewable(file: MedicalDocumentFile): boolean {
  const mime = file.mime_type ?? ''
  return mime.startsWith('image/') || mime === 'application/pdf'
}

function getPreviewUrl(fileId: number): string {
  const baseUrl = (api.defaults.baseURL ?? '').replace(/\/$/, '')
  const token = localStorage.getItem('docssalud_token')
  return `${baseUrl}/medical-documents/files/${fileId}/preview?token=${token}`
}

export function DocumentDetailPage() {
  const { id } = useParams()
  const { can, user } = useAuth()
  const navigate = useNavigate()
  const [statusOpen, setStatusOpen] = useState(false)
  const [observationOpen, setObservationOpen] = useState(false)
  const [deleteOpen, setDeleteOpen] = useState(false)
  const [previewFile, setPreviewFile] = useState<MedicalDocumentFile | null>(null)
  const document = useQuery({
    queryKey: ['document', id],
    queryFn: async () => (await api.get<MedicalDocument>(`/medical-documents/${id}`)).data,
    enabled: Boolean(id),
  })

  if (document.isLoading) return <div>Cargando...</div>
  if (!document.data) return <div>No encontrado</div>

  const data = document.data
  const isAdmin = user?.role?.code === 'ADMIN'
  const canChangeStatus = can('documents.updateStatus') && allowedStatusTransitions(data.status, isAdmin).length > 0

  async function downloadFile(fileId: number, fileName: string) {
    const response = await api.get(`/medical-documents/files/${fileId}/download`, { responseType: 'blob' })
    const url = URL.createObjectURL(response.data)
    const anchor = window.document.createElement('a')
    anchor.href = url
    anchor.download = fileName
    anchor.click()
    URL.revokeObjectURL(url)
  }

  const delivererPhoto = data.files?.find((f) => f.file_type === 'DELIVERER_PHOTO')
  const medicalFile = data.files?.find((f) => f.file_type === 'MEDICAL_DOCUMENT')
  const annexes = data.files?.filter((f) => f.file_type === 'ANNEX') ?? []

  return (
    <div>
      <div className="page-title">
        <div>
          <h1>Documento #{data.id}</h1>
          <div className="muted">{data.type?.name} - DNI {data.worker?.dni}</div>
        </div>
        <div className="header-actions">
          <StatusBadge status={data.status} />
          {isAdmin ? (
            <button className="btn secondary" type="button" onClick={() => setObservationOpen(true)}>
              <Pencil size={18} />
              Editar observacion
            </button>
          ) : null}
          {canChangeStatus ? (
            <button className="btn secondary" type="button" onClick={() => setStatusOpen(true)}>
              <RefreshCw size={18} />
              Cambiar estado
            </button>
          ) : null}
          {isAdmin ? (
            <button className="btn danger" type="button" onClick={() => setDeleteOpen(true)}>
              <Trash2 size={18} />
              Eliminar
            </button>
          ) : null}
        </div>
      </div>

      <div className="grid two">
        <section className="card grid">
          <h2>Datos del trabajador</h2>
          <div>
            <strong>{data.worker?.first_name} {data.worker?.last_name}</strong>
            <div className="muted">{data.worker?.position ?? 'Sin cargo'}</div>
          </div>
          <div className="muted">{data.worker?.management?.name} / {data.worker?.sector?.name}</div>
        </section>
        <section className="card grid">
          <h2>Entrega</h2>
          <div><strong>{data.deliverer_name}</strong></div>
          <div className="muted">{data.deliveryRelation?.name ?? data.delivery_relation?.name}</div>
          <div className="muted">Contacto: {data.contact_number}</div>
          <div>
            <strong>Observacion</strong>
            <div className="muted">{data.observation || 'Sin observacion registrada'}</div>
          </div>
        </section>
      </div>

      {/* File previews */}
      <div className="card" style={{ marginTop: 24 }}>
        <h2 style={{ marginBottom: 16 }}>Documentos adjuntos</h2>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))', gap: 16 }}>
          {delivererPhoto ? (
            <FilePreviewCard
              file={delivererPhoto}
              label="Foto de quien entrega"
              onPreview={() => setPreviewFile(delivererPhoto)}
              onDownload={() => downloadFile(delivererPhoto.id, delivererPhoto.original_name)}
            />
          ) : null}
          {medicalFile ? (
            <FilePreviewCard
              file={medicalFile}
              label={data.type?.name ?? 'Documento médico'}
              onPreview={() => setPreviewFile(medicalFile)}
              onDownload={() => downloadFile(medicalFile.id, medicalFile.original_name)}
            />
          ) : null}
          {annexes.map((annex, i) => (
            <FilePreviewCard
              key={annex.id}
              file={annex}
              label={`Anexo ${i + 1}`}
              onPreview={() => setPreviewFile(annex)}
              onDownload={() => downloadFile(annex.id, annex.original_name)}
            />
          ))}
        </div>
      </div>

      {/* History */}
      <div className="card" style={{ marginTop: 24 }}>
        <h2 style={{ marginBottom: 16 }}>Historial</h2>
        <div className="timeline">
          {data.history?.map((item) => (
            <div className="timeline-item" key={item.id}>
              <strong>{item.from_status ?? 'INICIO'} {'→'} {item.to_status}</strong>
              <div className="muted">{item.user?.name} - {new Date(item.created_at).toLocaleString()}</div>
              {item.observation ? <div>{item.observation}</div> : null}
            </div>
          ))}
        </div>
      </div>

      {/* Preview modal */}
      {previewFile ? (
        <FilePreviewModal
          file={previewFile}
          onClose={() => setPreviewFile(null)}
          onDownload={() => downloadFile(previewFile.id, previewFile.original_name)}
        />
      ) : null}
      {statusOpen ? <DetailStatusModal document={data} onClose={() => setStatusOpen(false)} /> : null}
      {observationOpen ? <ObservationModal document={data} onClose={() => setObservationOpen(false)} /> : null}
      {deleteOpen ? <DeleteDocumentModal document={data} onClose={() => setDeleteOpen(false)} onDeleted={() => navigate('/documents')} /> : null}
    </div>
  )
}

function FilePreviewCard({
  file,
  label,
  onPreview,
  onDownload,
}: {
  file: MedicalDocumentFile
  label: string
  onPreview: () => void
  onDownload: () => void
}) {
  const previewable = isPreviewable(file)
  const isImage = file.mime_type?.startsWith('image/')

  return (
    <div
      style={{
        border: '1px solid #e5e7eb',
        borderRadius: 12,
        overflow: 'hidden',
        background: '#fff',
      }}
    >
      {/* Thumbnail area */}
      <div
        onClick={previewable ? onPreview : onDownload}
        style={{
          height: 140,
          background: '#f9fafb',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          cursor: 'pointer',
          position: 'relative',
          overflow: 'hidden',
        }}
      >
        {isImage ? (
          <img
            src={getPreviewUrl(file.id)}
            alt={label}
            style={{ maxWidth: '100%', maxHeight: '100%', objectFit: 'cover', width: '100%', height: '100%' }}
            onError={(e) => {
              const target = e.target as HTMLImageElement
              target.style.display = 'none'
              target.parentElement!.innerHTML = '<div style="text-align:center;color:#9ca3af;">Sin vista previa</div>'
            }}
          />
        ) : (
          <FileText size={48} color="#9ca3af" />
        )}
        {/* Hover overlay */}
        <div
          style={{
            position: 'absolute',
            inset: 0,
            background: 'rgba(0,0,0,0.3)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            opacity: 0,
            transition: 'opacity 0.2s',
          }}
          onMouseEnter={(e) => { (e.currentTarget as HTMLElement).style.opacity = '1' }}
          onMouseLeave={(e) => { (e.currentTarget as HTMLElement).style.opacity = '0' }}
        >
          <Eye size={28} color="#fff" />
        </div>
      </div>
      {/* Info */}
      <div style={{ padding: '10px 12px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div style={{ minWidth: 0 }}>
          <div style={{ fontWeight: 600, fontSize: 13, color: '#111827', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
            {label}
          </div>
          <div style={{ fontSize: 11, color: '#6b7280', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
            {file.original_name}
          </div>
        </div>
        <button
          onClick={onDownload}
          title="Descargar"
          style={{ background: 'none', border: 'none', cursor: 'pointer', padding: 4, color: '#047857', flexShrink: 0 }}
        >
          <Download size={18} />
        </button>
      </div>
    </div>
  )
}

function FilePreviewModal({
  file,
  onClose,
  onDownload,
}: {
  file: MedicalDocumentFile
  onClose: () => void
  onDownload: () => void
}) {
  const url = getPreviewUrl(file.id)
  const isImage = file.mime_type?.startsWith('image/')
  const isPdf = file.mime_type === 'application/pdf'

  return (
    <div
      style={{
        position: 'fixed',
        inset: 0,
        zIndex: 1000,
        background: 'rgba(0,0,0,0.75)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        padding: 24,
      }}
      onClick={onClose}
    >
      <div
        style={{
          background: '#fff',
          borderRadius: 16,
          maxWidth: '90vw',
          maxHeight: '90vh',
          width: isPdf ? '80vw' : 'auto',
          height: isPdf ? '85vh' : 'auto',
          display: 'flex',
          flexDirection: 'column',
          overflow: 'hidden',
          boxShadow: '0 25px 50px rgba(0,0,0,0.25)',
        }}
        onClick={(e) => e.stopPropagation()}
      >
        {/* Header */}
        <div
          style={{
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
            padding: '12px 16px',
            borderBottom: '1px solid #e5e7eb',
          }}
        >
          <span style={{ fontWeight: 600, fontSize: 14, color: '#111827' }}>{file.original_name}</span>
          <div style={{ display: 'flex', gap: 8 }}>
            <button onClick={onDownload} style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#047857' }} title="Descargar">
              <Download size={20} />
            </button>
            <button onClick={onClose} style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#6b7280' }} title="Cerrar">
              <X size={20} />
            </button>
          </div>
        </div>
        {/* Content */}
        <div style={{ flex: 1, overflow: 'auto', display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#f9fafb' }}>
          {isImage ? (
            <img src={url} alt={file.original_name} style={{ maxWidth: '100%', maxHeight: '80vh', objectFit: 'contain' }} />
          ) : isPdf ? (
            <iframe src={url} style={{ width: '100%', height: '100%', border: 'none' }} title={file.original_name} />
          ) : (
            <div style={{ padding: 40, textAlign: 'center', color: '#6b7280' }}>
              <FileText size={64} color="#d1d5db" />
              <p style={{ marginTop: 16 }}>Vista previa no disponible para este tipo de archivo.</p>
              <button className="btn" onClick={onDownload} style={{ marginTop: 16 }}>
                <Download size={18} /> Descargar archivo
              </button>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

function DetailStatusModal({ document, onClose }: { document: MedicalDocument; onClose: () => void }) {
  const queryClient = useQueryClient()
  const { user } = useAuth()
  const isAdmin = user?.role?.code === 'ADMIN'
  const [status, setStatus] = useState<Status | ''>('')
  const [observation, setObservation] = useState('')
  const [error, setError] = useState('')
  const allowed = allowedStatusTransitions(document.status, isAdmin)
  const mutation = useMutation({
    mutationFn: async () => api.post(`/medical-documents/${document.id}/status`, { status, observation }),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['document', String(document.id)] }),
        queryClient.invalidateQueries({ queryKey: ['documents'] }),
      ])
      onClose()
    },
    onError: (mutationError) => setError(getErrorMessage(mutationError)),
  })

  return (
    <Modal title={`Cambio de estado - Documento #${document.id}`} onClose={onClose}>
      <div className="grid">
        <div className="field">
          <label>Estado</label>
          <select value={status} onChange={(event) => setStatus(event.target.value as Status)}>
            <option value="">Seleccione</option>
            {allowed.map((item) => (
              <option key={item} value={item}>{item}</option>
            ))}
          </select>
        </div>
        <div className="field">
          <label>Observación</label>
          <textarea value={observation} onChange={(event) => setObservation(event.target.value)} />
        </div>
        {error ? <div className="error">{error}</div> : null}
        <button className="btn" type="button" disabled={!status || mutation.isPending} onClick={() => mutation.mutate()}>
          Guardar
        </button>
      </div>
    </Modal>
  )
}

function ObservationModal({ document, onClose }: { document: MedicalDocument; onClose: () => void }) {
  const queryClient = useQueryClient()
  const [observation, setObservation] = useState(document.observation ?? '')
  const [error, setError] = useState('')

  const mutation = useMutation({
    mutationFn: async () => api.patch(`/medical-documents/${document.id}/observation`, { observation }),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['document', String(document.id)] }),
        queryClient.invalidateQueries({ queryKey: ['documents'] }),
      ])
      onClose()
    },
    onError: (mutationError) => setError(getErrorMessage(mutationError)),
  })

  return (
    <Modal title={`Editar observacion - Documento #${document.id}`} onClose={onClose}>
      <div className="grid">
        <div className="field">
          <label>Observacion</label>
          <textarea value={observation} onChange={(event) => setObservation(event.target.value)} />
        </div>
        {error ? <div className="error">{error}</div> : null}
        <button className="btn" type="button" disabled={mutation.isPending} onClick={() => mutation.mutate()}>
          Guardar observacion
        </button>
      </div>
    </Modal>
  )
}

function DeleteDocumentModal({
  document,
  onClose,
  onDeleted,
}: {
  document: MedicalDocument
  onClose: () => void
  onDeleted: () => void
}) {
  const queryClient = useQueryClient()
  const [error, setError] = useState('')
  const mutation = useMutation({
    mutationFn: async () => api.delete(`/medical-documents/${document.id}`),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['documents'] })
      onDeleted()
    },
    onError: (mutationError) => setError(getErrorMessage(mutationError)),
  })

  return (
    <Modal title={`Eliminar documento #${document.id}`} onClose={onClose}>
      <div className="grid">
        <p className="muted-text">Esta accion eliminara el registro del documento. Los maestros no seran modificados.</p>
        {error ? <div className="error">{error}</div> : null}
        <div className="header-actions" style={{ justifyContent: 'flex-end' }}>
          <button className="btn secondary" type="button" onClick={onClose}>Cancelar</button>
          <button className="btn danger" type="button" disabled={mutation.isPending} onClick={() => mutation.mutate()}>
            Eliminar
          </button>
        </div>
      </div>
    </Modal>
  )
}
