import {
  BarChart3,
  Bell,
  ChevronDown,
  CheckCheck,
  ClipboardList,
  FileCheck2,
  ExternalLink,
  Home,
  LogOut,
  Settings,
  UserRound,
  UsersRound,
  Menu
} from 'lucide-react'
import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import { useEffect, useRef, useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { api } from '../api/client'
import type { NotificationItem } from '../types'

const navItems = [
  { to: '/dashboard', label: 'Inicio', icon: Home },
  { to: '/documents', label: 'Documentos', icon: ClipboardList, permission: 'documents.view' },
  { to: '/registered-workers', label: 'Trabajadores registrados', icon: FileCheck2, permission: 'documents.view' },
  { to: '/workers', label: 'Trabajadores', icon: UsersRound, permission: 'workers.manage' },
  { to: '/reports', label: 'Reportes', icon: BarChart3, permission: 'reports.view' },
  { to: '/admin', label: 'Administración', icon: Settings, permission: 'admin.manage' },
]

export function AppLayout() {
  const { user, can, logout } = useAuth()
  const navigate = useNavigate()
  const [isCollapsed, setIsCollapsed] = useState(false)
  const [isMobileSidebarOpen, setIsMobileSidebarOpen] = useState(false)
  const [isProfileMenuOpen, setIsProfileMenuOpen] = useState(false)
  const [isNotificationsOpen, setIsNotificationsOpen] = useState(false)
  const notificationsRef = useRef<HTMLDivElement>(null)
  const profileRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      const target = event.target as Node
      if (notificationsRef.current && !notificationsRef.current.contains(target)) {
        setIsNotificationsOpen(false)
      }
      if (profileRef.current && !profileRef.current.contains(target)) {
        setIsProfileMenuOpen(false)
      }
    }

    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [])

  const { data: notifications = [], refetch: refetchNotifications } = useQuery({
    queryKey: ['notifications'],
    queryFn: async () => {
      const res = await api.get('/notifications')
      return res.data as NotificationItem[]
    },
    refetchInterval: 60000, // Poll every minute
  })

  const markAsRead = useMutation({
    mutationFn: async (id: number) => {
      await api.post(`/notifications/${id}/read`)
    },
    onSuccess: () => {
      refetchNotifications()
    }
  })

  const markAllAsRead = useMutation({
    mutationFn: async () => api.post('/notifications/read-all'),
    onSuccess: () => {
      refetchNotifications()
    },
  })

  const unreadCount = notifications.filter(n => !n.read_at).length

  async function handleLogout() {
    await logout()
    navigate('/login', { replace: true })
  }

  return (
    <div className={`app-shell ${isCollapsed ? 'sidebar-collapsed' : ''} ${isMobileSidebarOpen ? 'mobile-sidebar-open' : ''}`}>
      {isMobileSidebarOpen ? <button className="sidebar-backdrop" type="button" aria-label="Cerrar menu" onClick={() => setIsMobileSidebarOpen(false)} /> : null}
      <aside className="sidebar">
        <div className="brand" style={{ display: 'flex', justifyContent: 'center' }}>
          {isCollapsed ? (
            <img src="/icono_docssalud.png" alt="DocsSalud Icono" style={{ width: '32px', height: '32px', objectFit: 'contain' }} />
          ) : (
            <img src="/logotipo_docssalud.png" alt="DocsSalud Logotipo" style={{ height: '58px', objectFit: 'contain' }} />
          )}
        </div>
        <nav className="nav-list">
          {navItems
            .filter((item) => !item.permission || can(item.permission))
            .map((item) => (
              <NavLink className="nav-link" key={item.to} to={item.to} title={isCollapsed ? item.label : undefined} onClick={() => setIsMobileSidebarOpen(false)}>
                <item.icon size={20} strokeWidth={2.5} />
                <span>{item.label}</span>
              </NavLink>
            ))}
        </nav>
      </aside>
      <main className="main">
        <header className="header" style={{ justifyContent: 'space-between' }}>
          <div>
            <button
              className="toggle-sidebar-btn"
              onClick={() => {
                if (window.matchMedia('(max-width: 960px)').matches) {
                  setIsMobileSidebarOpen(true)
                } else {
                  setIsCollapsed(!isCollapsed)
                }
              }}
            >
              <Menu size={20} />
            </button>
          </div>
          <div className="header-actions">
            <div className="dropdown-container" ref={notificationsRef}>
              <div className="header-bell" title="Notificaciones" onClick={() => setIsNotificationsOpen(!isNotificationsOpen)} style={{ cursor: 'pointer' }}>
                <Bell size={22} strokeWidth={2} />
                {unreadCount > 0 && <div className="badge-dot">{unreadCount}</div>}
              </div>

              {isNotificationsOpen && (
                <div className="dropdown-menu" style={{ right: 0, top: '100%', marginTop: 8, padding: '8px 0', width: '300px', maxHeight: '400px', overflowY: 'auto' }}>
                  <div style={{ padding: '8px 16px', borderBottom: '1px solid #e5e7eb', fontWeight: 600, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <span>Notificaciones</span>
                    <button type="button" onClick={() => markAllAsRead.mutate()} disabled={unreadCount === 0 || markAllAsRead.isPending} title="Marcar todas como leidas">
                      <CheckCheck size={16} />
                    </button>
                  </div>
                  {notifications.length === 0 ? (
                    <div style={{ padding: '16px', textAlign: 'center', color: '#6b7280', fontSize: '13px' }}>No tienes notificaciones</div>
                  ) : (
                    notifications.map(n => (
                      <div 
                        key={n.id} 
                        onClick={() => !n.read_at && markAsRead.mutate(n.id)}
                        style={{ 
                          padding: '12px 16px', 
                          borderBottom: '1px solid #f3f4f6',
                          backgroundColor: n.read_at ? 'transparent' : '#f0fdf4',
                          cursor: n.read_at ? 'default' : 'pointer',
                        }}
                      >
                        <div style={{ fontWeight: n.read_at ? 400 : 600, fontSize: '13px', color: '#111827', marginBottom: '4px' }}>{n.title}</div>
                        <div style={{ fontSize: '12px', color: '#4b5563' }}>{n.body}</div>
                        <div style={{ fontSize: '10px', color: '#9ca3af', marginTop: '4px' }}>
                          {new Date(n.created_at).toLocaleString('es-PE')}
                        </div>
                      </div>
                    ))
                  )}
                  <button
                    type="button"
                    onClick={() => { setIsNotificationsOpen(false); navigate('/notifications') }}
                    style={{ width: '100%', justifyContent: 'center', borderTop: '1px solid #e5e7eb', color: '#047857', fontWeight: 600 }}
                  >
                    <ExternalLink size={16} /> Ver historial completo
                  </button>
                </div>
              )}
            </div>
            
            <div className="dropdown-container" ref={profileRef}>
              <div className="header-profile" onClick={() => setIsProfileMenuOpen(!isProfileMenuOpen)} style={{ cursor: 'pointer' }}>
                <img src={`https://ui-avatars.com/api/?name=${user?.name || 'US'}&background=e5e7eb&color=111827&bold=true`} alt="Avatar" className="avatar" />
                <div className="header-profile-info">
                  <strong>{user?.name}</strong>
                  <span>{user?.role?.name}</span>
                </div>
                <ChevronDown size={18} color="#6b7280" />
              </div>
              
              {isProfileMenuOpen && (
                <div className="dropdown-menu" style={{ right: 0, top: '100%', marginTop: 8, padding: '8px 0', minWidth: '180px' }}>
                  <button onClick={() => { setIsProfileMenuOpen(false); navigate('/profile') }} style={{ width: '100%' }}>
                    <UserRound size={16} />
                    Mi perfil
                  </button>
                  <button onClick={handleLogout} className="danger" style={{ width: '100%' }}>
                    <LogOut size={16} />
                    Cerrar sesión
                  </button>
                </div>
              )}
            </div>
          </div>
        </header>
        <section className="content">
          <Outlet />
        </section>
      </main>
    </div>
  )
}
