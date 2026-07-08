import axios, { type AxiosError } from 'axios'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'https://sst.agrocalera.app/api',
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('docssalud_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

export function getErrorMessage(error: unknown): string {
  const axiosError = error as AxiosError<{ message?: string; errors?: Record<string, string[]> }>
  const firstValidationError = axiosError.response?.data.errors
    ? Object.values(axiosError.response.data.errors)[0]?.[0]
    : undefined

  return firstValidationError ?? axiosError.response?.data.message ?? 'No se pudo completar la operacion.'
}
