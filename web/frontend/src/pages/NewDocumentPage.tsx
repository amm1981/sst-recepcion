import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Search, FileText, Phone, Camera, Upload, IdCard, Save, Plus } from 'lucide-react'
import { useMemo, useRef, useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { Link, useNavigate } from 'react-router-dom'
import { z } from 'zod'
import { api, getErrorMessage } from '../api/client'
import type { Catalogs, MedicalDocument, Worker } from '../types'

const schema = z.object({
  medical_document_type_id: z.string().min(1, 'Seleccione el tipo'),
  worker_dni: z.string().min(8, 'Ingrese DNI'),
  delivery_relation_id: z.string().min(1, 'Seleccione la relacion'),
  delivery_relation_detail: z.string().optional(),
  deliverer_name: z.string().min(1, 'Ingrese nombre'),
  deliverer_document: z.string().optional(),
  contact_number: z.string().min(1, 'Ingrese numero de contacto'),
  observation: z.string().optional(),
  deliverer_photo: z.any().optional(),
  medical_document_file: z.any(),
  annexes: z.any().optional(),
})

type NewDocumentForm = z.infer<typeof schema>

export function NewDocumentPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [worker, setWorker] = useState<Worker | null>(null)
  const [workerError, setWorkerError] = useState('')
  const [submitError, setSubmitError] = useState('')
  
  const delivererPhotoRef = useRef<HTMLInputElement | null>(null)
  const medicalFileRef = useRef<HTMLInputElement | null>(null)
  const annexesRef = useRef<HTMLInputElement | null>(null)

  const catalogs = useQuery({
    queryKey: ['catalogs'],
    queryFn: async () => (await api.get<Catalogs>('/sync/catalogs')).data,
  })

  const form = useForm<NewDocumentForm>({
    resolver: zodResolver(schema),
    defaultValues: {
      medical_document_type_id: '',
      worker_dni: '',
      delivery_relation_id: '',
      delivery_relation_detail: '',
      deliverer_name: '',
      deliverer_document: '',
      contact_number: '',
      observation: '',
    },
  })

  const relationId = useWatch({ control: form.control, name: 'delivery_relation_id' })
  const selectedRelation = useMemo(
    () => catalogs.data?.delivery_relations.find((item) => String(item.id) === relationId),
    [catalogs.data?.delivery_relations, relationId],
  )
  
  const delivererPhoto = useWatch({ control: form.control, name: 'deliverer_photo' })
  const medicalFile = useWatch({ control: form.control, name: 'medical_document_file' })
  const annexes = useWatch({ control: form.control, name: 'annexes' })
  const selectedTypeId = useWatch({ control: form.control, name: 'medical_document_type_id' })

  const getFileNames = (fileList: any) => {
    if (!fileList || fileList.length === 0) return null
    return Array.from(fileList as FileList).map(f => f.name).join(', ')
  }

  async function searchWorker() {
    setWorker(null)
    setWorkerError('')
    try {
      const response = await api.get<Worker>(`/workers/search/${form.getValues('worker_dni')}`)
      setWorker(response.data)
    } catch {
      setWorkerError('Trabajador no encontrado.')
    }
  }

  const mutation = useMutation({
    mutationFn: async (values: NewDocumentForm) => {
      const data = new FormData()
      Object.entries(values).forEach(([key, value]) => {
        if (key !== 'deliverer_photo' && key !== 'medical_document_file' && key !== 'annexes' && value) {
          data.append(key, String(value))
        }
      })
      const photo = values.deliverer_photo?.[0] as File | undefined
      const mFile = values.medical_document_file?.[0] as File | undefined
      if (photo) data.append('deliverer_photo', photo)
      if (mFile) data.append('medical_document_file', mFile)
      Array.from((values.annexes ?? []) as FileList).forEach((file) => data.append('annexes[]', file))
      return (await api.post<MedicalDocument>('/medical-documents', data)).data
    },
    onSuccess: async (document) => {
      await queryClient.invalidateQueries({ queryKey: ['documents'] })
      navigate(`/documents/${document.id}`)
    },
    onError: (error) => setSubmitError(getErrorMessage(error)),
  })

  const { ref: dRef, ...dRest } = form.register('deliverer_photo')
  const { ref: mRef, ...mRest } = form.register('medical_document_file')
  const { ref: aRef, ...aRest } = form.register('annexes')

  return (
    <div>
      <div className="breadcrumb">
        <Link to="/dashboard">Inicio</Link> &gt; <span>Nuevo Registro</span>
      </div>
      
      <div className="page-title" style={{ marginBottom: 24 }}>
        <h1 style={{ fontSize: 24 }}>Nuevo Registro</h1>
      </div>

      <form className="new-doc-grid" onSubmit={form.handleSubmit((values) => mutation.mutate(values))}>
        {/* Column 1 */}
        <div className="new-doc-col">
          <div className="new-doc-section">
            <h2 className="section-title">Información del Documento</h2>
            
            <div className="field">
              <label>Tipo de Documento *</label>
              <div className="radio-card-list">
                {catalogs.data?.medical_document_types.map((type) => {
                  const isActive = selectedTypeId === String(type.id)
                  return (
                    <div 
                      key={type.id} 
                      className={`radio-card ${isActive ? 'active' : ''}`}
                      onClick={() => form.setValue('medical_document_type_id', String(type.id), { shouldValidate: true })}
                    >
                      <div className="radio-card-content">
                        <FileText size={20} />
                        {type.name}
                      </div>
                      <div className="radio-circle"></div>
                    </div>
                  )
                })}
              </div>
              {form.formState.errors.medical_document_type_id && (
                <span className="error">{form.formState.errors.medical_document_type_id.message}</span>
              )}
            </div>

            <div className="field" style={{ marginTop: 24 }}>
              <label>DNI *</label>
              <div className="search-input-integrated">
                <input {...form.register('worker_dni')} placeholder="12345678" />
                <button type="button" className="search-btn" onClick={searchWorker}>
                  <Search size={18} />
                </button>
              </div>
              {workerError && <span className="error">{workerError}</span>}
              {form.formState.errors.worker_dni && <span className="error">{form.formState.errors.worker_dni.message}</span>}
            </div>

            {worker && (
              <div className="worker-card-styled">
                <img src={`https://ui-avatars.com/api/?name=${worker.first_name}+${worker.last_name}&background=e5e7eb&color=111827&bold=true`} alt="Avatar" className="avatar-large" />
                <div className="info">
                  <strong>{worker.first_name} {worker.last_name}</strong>
                  <span className="dni-text">DNI: {worker.dni}</span>
                  <span className="badge-light-green">Trabajador activo</span>
                </div>
              </div>
            )}

            <div style={{ marginTop: 32, borderTop: '1px solid #f3f4f6', paddingTop: 24 }}>
              <div className="field" style={{ marginBottom: 16 }}>
                <label>Relación de quien entrega *</label>
                <select {...form.register('delivery_relation_id')}>
                  <option value="">Seleccione</option>
                  {catalogs.data?.delivery_relations.map((relation) => (
                    <option key={relation.id} value={relation.id}>{relation.name}</option>
                  ))}
                </select>
                {form.formState.errors.delivery_relation_id && <span className="error">{form.formState.errors.delivery_relation_id.message}</span>}
              </div>

              {selectedRelation?.requires_detail && (
                <div className="field" style={{ marginBottom: 16 }}>
                  <label>Detalle de relación</label>
                  <input {...form.register('delivery_relation_detail')} />
                </div>
              )}

              <div className="field" style={{ marginBottom: 16 }}>
                <label>Nombre de quien entrega *</label>
                <input {...form.register('deliverer_name')} />
                {form.formState.errors.deliverer_name && <span className="error">{form.formState.errors.deliverer_name.message}</span>}
              </div>

              <div className="field" style={{ marginBottom: 16 }}>
                <label>Documento de quien entrega</label>
                <input {...form.register('deliverer_document')} />
              </div>
              
              <div className="field full">
                <label>Observación</label>
                <textarea {...form.register('observation')} style={{ minHeight: 60 }} />
              </div>
            </div>
          </div>
        </div>

        {/* Column 2 */}
        <div className="new-doc-col">
          <div className="new-doc-section">
            <div className="field">
              <label>Foto de quien entrega</label>
              <div className="dropzone-box" style={{ marginTop: 8 }}>
                <IdCard size={40} className="dropzone-icon" />
                <div className="file-hint">
                  {getFileNames(delivererPhoto) || 'Vista previa de la foto'}
                </div>
              </div>
              <div className="dropzone-actions">
                <button type="button" className="dropzone-btn" onClick={() => delivererPhotoRef.current?.click()}>
                  <Camera size={16} /> Tomar foto
                </button>
                <button type="button" className="dropzone-btn" onClick={() => delivererPhotoRef.current?.click()}>
                  <Upload size={16} /> Subir archivo
                </button>
                <input type="file" accept="image/*" {...dRest} ref={(e) => { dRef(e); delivererPhotoRef.current = e }} style={{ display: 'none' }} />
              </div>
              {form.formState.errors.deliverer_photo && <span className="error">{form.formState.errors.deliverer_photo.message as string}</span>}
            </div>
          </div>

          <div className="new-doc-section">
            <div className="field">
              <label>Foto del Documento *</label>
              <div className="dropzone-box" style={{ marginTop: 8 }}>
                <FileText size={40} className="dropzone-icon" />
                <div className="file-hint">
                  {getFileNames(medicalFile) || 'Vista previa del documento'}
                </div>
              </div>
              <div className="dropzone-actions">
                <button type="button" className="dropzone-btn" onClick={() => medicalFileRef.current?.click()}>
                  <Camera size={16} /> Tomar foto
                </button>
                <button type="button" className="dropzone-btn" onClick={() => medicalFileRef.current?.click()}>
                  <Upload size={16} /> Subir archivo
                </button>
                <input type="file" {...mRest} ref={(e) => { mRef(e); medicalFileRef.current = e }} style={{ display: 'none' }} />
              </div>
              {form.formState.errors.medical_document_file && <span className="error">{form.formState.errors.medical_document_file.message as string}</span>}
            </div>
          </div>
        </div>

        {/* Column 3 */}
        <div className="new-doc-col">
          <div className="new-doc-section">
            <div className="field">
              <label>Número de Contacto *</label>
              <div className="input-with-icon" style={{ marginTop: 8 }}>
                <input {...form.register('contact_number')} placeholder="987654321" />
                <Phone size={18} />
              </div>
              {form.formState.errors.contact_number && <span className="error">{form.formState.errors.contact_number.message}</span>}
            </div>
          </div>

          <div className="new-doc-section">
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
              <label style={{ fontSize: 13, fontWeight: 600, color: '#374151' }}>Anexos Adicionales (máx. 4)</label>
              <span style={{ fontSize: 12, color: '#6b7280' }}>
                {annexes?.length || 0}/4 archivos
              </span>
            </div>
            
            <div className="dropzone-box green-dashed" onClick={() => annexesRef.current?.click()}>
              <Plus size={24} />
              <div style={{ fontWeight: 600 }}>Adjuntar archivo</div>
              {annexes?.length ? <div className="file-hint" style={{ color: '#047857' }}>{getFileNames(annexes)}</div> : null}
              <input type="file" multiple {...aRest} ref={(e) => { aRef(e); annexesRef.current = e }} style={{ display: 'none' }} />
            </div>
            
            <div className="file-hint">
              <span>Formatos permitidos: PDF, JPG, PNG</span>
              <span>Tamaño máximo por archivo: 10MB</span>
            </div>
            {form.formState.errors.annexes && <span className="error">{form.formState.errors.annexes.message as string}</span>}
          </div>

          <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            {submitError && <div className="error">{submitError}</div>}
            <button className="btn-save-large" type="submit" disabled={mutation.isPending}>
              <Save size={20} /> Guardar Registro
            </button>
          </div>
        </div>

      </form>
    </div>
  )
}
