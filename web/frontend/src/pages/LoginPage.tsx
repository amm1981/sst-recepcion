import { zodResolver } from '@hookform/resolvers/zod'
import { LogIn } from 'lucide-react'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Navigate, useLocation, useNavigate } from 'react-router-dom'
import { z } from 'zod'
import { getErrorMessage } from '../api/client'
import { useAuth } from '../auth/AuthContext'

const schema = z.object({
  user: z.string().min(1, 'Ingrese su usuario'),
  password: z.string().min(1, 'Ingrese su password'),
})

type LoginForm = z.infer<typeof schema>

export function LoginPage() {
  const { user, login } = useAuth()
  const [error, setError] = useState('')
  const navigate = useNavigate()
  const location = useLocation()
  const form = useForm<LoginForm>({
    resolver: zodResolver(schema),
    defaultValues: { user: '', password: '' },
  })

  if (user) {
    return <Navigate to="/dashboard" replace />
  }

  async function onSubmit(values: LoginForm) {
    setError('')
    try {
      await login(values.user, values.password)
      const target = (location.state as { from?: { pathname?: string } } | null)?.from?.pathname ?? '/dashboard'
      navigate(target, { replace: true })
    } catch (submitError) {
      setError(getErrorMessage(submitError))
    }
  }

  return (
    <main className="login-screen">
      <form className="login-card grid" onSubmit={form.handleSubmit(onSubmit)}>
        <div className="brand" style={{ padding: 0, flexDirection: 'column', alignItems: 'center', gap: '8px' }}>
          <img src="/logotipo_docssalud.png" alt="DocsSalud Logotipo" style={{ height: '76px', objectFit: 'contain' }} />
        </div>
        <div className="field">
          <label htmlFor="user">Usuario</label>
          <input id="user" autoComplete="username" {...form.register('user')} />
          {form.formState.errors.user ? <span className="error">{form.formState.errors.user.message}</span> : null}
        </div>
        <div className="field">
          <label htmlFor="password">Password</label>
          <input id="password" type="password" autoComplete="current-password" {...form.register('password')} />
          {form.formState.errors.password ? (
            <span className="error">{form.formState.errors.password.message}</span>
          ) : null}
        </div>
        {error ? <div className="error">{error}</div> : null}
        <button className="btn" type="submit" disabled={form.formState.isSubmitting}>
          <LogIn size={18} />
          Ingresar
        </button>
      </form>
    </main>
  )
}
