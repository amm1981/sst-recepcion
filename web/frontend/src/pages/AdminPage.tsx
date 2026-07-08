import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { ColumnDef } from '@tanstack/react-table'
import { Edit, Plus, Trash2, Users, ShieldCheck, FileText, Building2, Network, Truck, ChevronRight, History, RefreshCw, KeyRound } from 'lucide-react'
import { useMemo, useState, useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { api, getErrorMessage } from '../api/client'
import { DataTable } from '../components/DataTable'
import { Modal } from '../components/Modal'
import { SearchBar } from '../components/SearchBar'
import { Link } from 'react-router-dom'
import type { AuditLog, Paginated, Permission, Role, WorkerSyncLog } from '../types'

type AdminRecord = Record<string, unknown> & { id: number; user?: string; name?: string; code?: string; email?: string }

const resources = [
  { key: 'users', label: 'Usuarios', icon: Users, desc: 'Gestionar usuarios del sistema y sus accesos.' },
  { key: 'roles', label: 'Roles', icon: ShieldCheck, desc: 'Definir roles y responsabilidades del sistema.' },
  { key: 'permissions', label: 'Permisos', icon: KeyRound, desc: 'Gestionar permisos disponibles para los roles.' },
  { key: 'document-types', label: 'Tipos de Documento', icon: FileText, desc: 'Configurar los tipos de documentos disponibles.' },
  { key: 'managements', label: 'Gerencias', icon: Building2, desc: 'Gestionar las gerencias de la organización.' },
  { key: 'sectors', label: 'Sectores', icon: Network, desc: 'Gestionar los sectores de la organización.' },
  { key: 'delivery-relations', label: 'Relaciones Entrega', icon: Truck, desc: 'Configurar las relaciones de entrega de documentos.' },
  { key: 'audit-logs', label: 'Auditoria', icon: History, desc: 'Consultar acciones y trazabilidad del sistema.' },
  { key: 'worker-sync', label: 'Data Maestra', icon: RefreshCw, desc: 'Sincronizar trabajadores desde EmployeeFlow.' },
]

export function AdminPage() {
  const [resource, setResource] = useState(resources[0].key)
  const [editing, setEditing] = useState<AdminRecord | null>(null)
  const [deleting, setDeleting] = useState<AdminRecord | null>(null)
  
  const [searchQuery, setSearchQuery] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')

  useEffect(() => {
    const timer = setTimeout(() => setDebouncedSearch(searchQuery), 300)
    return () => clearTimeout(timer)
  }, [searchQuery])

  useEffect(() => {
    setSearchQuery('')
    setDebouncedSearch('')
  }, [resource])

  const queryClient = useQueryClient()
  const records = useQuery({
    queryKey: ['admin', resource, debouncedSearch],
    queryFn: async () => {
      const params = resource === 'audit-logs'
        ? `q=${encodeURIComponent(debouncedSearch)}`
        : `search=${encodeURIComponent(debouncedSearch)}`
      return (await api.get<Paginated<AdminRecord>>(`/admin/${resource}?${params}`)).data
    },
    enabled: resource !== 'worker-sync',
  })
  const deleteMutation = useMutation({
    mutationFn: async (id: number) => api.delete(`/admin/${resource}/${id}`),
    onSuccess: () => {
      setDeleting(null)
      queryClient.invalidateQueries({ queryKey: ['admin', resource] })
    },
  })
  const columns = useMemo<ColumnDef<AdminRecord>[]>(() => {
    const defaultActions = {
      header: 'Acciones',
      cell: ({ row }: any) => (
        <div className="header-actions">
          <button className="icon-btn secondary" type="button" onClick={() => setEditing(row.original)} title="Editar"><Edit size={17} /></button>
          <button className="icon-btn secondary" type="button" onClick={() => setDeleting(row.original)} title="Eliminar"><Trash2 size={17} /></button>
        </div>
      ),
    }

    if (resource === 'audit-logs') {
      return [
        { header: 'Fecha', cell: ({ row }) => new Date(String(row.original.created_at)).toLocaleString('es-PE') },
        { header: 'Usuario', cell: ({ row }) => (row.original.user as AuditLog['user'])?.name ?? 'Sistema' },
        { header: 'Accion', cell: ({ row }) => String(row.original.action ?? '') },
        { header: 'Entidad', cell: ({ row }) => String(row.original.entity ?? '') },
        { header: 'ID', cell: ({ row }) => String(row.original.entity_id ?? '-') },
        { header: 'IP', cell: ({ row }) => String(row.original.ip_address ?? '-') },
      ]
    }

    if (resource === 'users') {
      return [
        { 
          header: 'Nombre', 
          cell: ({ row }) => (
            <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
              <img src={`https://ui-avatars.com/api/?name=${row.original.name || 'U'}&background=e5e7eb&color=111827&bold=true`} alt="Avatar" className="avatar" style={{ width: 32, height: 32, borderRadius: '50%' }} />
              <span style={{ fontWeight: 500 }}>{row.original.name}</span>
            </div>
          )
        },
        { header: 'Usuario', accessorKey: 'user' },
        { header: 'Correo', accessorKey: 'email' },
        { 
          header: 'Rol', 
          cell: ({ row }) => {
            const roleCode = (row.original.role as any)?.code || 'N/A'
            const roleName = (row.original.role as any)?.name || 'N/A'
            let style = { padding: '4px 12px', borderRadius: '16px', fontSize: '12px', fontWeight: 600, backgroundColor: '#f3f4f6', color: '#374151' }
            
            if (roleCode === 'admin') {
              style = { ...style, backgroundColor: '#dcfce7', color: '#166534' }
            } else if (roleCode === 'rrhh') {
              style = { ...style, backgroundColor: '#dbeafe', color: '#1e40af' }
            } else if (roleCode === 'sst') {
              style = { ...style, backgroundColor: '#ffedd5', color: '#c2410c' }
            }
            
            return <span style={style}>{roleName}</span>
          }
        },
        { 
          header: 'Estado', 
          cell: ({ row }) => (
            <span style={{ backgroundColor: '#dcfce7', color: '#166534', padding: '4px 12px', borderRadius: '16px', fontSize: '12px', fontWeight: 600 }}>
              {row.original.is_active !== false ? 'Activo' : 'Inactivo'}
            </span>
          )
        },
        defaultActions,
      ]
    }
    
    return [
      { accessorKey: 'id', header: 'ID' },
      { header: 'Nombre', cell: ({ row }) => <span style={{ fontWeight: 500 }}>{String(row.original.name ?? row.original.email ?? '')}</span> },
      { header: 'Codigo', cell: ({ row }) => String(row.original.code ?? '') },
      { 
        header: 'Estado', 
        cell: ({ row }) => (
          <span style={{ backgroundColor: '#dcfce7', color: '#166534', padding: '4px 12px', borderRadius: '16px', fontSize: '12px', fontWeight: 600 }}>
            {row.original.is_active !== false ? 'Activo' : 'Inactivo'}
          </span>
        )
      },
      defaultActions,
    ]
  }, [resource, deleteMutation])

  const activeResource = resources.find(r => r.key === resource)

  return (
    <div>
      <div className="breadcrumb" style={{ marginBottom: '16px' }}>
        <Link to="/dashboard">Inicio</Link> &gt; <span>Administración</span>
      </div>
      <div className="page-title">
        <div>
          <h1>Administración</h1>
        </div>
      </div>
      
      <div className="admin-cards-grid">
        {resources.map((item) => {
          const Icon = item.icon
          const isActive = resource === item.key
          return (
            <div 
              key={item.key} 
              className={`admin-card ${isActive ? 'active' : ''}`} 
              onClick={() => setResource(item.key)}
            >
              <div className="admin-card-icon">
                <Icon size={24} />
              </div>
              <div className="admin-card-content">
                <div className="admin-card-title">{item.label}</div>
                <div className="admin-card-desc">{item.desc}</div>
              </div>
              <div className="admin-card-chevron">
                <ChevronRight size={20} />
              </div>
            </div>
          )
        })}
      </div>

      <div className="admin-toolbar">
        <h2>{activeResource?.label} del Sistema</h2>
        <div className="admin-toolbar-actions">
          <SearchBar 
            placeholder={`Buscar ${activeResource?.label.toLowerCase()}...`}
            value={searchQuery}
            onChange={setSearchQuery}
          />
          {!['audit-logs', 'worker-sync'].includes(resource) ? (
            <button className="btn" type="button" onClick={() => setEditing({ id: 0 })}>
              <Plus size={18} /> Nuevo {activeResource?.label.replace(/s$/, '').replace(/es$/, '')}
            </button>
          ) : null}
        </div>
      </div>

      {resource === 'worker-sync' ? (
        <WorkerSyncPanel />
      ) : (
        <DataTable data={records.data?.data ?? []} columns={columns} />
      )}
      {editing ? (
        <AdminModal resource={resource} record={editing.id ? editing : null} onClose={() => setEditing(null)} />
      ) : null}
      {deleting ? (
        <ConfirmDeleteModal
          record={deleting}
          resourceLabel={activeResource?.label ?? 'registro'}
          onCancel={() => setDeleting(null)}
          onConfirm={() => deleteMutation.mutate(deleting.id)}
          isPending={deleteMutation.isPending}
        />
      ) : null}
    </div>
  )
}

function WorkerSyncPanel() {
  const queryClient = useQueryClient()
  const latest = useQuery({
    queryKey: ['admin', 'worker-sync', 'latest'],
    queryFn: async () => (await api.get<WorkerSyncLog>('/admin/workers/sync-employee-flow/latest')).data,
    retry: false,
  })
  const syncMutation = useMutation({
    mutationFn: async () => (await api.post<{ message: string; log: WorkerSyncLog }>('/admin/workers/sync-employee-flow')).data,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['admin', 'worker-sync', 'latest'] })
      await queryClient.invalidateQueries({ queryKey: ['workers'] })
    },
  })

  const log = syncMutation.data?.log ?? latest.data

  return (
    <div className="table-card" style={{ padding: 24 }}>
      <div className="table-header" style={{ padding: 0, marginBottom: 16 }}>
        <div>
          <h2>Sincronizacion EmployeeFlow</h2>
          <p className="muted-text">Actualiza trabajadores, gerencias y sectores desde la fuente maestra.</p>
        </div>
        <button className="btn" type="button" onClick={() => syncMutation.mutate()} disabled={syncMutation.isPending}>
          <RefreshCw size={18} /> {syncMutation.isPending ? 'Sincronizando...' : 'Sincronizar ahora'}
        </button>
      </div>
      {syncMutation.error ? <div className="error">{getErrorMessage(syncMutation.error)}</div> : null}
      {log ? (
        <div className="grid reports-stats">
          <SyncMetric label="Estado" value={log.status} />
          <SyncMetric label="Recibidos" value={log.total_received} />
          <SyncMetric label="Creados" value={log.created_count} />
          <SyncMetric label="Actualizados" value={log.updated_count} />
          <SyncMetric label="Inactivados" value={log.inactive_count} />
          <SyncMetric label="Errores" value={log.error_count} />
        </div>
      ) : (
        <div className="table-empty">No hay sincronizaciones previas.</div>
      )}
      {log?.finished_at ? <p className="muted-text">Ultima finalizacion: {new Date(log.finished_at).toLocaleString('es-PE')}</p> : null}
      {log?.error_message ? <div className="error">{log.error_message}</div> : null}
    </div>
  )
}

function SyncMetric({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="card stat-card" style={{ padding: 16 }}>
      <div className="stat-info">
        <span className="stat-title">{label}</span>
        <span className="stat-value" style={{ fontSize: 24 }}>{value}</span>
      </div>
    </div>
  )
}

function ConfirmDeleteModal({
  record,
  resourceLabel,
  onCancel,
  onConfirm,
  isPending,
}: {
  record: AdminRecord
  resourceLabel: string
  onCancel: () => void
  onConfirm: () => void
  isPending: boolean
}) {
  return (
    <Modal title="Confirmar eliminacion" onClose={onCancel}>
      <p style={{ marginTop: 0 }}>
        Esta accion eliminara el registro de {resourceLabel.toLowerCase()} <strong>{record.name ?? record.email ?? record.code ?? record.id}</strong>.
      </p>
      <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 12 }}>
        <button className="btn secondary" type="button" onClick={onCancel}>Cancelar</button>
        <button className="btn danger" type="button" onClick={onConfirm} disabled={isPending}>
          <Trash2 size={18} /> Eliminar
        </button>
      </div>
    </Modal>
  )
}


function PermissionsMatrix({ form, permissions }: { form: any, permissions: Permission[] }) {
  const modulesSet = new Set<string>();
  const actionsSet = new Set<string>();
  
  const mapped = permissions.map(p => {
    const parts = (p.code || '').split('.');
    const mod = parts[0];
    const act = parts[1] || 'general';
    modulesSet.add(mod);
    actionsSet.add(act);
    return { ...p, module: mod, action: act };
  });

  const modules = Array.from(modulesSet);
  const actions = Array.from(actionsSet);

  const actionLabels: Record<string, string> = {
    view: 'Acceder',
    create: 'Insertar',
    updateStatus: 'Actualizar',
    manage: 'Gestionar',
  };

  const moduleLabels: Record<string, string> = {
    documents: 'Documentos',
    workers: 'Trabajadores',
    reports: 'Reportes',
    admin: 'Administración'
  };

  const getPermId = (mod: string, act: string) => {
    return mapped.find(p => p.module === mod && p.action === act)?.id?.toString();
  };

  const selectedIds = form.watch('permission_ids') as string[] || [];

  const handleToggle = (id: string, checked: boolean) => {
    let newIds = [...selectedIds];
    if (checked) {
      if (!newIds.includes(id)) newIds.push(id);
    } else {
      newIds = newIds.filter(x => x !== id);
    }
    form.setValue('permission_ids', newIds);
  };

  const handleRowToggle = (mod: string, checked: boolean) => {
    const idsInRow = actions.map(act => getPermId(mod, act)).filter(Boolean) as string[];
    let newIds = [...selectedIds];
    if (checked) {
      idsInRow.forEach(id => {
        if (!newIds.includes(id)) newIds.push(id);
      });
    } else {
      newIds = newIds.filter(id => !idsInRow.includes(id));
    }
    form.setValue('permission_ids', newIds);
  };

  const handleColToggle = (act: string, checked: boolean) => {
    const idsInCol = modules.map(mod => getPermId(mod, act)).filter(Boolean) as string[];
    let newIds = [...selectedIds];
    if (checked) {
      idsInCol.forEach(id => {
        if (!newIds.includes(id)) newIds.push(id);
      });
    } else {
      newIds = newIds.filter(id => !idsInCol.includes(id));
    }
    form.setValue('permission_ids', newIds);
  };

  return (
    <div className="permissions-matrix-container">
      <table className="permissions-matrix">
        <thead>
          <tr>
            <th>TODOS</th>
            {actions.map(act => {
              const idsInCol = modules.map(mod => getPermId(mod, act)).filter(Boolean) as string[];
              const isAllChecked = idsInCol.length > 0 && idsInCol.every(id => selectedIds.includes(id));
              return (
                <th key={act}>
                  <div className="toggle-header-label">
                    <span>{actionLabels[act] || act}</span>
                    <label className="toggle-switch">
                      <input 
                        type="checkbox" 
                        checked={isAllChecked} 
                        onChange={(e) => handleColToggle(act, e.target.checked)} 
                      />
                      <span className="toggle-slider"></span>
                    </label>
                  </div>
                </th>
              )
            })}
          </tr>
        </thead>
        <tbody>
          {modules.map(mod => {
            const idsInRow = actions.map(act => getPermId(mod, act)).filter(Boolean) as string[];
            const isAllChecked = idsInRow.length > 0 && idsInRow.every(id => selectedIds.includes(id));
            return (
              <tr key={mod}>
                <td>
                  <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <span>{moduleLabels[mod] || mod}</span>
                    <label className="toggle-switch">
                      <input 
                        type="checkbox" 
                        checked={isAllChecked} 
                        onChange={(e) => handleRowToggle(mod, e.target.checked)} 
                      />
                      <span className="toggle-slider"></span>
                    </label>
                  </div>
                </td>
                {actions.map(act => {
                  const id = getPermId(mod, act);
                  return (
                    <td key={act}>
                      {id ? (
                        <label className="toggle-switch" title={mapped.find(p => p.id?.toString() === id)?.name}>
                          <input 
                            type="checkbox" 
                            checked={selectedIds.includes(id)} 
                            onChange={(e) => handleToggle(id, e.target.checked)} 
                          />
                          <span className="toggle-slider"></span>
                        </label>
                      ) : (
                        <label className="toggle-switch disabled">
                          <input type="checkbox" disabled />
                          <span className="toggle-slider"></span>
                        </label>
                      )}
                    </td>
                  )
                })}
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}

function AdminModal({ resource, record, onClose }: { resource: string; record: AdminRecord | null; onClose: () => void }) {
  const queryClient = useQueryClient()
  const form = useForm<Record<string, string | boolean | string[] | number[]>>({
    defaultValues: {
      name: String(record?.name ?? ''),
      user: String(record?.user ?? ''),
      code: String(record?.code ?? ''),
      email: String(record?.email ?? ''),
      phone: String(record?.phone ?? ''),
      password: '',
      description: String(record?.description ?? ''),
      role_id: String(record?.role_id ?? ''),
      management_id: String(record?.management_id ?? ''),
      requires_detail: Boolean(record?.requires_detail ?? false),
      is_active: Boolean(record?.is_active ?? true),
      permission_ids: ((record as Role | null)?.permissions?.map((permission) => String(permission.id)) ?? []) as string[],
    },
  })
  const roles = useQuery({
    queryKey: ['admin', 'roles', 'select'],
    queryFn: async () => (await api.get<Paginated<Role>>('/admin/roles?per_page=100')).data.data,
    enabled: resource === 'users',
  })
  const permissions = useQuery({
    queryKey: ['admin', 'permissions', 'select'],
    queryFn: async () => (await api.get<Paginated<Permission>>('/admin/permissions?per_page=100')).data.data,
    enabled: resource === 'roles',
  })
  const mutation = useMutation({
    mutationFn: async (values: Record<string, string | boolean | string[] | number[]>) => {
      const payload = normalizeAdminPayload(resource, values)
      return record ? api.put(`/admin/${resource}/${record.id}`, payload) : api.post(`/admin/${resource}`, payload)
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['admin'] })
      onClose()
    },
  })

  return (
    <Modal title={record ? 'Editar registro' : 'Nuevo registro'} onClose={onClose}>
      <form className="grid" onSubmit={form.handleSubmit((values) => mutation.mutate(values))}>
        <div className="form-grid">
          {resource === 'users' ? (
            <>
              <TextField label="Nombre" name="name" form={form} />
              <TextField label="Usuario" name="user" form={form} />
              <TextField label="Correo" name="email" form={form} />
              <TextField label="Telefono" name="phone" form={form} />
              <TextField label="Password" name="password" form={form} type="password" />
              <div className="field">
                <label>Rol</label>
                <select {...form.register('role_id')}>
                  <option value="">Seleccione</option>
                  {roles.data?.map((role) => <option key={role.id} value={role.id}>{role.name}</option>)}
                </select>
              </div>
            </>
          ) : null}
          {resource !== 'users' ? (
            <>
              <TextField label="Nombre" name="name" form={form} />
              <TextField label="Codigo" name="code" form={form} />
            </>
          ) : null}
          {['roles', 'permissions'].includes(resource) ? <TextField label="Descripcion" name="description" form={form} /> : null}
          {resource === 'delivery-relations' ? (
            <label className="field">
              <span>Requiere detalle</span>
              <input type="checkbox" {...form.register('requires_detail')} />
            </label>
          ) : null}
          {resource === 'roles' ? (
            <div className="field full">
              <label>Permisos</label>
              <PermissionsMatrix form={form} permissions={permissions.data || []} />
            </div>
          ) : null}
        </div>
        <button className="btn" type="submit" disabled={mutation.isPending}>Guardar</button>
      </form>
    </Modal>
  )
}

function TextField({
  label,
  name,
  form,
  type = 'text',
}: {
  label: string
  name: string
  form: ReturnType<typeof useForm<Record<string, string | boolean | string[] | number[]>>>
  type?: string
}) {
  return (
    <div className="field">
      <label>{label}</label>
      <input type={type} {...form.register(name)} />
    </div>
  )
}

function normalizeAdminPayload(resource: string, values: Record<string, string | boolean | string[] | number[]>) {
  if (resource === 'users') {
    return {
      name: values.name,
      user: values.user,
      email: values.email,
      phone: values.phone || null,
      password: values.password || undefined,
      role_id: values.role_id,
      is_active: true,
    }
  }

  if (resource === 'roles') {
    return {
      name: values.name,
      code: values.code,
      description: values.description || null,
      permission_ids: (values.permission_ids as unknown[]).map(Number),
      is_active: true,
    }
  }

  if (resource === 'permissions') {
    return { name: values.name, code: values.code, description: values.description || null }
  }

  if (resource === 'sectors') {
    return { name: values.name, code: values.code, is_active: true }
  }

  if (resource === 'delivery-relations') {
    return { name: values.name, code: values.code, requires_detail: Boolean(values.requires_detail), is_active: true }
  }

  return { name: values.name, code: values.code, is_active: true }
}
