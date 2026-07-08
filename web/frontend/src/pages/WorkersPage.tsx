import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { ColumnDef } from '@tanstack/react-table'
import { ChevronLeft, ChevronRight, Edit, Plus, Trash2, Upload, MoreVertical } from 'lucide-react'
import { useMemo, useState, useRef, useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { Link } from 'react-router-dom'
import { api, getErrorMessage } from '../api/client'
import { useAuth } from '../auth/AuthContext'
import { DataTable } from '../components/DataTable'
import { FilterSelect } from '../components/FilterSelect'
import { Modal } from '../components/Modal'
import { SearchBar } from '../components/SearchBar'
import type { Catalogs, Paginated, Worker } from '../types'

type WorkerForm = Pick<Worker, 'dni' | 'first_name' | 'last_name' | 'email' | 'phone' | 'position'> & {
  management_id?: string
  sector_id?: string
}

function ActionMenu({ onEdit, onDelete }: { onEdit: () => void, onDelete: () => void }) {
  const [open, setOpen] = useState(false)
  const menuRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (menuRef.current && !menuRef.current.contains(event.target as Node)) {
        setOpen(false)
      }
    }
    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [])

  return (
    <div className="dropdown-container" ref={menuRef}>
      <button className="icon-btn secondary action-menu-trigger" type="button" onClick={() => setOpen(!open)} title="Acciones">
        <MoreVertical size={20} />
      </button>
      {open && (
        <div className="dropdown-menu action-menu">
          <button onClick={() => { setOpen(false); onEdit(); }}><Edit size={16} /> Editar</button>
          <button onClick={() => { setOpen(false); onDelete(); }} className="danger"><Trash2 size={16} /> Eliminar</button>
        </div>
      )}
    </div>
  )
}

export function WorkersPage() {
  const { can } = useAuth()
  const queryClient = useQueryClient()
  const [editing, setEditing] = useState<Worker | null>(null)
  const [deleting, setDeleting] = useState<Worker | null>(null)
  const [showImport, setShowImport] = useState(false)
  
  const [q, setQ] = useState('')
  const [managementId, setManagementId] = useState('')
  const [sectorId, setSectorId] = useState('')
  const [isActive, setIsActive] = useState('')
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(25)

  const catalogs = useQuery({
    queryKey: ['catalogs'],
    queryFn: async () => (await api.get<Catalogs>('/sync/catalogs')).data,
  })

  const workers = useQuery({
    queryKey: ['workers', q, managementId, sectorId, isActive, page, perPage],
    queryFn: async () => (await api.get<Paginated<Worker>>('/workers', {
      params: {
        per_page: perPage,
        page,
        q: q || undefined,
        management_id: managementId || undefined,
        sector_id: sectorId || undefined,
        is_active: isActive !== '' ? isActive : undefined,
      }
    })).data,
  })

  const deleteMutation = useMutation({
    mutationFn: async (id: number) => api.delete(`/workers/${id}`),
    onSuccess: () => {
      setDeleting(null)
      queryClient.invalidateQueries({ queryKey: ['workers'] })
    },
  })

  const columns = useMemo<ColumnDef<Worker>[]>(
    () => [
      { accessorKey: 'dni', header: 'DNI' },
      { header: 'Nombre Completo', cell: ({ row }) => `${row.original.first_name} ${row.original.last_name}` },
      { header: 'Gerencia', cell: ({ row }) => row.original.management?.name || '-' },
      { header: 'Sector', cell: ({ row }) => row.original.sector?.name || '-' },
      { accessorKey: 'position', header: 'Cargo' },
      {
        header: 'Estado',
        cell: ({ row }) => {
          const active = row.original.is_active !== false
          return <span className={`badge ${active ? 'ACTIVO' : 'INACTIVO'}`}>{active ? 'Activo' : 'Inactivo'}</span>
        }
      },
      {
        header: 'Acciones',
        cell: ({ row }) =>
          can('workers.manage') ? (
            <ActionMenu 
              onEdit={() => setEditing(row.original)}
              onDelete={() => setDeleting(row.original)}
            />
          ) : null,
      },
    ],
    [can, deleteMutation],
  )

  const meta = workers.data
  const from = meta?.total ? ((meta.current_page - 1) * perPage) + 1 : 0
  const to = meta?.total ? Math.min(meta.current_page * perPage, meta.total) : 0
  const lastPage = meta?.last_page ?? 1
  const currentPage = meta?.current_page ?? 1

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

  return (
    <div>
      <div className="breadcrumb">
        <Link to="/dashboard">Inicio</Link> &gt; <span>Trabajadores</span>
      </div>
      
      <div className="page-title" style={{ marginBottom: 24, marginTop: 8, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <h1 style={{ fontSize: 24 }}>Trabajadores</h1>
        
        {can('workers.manage') ? (
          <div style={{ display: 'flex', gap: 12 }}>
            <button className="btn secondary" type="button" onClick={() => setShowImport(true)} style={{ fontWeight: 600, color: '#4b5563' }}>
              <Upload size={18} /> Importar
            </button>
            <button className="btn" type="button" onClick={() => setEditing({} as Worker)}>
              <Plus size={18} /> Nuevo Trabajador
            </button>
          </div>
        ) : null}
      </div>

      <div className="card" style={{ padding: 0 }}>
        <div style={{ padding: '24px 24px 24px' }}>
          <div className="toolbar" style={{ margin: 0 }}>
            <div className="filters" style={{ flexWrap: 'nowrap' }}>
              <SearchBar 
                placeholder="Buscar por DNI o nombre..."
                value={q}
                onChange={(value) => { setQ(value); setPage(1) }}
                className="flex-1"
              />
              <div className="field" style={{ width: '160px', marginBottom: 0 }}>
                <FilterSelect 
                  value={managementId}
                  onChange={(value) => { setManagementId(value); setPage(1) }}
                  options={catalogs.data?.managements.map(m => ({ value: String(m.id), label: m.name })) || []}
                  placeholder="Gerencia"
                />
              </div>
              <div className="field" style={{ width: '160px', marginBottom: 0 }}>
                <FilterSelect 
                  value={sectorId}
                  onChange={(value) => { setSectorId(value); setPage(1) }}
                  options={catalogs.data?.sectors.map(s => ({ value: String(s.id), label: s.name })) || []}
                  placeholder="Sector"
                />
              </div>
              <div className="field" style={{ width: '140px', marginBottom: 0 }}>
                <FilterSelect 
                  value={isActive}
                  onChange={(value) => { setIsActive(value); setPage(1) }}
                  options={[
                    { value: '1', label: 'Activo' },
                    { value: '0', label: 'Inactivo' }
                  ]}
                  placeholder="Estado"
                />
              </div>
            </div>
          </div>
        </div>

        <DataTable data={workers.data?.data ?? []} columns={columns} />

        {meta?.total ? (
          <div className="table-footer">
            <span>Mostrando {from} a {to} de {meta.total} resultados</span>
            <div className="footer-controls">
              <label className="page-size-control">
                <span>Por página</span>
                <select
                  value={perPage}
                  onChange={(event) => {
                    setPerPage(Number(event.target.value))
                    setPage(1)
                  }}
                >
                  {[10, 25, 50, 100].map((value) => (
                    <option key={value} value={value}>{value}</option>
                  ))}
                </select>
              </label>
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
                    <span key={`dots-${idx}`} className="page-btn page-ellipsis">...</span>
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
          </div>
        ) : null}
      </div>
      
      {editing ? <WorkerModal worker={editing.id ? editing : null} onClose={() => setEditing(null)} /> : null}
      {deleting ? (
        <Modal title="Confirmar eliminacion" onClose={() => setDeleting(null)}>
          <p style={{ marginTop: 0 }}>
            Esta accion eliminara al trabajador <strong>{deleting.first_name} {deleting.last_name}</strong>.
          </p>
          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 12 }}>
            <button className="btn secondary" type="button" onClick={() => setDeleting(null)}>Cancelar</button>
            <button className="btn danger" type="button" onClick={() => deleteMutation.mutate(deleting.id)} disabled={deleteMutation.isPending}>
              <Trash2 size={18} /> Eliminar
            </button>
          </div>
        </Modal>
      ) : null}
      {showImport ? <ImportModal onClose={() => setShowImport(false)} /> : null}
    </div>
  )
}

function ImportModal({ onClose }: { onClose: () => void }) {
  const queryClient = useQueryClient()
  const [file, setFile] = useState<File | null>(null)
  const [error, setError] = useState('')
  const [downloading, setDownloading] = useState(false)
  
  const handleDownloadTemplate = async () => {
    try {
      setDownloading(true)
      const response = await api.get('/workers/import-template', { responseType: 'blob' })
      const url = window.URL.createObjectURL(new Blob([response.data]))
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', 'plantilla_trabajadores.xlsx')
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)
    } catch (e: any) {
      setError('Error al descargar la plantilla')
    } finally {
      setDownloading(false)
    }
  }

  const mutation = useMutation({
    mutationFn: async () => {
      if (!file) throw new Error('Seleccione un archivo Excel (.xlsx)')
      
      const formData = new FormData()
      formData.append('file', file)
      
      return api.post('/workers/import-excel', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['workers'] })
      onClose()
    },
    onError: (e: any) => setError(getErrorMessage(e))
  })

  return (
    <Modal title="Importar Trabajadores" onClose={onClose}>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
        <div>
          <p style={{ margin: '0 0 8px', color: '#4b5563', fontSize: 14 }}>
            Descarga la plantilla Excel (.xlsx) y llena los datos usando las listas desplegables para Gerencia y Sector. Luego, sube el archivo completo.
          </p>
          <button type="button" onClick={handleDownloadTemplate} disabled={downloading} className="btn ghost" style={{ color: '#047857', padding: '4px 0' }}>
            {downloading ? 'Descargando...' : 'Descargar plantilla .xlsx'}
          </button>
        </div>
        <div className="field">
          <label>Archivo Excel</label>
          <input type="file" accept=".xlsx, .xls" onChange={e => setFile(e.target.files?.[0] || null)} />
        </div>
        {error && <div className="error">{error}</div>}
        <button className="btn" disabled={!file || mutation.isPending} onClick={() => mutation.mutate()}>
          {mutation.isPending ? 'Procesando...' : 'Procesar Importación'}
        </button>
      </div>
    </Modal>
  )
}

function WorkerModal({ worker, onClose }: { worker: Worker | null; onClose: () => void }) {
  const queryClient = useQueryClient()
  const catalogs = useQuery({
    queryKey: ['catalogs'],
    queryFn: async () => (await api.get<Catalogs>('/sync/catalogs')).data,
  })
  const form = useForm<WorkerForm>({
    defaultValues: {
      dni: worker?.dni ?? '',
      first_name: worker?.first_name ?? '',
      last_name: worker?.last_name ?? '',
      email: worker?.email ?? '',
      phone: worker?.phone ?? '',
      position: worker?.position ?? '',
      management_id: worker?.management_id ? String(worker.management_id) : '',
      sector_id: worker?.sector_id ? String(worker.sector_id) : '',
    },
  })
  const mutation = useMutation({
    mutationFn: async (values: WorkerForm) => {
      const payload = {
        ...values,
        management_id: values.management_id || null,
        sector_id: values.sector_id || null,
        is_active: true,
      }
      return worker ? api.put(`/workers/${worker.id}`, payload) : api.post('/workers', payload)
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['workers'] })
      onClose()
    },
  })

  return (
    <Modal title={worker ? 'Editar trabajador' : 'Nuevo trabajador'} onClose={onClose}>
      <form className="grid" onSubmit={form.handleSubmit((values) => mutation.mutate(values))}>
        <div className="form-grid">
          <div className="field"><label>DNI</label><input {...form.register('dni', { required: true })} /></div>
          <div className="field"><label>Nombres</label><input {...form.register('first_name', { required: true })} /></div>
          <div className="field"><label>Apellidos</label><input {...form.register('last_name', { required: true })} /></div>
          <div className="field"><label>Cargo</label><input {...form.register('position')} /></div>
          <div className="field"><label>Correo</label><input {...form.register('email')} /></div>
          <div className="field"><label>Telefono</label><input {...form.register('phone')} /></div>
          <div className="field">
            <label>Gerencia</label>
            <select {...form.register('management_id')}>
              <option value="">Seleccione</option>
              {catalogs.data?.managements.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
            </select>
          </div>
          <div className="field">
            <label>Sector</label>
            <select {...form.register('sector_id')}>
              <option value="">Seleccione</option>
              {catalogs.data?.sectors.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
            </select>
          </div>
        </div>
        <button className="btn" type="submit" disabled={mutation.isPending}>Guardar</button>
      </form>
    </Modal>
  )
}
