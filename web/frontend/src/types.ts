export type RoleCode = 'ADMIN' | 'RRHH' | 'SST'

export type PermissionCode =
  | 'documents.view'
  | 'documents.create'
  | 'documents.updateStatus'
  | 'workers.manage'
  | 'reports.view'
  | 'admin.manage'

export type Status = 'PENDIENTE' | 'RECEPCIONADO' | 'REGISTRADO' | 'RECHAZADO'

export type Role = {
  id: number
  name: string
  code: RoleCode
  permissions?: Permission[]
}

export type Permission = {
  id: number
  name: string
  code: PermissionCode | string
  description?: string | null
}

export type User = {
  id: number
  name: string
  email: string
  phone?: string | null
  role?: Role | null
  permissions: string[]
}

export type NotificationItem = {
  id: number
  title: string
  body?: string | null
  read_at?: string | null
  data?: Record<string, unknown> | null
  created_at: string
}

export type AuditLog = {
  id: number
  action: string
  entity: string
  entity_id?: number | null
  metadata?: Record<string, unknown> | null
  ip_address?: string | null
  created_at: string
  user?: Pick<User, 'id' | 'name' | 'email'> | null
}

export type WorkerSyncLog = {
  id: number
  started_at?: string | null
  finished_at?: string | null
  status: string
  total_received: number
  created_count: number
  updated_count: number
  inactive_count: number
  warning_count: number
  error_count: number
  error_message?: string | null
}

export type DocumentCounts = {
  pending: number
  received: number
  registered: number
  rejected: number
}

export type Management = {
  id: number
  name: string
  code: string
  is_active: boolean
}

export type Sector = {
  id: number
  name: string
  code: string
  is_active: boolean
}

export type Worker = {
  id: number
  dni: string
  first_name: string
  last_name: string
  email?: string | null
  phone?: string | null
  position?: string | null
  management_id?: number | null
  sector_id?: number | null
  management?: Management | null
  sector?: Sector | null
  is_active: boolean
}

export type MedicalDocumentType = {
  id: number
  name: string
  code: string
  is_active: boolean
}

export type DeliveryRelation = {
  id: number
  name: string
  code: string
  requires_detail: boolean
  is_active: boolean
}

export type MedicalDocumentFile = {
  id: number
  file_type: string
  original_name: string
  mime_type?: string | null
  size: number
}

export type MedicalDocumentHistory = {
  id: number
  from_status?: Status | null
  to_status: Status
  observation?: string | null
  created_at: string
  user?: Pick<User, 'id' | 'name' | 'email'> | null
}

export type MedicalDocument = {
  id: number
  status: Status
  contact_number: string
  observation?: string | null
  delivery_relation_detail?: string | null
  deliverer_name: string
  deliverer_document?: string | null
  created_at: string
  type?: MedicalDocumentType
  worker?: Worker
  delivery_relation?: DeliveryRelation
  deliveryRelation?: DeliveryRelation
  creator?: Pick<User, 'id' | 'name' | 'email'>
  files?: MedicalDocumentFile[]
  history?: MedicalDocumentHistory[]
}

export type Paginated<T> = {
  data: T[]
  current_page: number
  last_page: number
  total: number
}

export type Catalogs = {
  medical_document_types: MedicalDocumentType[]
  delivery_relations: DeliveryRelation[]
  managements: Management[]
  sectors: Sector[]
}
