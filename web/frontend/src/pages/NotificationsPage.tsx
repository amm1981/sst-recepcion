import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { CheckCheck, Bell } from 'lucide-react'
import { Link } from 'react-router-dom'
import { api } from '../api/client'
import type { NotificationItem, Paginated } from '../types'

export function NotificationsPage() {
  const queryClient = useQueryClient()
  const notifications = useQuery({
    queryKey: ['notifications', 'page'],
    queryFn: async () => (await api.get<Paginated<NotificationItem>>('/notifications?per_page=100')).data,
  })

  const markAll = useMutation({
    mutationFn: async () => api.post('/notifications/read-all'),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['notifications'] })
    },
  })

  const rows = notifications.data?.data ?? []
  const unreadCount = rows.filter((item) => !item.read_at).length

  return (
    <div>
      <div className="breadcrumb">
        <Link to="/dashboard">Inicio</Link> &gt; <span>Notificaciones</span>
      </div>

      <div className="page-title" style={{ marginBottom: 24, marginTop: 8 }}>
        <div>
          <h1 style={{ fontSize: 24 }}>Notificaciones</h1>
          <p className="muted-text">{unreadCount} notificaciones sin leer</p>
        </div>
        <button className="btn secondary" type="button" onClick={() => markAll.mutate()} disabled={markAll.isPending || unreadCount === 0}>
          <CheckCheck size={18} /> Marcar todas como leidas
        </button>
      </div>

      <div className="table-card">
        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Estado</th>
                <th>Notificacion</th>
                <th>Fecha</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((item) => (
                <tr key={item.id}>
                  <td>
                    <span className={`badge ${item.read_at ? 'ACTIVO' : 'PENDIENTE'}`}>
                      {item.read_at ? 'Leida' : 'Nueva'}
                    </span>
                  </td>
                  <td>
                    <div style={{ display: 'flex', gap: 12, alignItems: 'flex-start' }}>
                      <Bell size={18} color={item.read_at ? '#9ca3af' : '#047857'} />
                      <div>
                        <div style={{ fontWeight: item.read_at ? 500 : 700 }}>{item.title}</div>
                        <div style={{ color: '#6b7280', marginTop: 4 }}>{item.body || '-'}</div>
                      </div>
                    </div>
                  </td>
                  <td>{new Date(item.created_at).toLocaleString('es-PE')}</td>
                </tr>
              ))}
              {rows.length === 0 && !notifications.isLoading ? (
                <tr><td colSpan={3} className="table-empty">No hay notificaciones.</td></tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  )
}
