import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { api } from '../api/client'
import type { User } from '../types'
import { Mail, Phone, Clock, Monitor, MapPin, Eye, EyeOff, ShieldCheck } from 'lucide-react'
import { useState, useEffect } from 'react'

type ProfileForm = {
  firstName: string
  lastName: string
  email: string
  phone: string
  position: string
  management: string
}

type PasswordForm = {
  currentPassword: ''
  newPassword: ''
  confirmPassword: ''
}

export function ProfilePage() {
  const queryClient = useQueryClient()
  const profile = useQuery({
    queryKey: ['profile'],
    queryFn: async () => (await api.get<User>('/profile')).data,
  })

  const form = useForm<ProfileForm>({
    defaultValues: {
      firstName: '',
      lastName: '',
      email: '',
      phone: '',
      position: 'Administrador del Sistema',
      management: 'Administración'
    },
  })

  useEffect(() => {
    if (profile.data) {
      const parts = (profile.data.name || '').split(' ')
      const firstName = parts[0] || ''
      const lastName = parts.slice(1).join(' ') || ''
      
      form.reset({
        firstName,
        lastName,
        email: profile.data.email || '',
        phone: profile.data.phone || '',
        position: 'Administrador del Sistema',
        management: 'Administración'
      })
    }
  }, [profile.data, form])

  const passwordForm = useForm<PasswordForm>({
    defaultValues: {
      currentPassword: '',
      newPassword: '',
      confirmPassword: '',
    }
  })

  const [showCurrent, setShowCurrent] = useState(false)
  const [showNew, setShowNew] = useState(false)
  const [showConfirm, setShowConfirm] = useState(false)

  const profileMutation = useMutation({
    mutationFn: async (values: ProfileForm) =>
      api.put('/profile', {
        name: `${values.firstName} ${values.lastName}`.trim(),
        email: values.email,
        phone: values.phone,
      }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['profile'] }),
  })

  const passwordMutation = useMutation({
    mutationFn: async (values: PasswordForm) => {
      if (values.newPassword !== values.confirmPassword) {
        throw new Error('Las contraseñas no coinciden')
      }
      return api.put('/profile', {
        name: profile.data?.name,
        email: profile.data?.email,
        password: values.newPassword,
      })
    },
    onSuccess: () => {
      passwordForm.reset()
      alert('Contraseña actualizada correctamente')
    },
    onError: (error: any) => {
      alert(error.message || 'Error al actualizar contraseña')
    }
  })

  const user = profile.data

  return (
    <div>
      <div className="breadcrumb">
        <span>Inicio</span> &gt; <span>Mi Perfil</span>
      </div>
      <div className="page-title" style={{ marginBottom: 24, marginTop: 8 }}>
        <h1 style={{ fontSize: 24 }}>Mi Perfil</h1>
      </div>

      <div className="profile-grid">
        {/* Left Column */}
        <div className="profile-col-left">
          <div className="profile-card" style={{ marginBottom: 24 }}>
            <div className="profile-avatar-container">
              <img src={`https://ui-avatars.com/api/?name=${user?.name || 'US'}&background=e5e7eb&color=111827&bold=true&size=200`} alt="Avatar" />
              <h2>{user?.name}</h2>
              <p>{user?.role?.name}</p>
              <div className="badge ACTIVO" style={{ padding: '4px 12px', borderRadius: 12 }}>
                <span style={{ display: 'inline-block', width: 6, height: 6, background: '#16a34a', borderRadius: '50%', marginRight: 6 }}></span>
                Activo
              </div>
            </div>
            <div className="profile-contact-list">
              <div className="profile-contact-item">
                <Mail size={18} />
                {user?.email}
              </div>
              <div className="profile-contact-item">
                <Phone size={18} />
                {user?.phone || 'No registrado'}
              </div>
            </div>
          </div>

          <div className="profile-card">
            <h3 className="profile-card-title" style={{ fontSize: 16 }}>Sesión</h3>
            <div className="profile-session-item">
              <Clock size={16} />
              <div className="profile-session-text">
                <span className="profile-session-label">Último acceso</span>
                <span className="profile-session-val">{new Date().toLocaleString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</span>
              </div>
            </div>
            <div className="profile-session-item">
              <Monitor size={16} />
              <div className="profile-session-text">
                <span className="profile-session-label">Dispositivo</span>
                <span className="profile-session-val">Chrome en Windows 11</span>
              </div>
            </div>
            <div className="profile-session-item">
              <MapPin size={16} />
              <div className="profile-session-text">
                <span className="profile-session-label">Ubicación</span>
                <span className="profile-session-val">Lima, Perú</span>
              </div>
            </div>
          </div>
        </div>

        {/* Right Column */}
        <div className="profile-col-right">
          <form className="profile-card" style={{ marginBottom: 24 }} onSubmit={form.handleSubmit((values) => profileMutation.mutate(values))}>
            <h3 className="profile-card-title">Información Personal</h3>
            <div className="form-grid">
              <div className="field">
                <label>Nombres</label>
                <input {...form.register('firstName')} />
              </div>
              <div className="field">
                <label>Apellidos</label>
                <input {...form.register('lastName')} />
              </div>
              <div className="field">
                <label>Correo electrónico</label>
                <input {...form.register('email')} />
              </div>
              <div className="field">
                <label>Teléfono</label>
                <input {...form.register('phone')} />
              </div>
              <div className="field">
                <label>Cargo</label>
                <input {...form.register('position')} readOnly style={{ backgroundColor: '#f9fafb', color: '#6b7280' }} />
              </div>
              <div className="field">
                <label>Área</label>
                <input {...form.register('management')} readOnly style={{ backgroundColor: '#f9fafb', color: '#6b7280' }} />
              </div>
            </div>
            <div style={{ marginTop: 24, display: 'flex', justifyContent: 'flex-end' }}>
              <button className="btn" type="submit" disabled={profileMutation.isPending}>Guardar Cambios</button>
            </div>
          </form>

          <form className="profile-card" onSubmit={passwordForm.handleSubmit((values) => passwordMutation.mutate(values))}>
            <h3 className="profile-card-title">Seguridad</h3>
            <div className="form-grid" style={{ gridTemplateColumns: 'repeat(3, 1fr)', marginBottom: 20 }}>
              <div className="field">
                <label>Contraseña actual</label>
                <div className="password-input-wrapper">
                  <input type={showCurrent ? 'text' : 'password'} placeholder="Ingresa tu contraseña actual" {...passwordForm.register('currentPassword')} />
                  <button type="button" className="password-toggle-btn" onClick={() => setShowCurrent(!showCurrent)}>
                    {showCurrent ? <EyeOff size={18} /> : <Eye size={18} />}
                  </button>
                </div>
              </div>
              <div className="field">
                <label>Nueva contraseña</label>
                <div className="password-input-wrapper">
                  <input type={showNew ? 'text' : 'password'} placeholder="Ingresa tu nueva contraseña" {...passwordForm.register('newPassword')} />
                  <button type="button" className="password-toggle-btn" onClick={() => setShowNew(!showNew)}>
                    {showNew ? <EyeOff size={18} /> : <Eye size={18} />}
                  </button>
                </div>
              </div>
              <div className="field">
                <label>Confirmar contraseña</label>
                <div className="password-input-wrapper">
                  <input type={showConfirm ? 'text' : 'password'} placeholder="Confirma tu nueva contraseña" {...passwordForm.register('confirmPassword')} />
                  <button type="button" className="password-toggle-btn" onClick={() => setShowConfirm(!showConfirm)}>
                    {showConfirm ? <EyeOff size={18} /> : <Eye size={18} />}
                  </button>
                </div>
              </div>
            </div>
            <div className="security-notice">
              <ShieldCheck size={16} />
              <span>La contraseña debe tener al menos 8 caracteres, incluir una mayúscula, un número y un carácter especial.</span>
            </div>
            <div>
              <button className="btn" type="submit" disabled={passwordMutation.isPending}>
                <ShieldCheck size={18} />
                Actualizar Contraseña
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}
