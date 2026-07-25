import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AlertCircle, CalendarCheck, CheckCircle2, FileSearch, FileText, IdCard, Loader2, Phone, Plus, Save, Search, Upload } from 'lucide-react'
import { useEffect, useMemo, useRef, useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { Link, useNavigate } from 'react-router-dom'
import { z } from 'zod'
import { api, getErrorMessage } from '../api/client'
import type { Catalogs, MedicalDocument, MedicalDocumentType, Paginated, Worker } from '../types'

const schema = z.object({
  medical_document_type_id: z.string().min(1, 'Seleccione el tipo'),
  worker_dni: z.string().min(2, 'Ingrese DNI, nombre o apellidos'),
  document_date: z.string().min(1, 'Ingrese la fecha del documento'),
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

const MAX_FILE_SIZE = 10 * 1024 * 1024
const DOCUMENT_IMAGE_MAX_SIDE = 3600
const DOCUMENT_IMAGE_FALLBACK_SIDES = [3200, 2800, 2400]
const DOCUMENT_IMAGE_QUALITIES = [0.94, 0.92, 0.9, 0.86]
const DOCUMENT_ACCEPT = '.pdf,.docx,.jpeg,.jpg,.png,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,image/jpeg,image/png'
const IMAGE_ACCEPT = '.jpeg,.jpg,.png,image/jpeg,image/png'
const ALLOWED_EXTENSIONS = ['pdf', 'docx', 'jpeg', 'jpg', 'png']
const DATE_RE = /\b(\d{1,2})[/-](\d{1,2})[/-](\d{2,4})\b/g

function createOfflineUuid() {
  return globalThis.crypto?.randomUUID?.() ?? `web-${Date.now()}-${Math.random().toString(36).slice(2)}`
}

function fileExtension(file: File) {
  return file.name.split('.').pop()?.toLowerCase() ?? ''
}

function normalizeText(value: string) {
  return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase()
}

function documentTypePastDays(type?: MedicalDocumentType | null) {
  const normalized = normalizeText(`${type?.code ?? ''} ${type?.name ?? ''}`)
  return normalized.includes('ATENCION') ? 1 : 2
}

function formatDateInput(date: Date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function parseDateCandidate(day: string, month: string, year: string) {
  const fullYear = year.length === 2 ? Number(`20${year}`) : Number(year)
  const currentYear = new Date().getFullYear()
  if (fullYear < 2000 || fullYear > currentYear) return null
  const date = new Date(fullYear, Number(month) - 1, Number(day))
  if (date.getFullYear() !== fullYear || date.getMonth() !== Number(month) - 1 || date.getDate() !== Number(day)) return null
  return formatDateInput(date)
}

function extractDateCandidates(text: string) {
  const dates = new Set<string>()
  for (const match of text.matchAll(DATE_RE)) {
    const parsed = parseDateCandidate(match[1], match[2], match[3])
    if (parsed) dates.add(parsed)
  }
  return Array.from(dates)
}

function allowedDateRange(type?: MedicalDocumentType | null) {
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const min = new Date(today)
  min.setDate(today.getDate() - documentTypePastDays(type))
  return { min: formatDateInput(min), max: formatDateInput(today) }
}

function validateDocumentDateForType(value: string, type?: MedicalDocumentType | null) {
  if (!value) return 'Ingrese la fecha del documento.'
  const { min, max } = allowedDateRange(type)
  if (value < min || value > max) {
    const days = documentTypePastDays(type)
    const label = days === 1 ? '1 dia anterior' : `${days} dias anteriores`
    return `La fecha debe estar entre ${min} y ${max} para este tipo de documento (${label}).`
  }
  return null
}

function formatDisplayDate(value: string) {
  if (!value) return ''
  const [year, month, day] = value.split('-')
  if (!year || !month || !day) return value
  return `${day}-${month}-${year}`
}

function validateDocumentFile(file: File) {
  if (file.size > MAX_FILE_SIZE) {
    throw new Error(`El archivo ${file.name} supera el tamano maximo de 10MB.`)
  }
  if (!ALLOWED_EXTENSIONS.includes(fileExtension(file))) {
    throw new Error(`Formato no permitido: ${file.name}. Use DOCX, PDF, JPEG, JPG o PNG.`)
  }
}

async function compressImageFile(file: File): Promise<File> {
  if (!file.type.startsWith('image/')) return file
  const bitmap = await createImageBitmap(file)
  const maxOriginalSide = Math.max(bitmap.width, bitmap.height)
  const sides = [DOCUMENT_IMAGE_MAX_SIDE, ...DOCUMENT_IMAGE_FALLBACK_SIDES]
    .filter((side, index) => side <= maxOriginalSide || index === 0)
    .filter((side, index, allSides) => allSides.indexOf(side) === index)
  const compressedName = file.name.replace(/\.[^.]+$/, '.jpg')
  let bestBlob: Blob | null = null

  try {
    for (const maxSide of sides) {
      const scale = Math.min(1, maxSide / maxOriginalSide)
      const width = Math.max(1, Math.round(bitmap.width * scale))
      const height = Math.max(1, Math.round(bitmap.height * scale))
      const canvas = document.createElement('canvas')
      canvas.width = width
      canvas.height = height
      const context = canvas.getContext('2d', { alpha: false })
      if (!context) continue
      context.imageSmoothingEnabled = true
      context.imageSmoothingQuality = 'high'
      context.fillStyle = '#ffffff'
      context.fillRect(0, 0, width, height)
      context.drawImage(bitmap, 0, 0, width, height)

      for (const quality of DOCUMENT_IMAGE_QUALITIES) {
        const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality))
        if (!blob) continue
        if (!bestBlob || blob.size < bestBlob.size) bestBlob = blob
        if (blob.size <= MAX_FILE_SIZE && blob.size < file.size * 0.98) {
          return new File([blob], compressedName, { type: 'image/jpeg', lastModified: Date.now() })
        }
      }
    }
  } finally {
    bitmap.close()
  }

  if (file.size <= MAX_FILE_SIZE) return file
  if (bestBlob && bestBlob.size <= MAX_FILE_SIZE) {
    return new File([bestBlob], compressedName, { type: 'image/jpeg', lastModified: Date.now() })
  }

  return file
}

async function prepareDocumentFile(file: File) {
  validateDocumentFile(file)
  const prepared = await compressImageFile(file)
  if (prepared.size > MAX_FILE_SIZE) {
    throw new Error(`El archivo ${file.name} supera el tamano maximo de 10MB incluso despues de comprimir.`)
  }
  return prepared
}

export function NewDocumentPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [worker, setWorker] = useState<Worker | null>(null)
  const [workerResults, setWorkerResults] = useState<Worker[]>([])
  const [workerError, setWorkerError] = useState('')
  const [submitError, setSubmitError] = useState('')
  const [scanStatus, setScanStatus] = useState<'idle' | 'processing' | 'found' | 'multiple' | 'empty'>('idle')
  const [scanMessage, setScanMessage] = useState('')
  const [dateCandidates, setDateCandidates] = useState<string[]>([])
  const pendingOfflineUuidRef = useRef<string | null>(null)

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
      document_date: '',
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
  const isWorkerRelation = selectedRelation?.code === 'TRABAJADOR' || selectedRelation?.name.toLowerCase() === 'trabajador'

  const delivererPhoto = useWatch({ control: form.control, name: 'deliverer_photo' })
  const medicalFile = useWatch({ control: form.control, name: 'medical_document_file' })
  const annexes = useWatch({ control: form.control, name: 'annexes' })
  const selectedTypeId = useWatch({ control: form.control, name: 'medical_document_type_id' })
  const documentDate = useWatch({ control: form.control, name: 'document_date' })
  const delivererName = useWatch({ control: form.control, name: 'deliverer_name' })
  const contactNumber = useWatch({ control: form.control, name: 'contact_number' })
  const selectedType = useMemo(
    () => catalogs.data?.medical_document_types.find((type) => String(type.id) === selectedTypeId),
    [catalogs.data?.medical_document_types, selectedTypeId],
  )

  const typeStepReady = Boolean(selectedTypeId)
  const workerStepReady = typeStepReady && Boolean(worker)
  const documentStepReady = workerStepReady && Boolean(medicalFile?.length)
  const documentDateError = validateDocumentDateForType(documentDate, selectedType)
  const dateStepReady = documentStepReady && Boolean(documentDate) && !documentDateError
  const deliveryStepReady = dateStepReady && Boolean(relationId) && Boolean(delivererName?.trim())
  const contactStepReady = deliveryStepReady && Boolean(contactNumber?.trim())
  const currentDateRange = allowedDateRange(selectedType)

  const getFileNames = (fileList: any) => {
    if (!fileList || fileList.length === 0) return null
    return Array.from(fileList as FileList).map((f) => f.name).join(', ')
  }

  function selectWorker(foundWorker: Worker) {
    setWorker(foundWorker)
    setWorkerResults([])
    setWorkerError('')
    form.setValue('worker_dni', foundWorker.dni, { shouldValidate: true })
    form.setValue('contact_number', foundWorker.phone ?? '', { shouldValidate: true })
    if (isWorkerRelation) {
      form.setValue('deliverer_name', `${foundWorker.first_name} ${foundWorker.last_name}`.trim(), { shouldValidate: true })
      form.setValue('deliverer_document', foundWorker.dni, { shouldValidate: true })
    }
  }

  async function searchWorker() {
    setWorker(null)
    setWorkerResults([])
    setWorkerError('')
    const query = form.getValues('worker_dni').trim()
    if (query.length < 2) {
      setWorkerError('Ingrese DNI, nombre o apellidos.')
      return
    }
    try {
      const response = await api.get<Worker>(`/workers/search/${encodeURIComponent(query)}`)
      selectWorker(response.data)
    } catch {
      try {
        const response = await api.get<Paginated<Worker>>('/workers', {
          params: { q: query, is_active: true, per_page: 10 },
        })
        const results = response.data.data ?? []
        if (results.length === 1) {
          selectWorker(results[0])
        } else if (results.length > 1) {
          setWorkerResults(results)
          setWorkerError('Se encontraron varios trabajadores. Seleccione uno para continuar.')
        } else {
          setWorkerError('Trabajador no encontrado.')
        }
      } catch {
        setWorkerError('Trabajador no encontrado.')
      }
    }
  }

  function setDocumentFile(files: FileList | null) {
    setScanStatus('idle')
    setScanMessage('')
    setDateCandidates([])
    const file = files?.[0]
    if (!file) return

    setScanStatus('processing')
    setScanMessage('Buscando fechas sugeridas...')
    window.setTimeout(() => {
      const candidates = extractDateCandidates(file.name)
      setDateCandidates(candidates)
      if (candidates.length === 1) {
        form.setValue('document_date', candidates[0], { shouldValidate: true })
        setScanStatus('found')
        setScanMessage(`Se encontro una fecha en el nombre del archivo: ${candidates[0]}. Verifiquela antes de guardar.`)
      } else if (candidates.length > 1) {
        setScanStatus('multiple')
        setScanMessage('Se encontraron varias fechas. Seleccione la fecha que corresponde al documento.')
      } else {
        setScanStatus('empty')
        setScanMessage('No se reconocio una fecha automaticamente. Ingrese la fecha manualmente.')
      }
    }, 250)
  }

  useEffect(() => {
    if (!selectedRelation) return
    if (isWorkerRelation && worker) {
      form.setValue('deliverer_name', `${worker.first_name} ${worker.last_name}`.trim(), { shouldValidate: true })
      form.setValue('deliverer_document', worker.dni, { shouldValidate: true })
      form.setValue('contact_number', worker.phone ?? '', { shouldValidate: true })
    } else if (!isWorkerRelation) {
      form.setValue('delivery_relation_detail', '')
      form.setValue('deliverer_name', '', { shouldValidate: true })
      form.setValue('deliverer_document', '', { shouldValidate: true })
      form.setValue('contact_number', worker?.phone ?? '', { shouldValidate: true })
    }
  }, [form, isWorkerRelation, selectedRelation, worker])

  const mutation = useMutation({
    mutationFn: async (values: NewDocumentForm) => {
      setSubmitError('')
      const data = new FormData()
      const offlineUuid = pendingOfflineUuidRef.current ?? createOfflineUuid()
      pendingOfflineUuidRef.current = offlineUuid
      data.append('offline_uuid', offlineUuid)
      Object.entries(values).forEach(([key, value]) => {
        if (key !== 'deliverer_photo' && key !== 'medical_document_file' && key !== 'annexes' && value) {
          data.append(key, String(value))
        }
      })
      const photo = values.deliverer_photo?.[0] as File | undefined
      const mFile = values.medical_document_file?.[0] as File | undefined
      if (photo) data.append('deliverer_photo', await prepareDocumentFile(photo))
      if (mFile) data.append('medical_document_file', await prepareDocumentFile(mFile))
      for (const file of Array.from((values.annexes ?? []) as FileList)) {
        data.append('annexes[]', await prepareDocumentFile(file))
      }
      return (await api.post<MedicalDocument>('/medical-documents', data)).data
    },
    onSuccess: async (document) => {
      pendingOfflineUuidRef.current = null
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

      <form
        className="document-flow"
        onSubmit={form.handleSubmit((values) => {
          const error = validateDocumentDateForType(values.document_date, selectedType)
          if (error) {
            form.setError('document_date', { message: error })
            return
          }
          mutation.mutate(values)
        })}
      >
        <section className="new-doc-section flow-section">
          <FlowHeader step="1" title="Tipo de documento" caption="Seleccione el tipo para aplicar la regla de fecha correcta." />
          <div className="field">
            <div className="radio-card-list compact">
              {catalogs.data?.medical_document_types.map((type) => {
                const isActive = selectedTypeId === String(type.id)
                return (
                  <button
                    type="button"
                    key={type.id}
                    className={`radio-card ${isActive ? 'active' : ''}`}
                    onClick={() => {
                      form.setValue('medical_document_type_id', String(type.id), { shouldValidate: true })
                      if (documentDate) form.trigger('document_date')
                    }}
                  >
                    <div className="radio-card-content">
                      <FileText size={20} />
                      {type.name}
                    </div>
                    <div className="radio-circle"></div>
                  </button>
                )
              })}
            </div>
            {form.formState.errors.medical_document_type_id && (
              <span className="error">{form.formState.errors.medical_document_type_id.message}</span>
            )}
          </div>
        </section>

        <section className={`new-doc-section flow-section ${!typeStepReady ? 'locked' : ''}`}>
          <FlowHeader step="2" title="Trabajador" caption="Busque por DNI, nombre o apellidos y confirme el trabajador." />
          <div className="field">
            <label>Trabajador *</label>
            <div className="search-input-integrated">
              <input {...form.register('worker_dni')} placeholder="Buscar por DNI, nombre o apellidos" disabled={!typeStepReady} />
              <button type="button" className="search-btn" onClick={searchWorker} disabled={!typeStepReady || mutation.isPending}>
                <Search size={18} />
              </button>
            </div>
            {workerError && <span className="error">{workerError}</span>}
            {form.formState.errors.worker_dni && <span className="error">{form.formState.errors.worker_dni.message}</span>}
          </div>

          {workerResults.length > 1 && (
            <div className="worker-result-list">
              {workerResults.map((result) => (
                <button key={result.id} type="button" className="worker-result-item" onClick={() => selectWorker(result)}>
                  <strong>{result.first_name} {result.last_name}</strong>
                  <span>DNI: {result.dni}</span>
                  {result.position ? <small>{result.position}</small> : null}
                </button>
              ))}
            </div>
          )}

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
        </section>

        <section className={`new-doc-section flow-section ${!workerStepReady ? 'locked' : ''}`}>
          <FlowHeader step="3" title="Documento y fecha" caption="Adjunte el documento. Si se detecta una fecha, se sugiere y puede editarse." />
          <div className="flow-two-columns">
            <div className="field">
              <label>Documento *</label>
              <div className="dropzone-box compact-dropzone" style={{ marginTop: 8 }}>
                <FileSearch size={36} className="dropzone-icon" />
                <div className="file-hint">{getFileNames(medicalFile) || 'Foto, PDF o Word del documento'}</div>
              </div>
              <div className="dropzone-actions">
                <button type="button" className="dropzone-btn" onClick={() => medicalFileRef.current?.click()} disabled={!workerStepReady}>
                  <Upload size={16} /> Subir o tomar foto
                </button>
                <input
                  type="file"
                  accept={DOCUMENT_ACCEPT}
                  {...mRest}
                  ref={(e) => { mRef(e); medicalFileRef.current = e }}
                  onChange={(event) => {
                    mRest.onChange(event)
                    setDocumentFile(event.target.files)
                  }}
                  style={{ display: 'none' }}
                />
              </div>
              <div className="file-hint">Formatos permitidos: DOCX, PDF, JPEG, JPG, PNG. Tamano maximo por archivo: 10MB.</div>
              {form.formState.errors.medical_document_file && <span className="error">{form.formState.errors.medical_document_file.message as string}</span>}
            </div>

            <div className="field">
              <label>Fecha del documento *</label>
              <div className="input-with-icon" style={{ marginTop: 8 }}>
                <input type="date" min={currentDateRange.min} max={currentDateRange.max} {...form.register('document_date')} disabled={!workerStepReady} />
                <CalendarCheck size={18} />
              </div>
              <div className="file-hint">Permitido: {currentDateRange.min} al {currentDateRange.max}.</div>
              {documentDate ? <div className="selected-date-label">Fecha seleccionada: {formatDisplayDate(documentDate)}</div> : null}
              {dateCandidates.length > 1 ? (
                <div className="date-candidate-list">
                  {dateCandidates.map((candidate) => (
                    <button key={candidate} type="button" className="date-candidate" onClick={() => form.setValue('document_date', candidate, { shouldValidate: true })}>
                      {candidate}
                    </button>
                  ))}
                </div>
              ) : null}
              {(form.formState.errors.document_date?.message || documentDateError) && documentDate ? (
                <span className="error">{form.formState.errors.document_date?.message || documentDateError}</span>
              ) : null}
              {scanMessage ? (
                <div className={`scan-message ${scanStatus}`}>
                  {scanStatus === 'processing' ? <Loader2 size={16} className="spin" /> : scanStatus === 'found' ? <CheckCircle2 size={16} /> : <AlertCircle size={16} />}
                  {scanMessage}
                </div>
              ) : null}
            </div>
          </div>
        </section>

        <section className={`new-doc-section flow-section ${!dateStepReady ? 'locked' : ''}`}>
          <FlowHeader step="4" title="Entrega" caption="Registre la relacion, los datos de quien entrega y su foto." />
          <div className="flow-two-columns">
            <div className="field">
              <label>Relacion de quien entrega *</label>
              <select {...form.register('delivery_relation_id')} disabled={!dateStepReady}>
                <option value="">Seleccione</option>
                {catalogs.data?.delivery_relations.map((relation) => (
                  <option key={relation.id} value={relation.id}>{relation.name}</option>
                ))}
              </select>
              {form.formState.errors.delivery_relation_id && <span className="error">{form.formState.errors.delivery_relation_id.message}</span>}
            </div>

            {selectedRelation?.requires_detail && (
              <div className="field">
                <label>Detalle de relacion</label>
                <input {...form.register('delivery_relation_detail')} disabled={!dateStepReady} />
              </div>
            )}

            <div className="field">
              <label>Nombre de quien entrega *</label>
              <input {...form.register('deliverer_name')} disabled={!dateStepReady} />
              {form.formState.errors.deliverer_name && <span className="error">{form.formState.errors.deliverer_name.message}</span>}
            </div>

            <div className="field">
              <label>Documento de quien entrega</label>
              <input {...form.register('deliverer_document')} disabled={!dateStepReady} />
            </div>

            <div className="field">
              <label>Foto de quien entrega</label>
              <div className="dropzone-box compact-dropzone" style={{ marginTop: 8 }}>
                <IdCard size={32} className="dropzone-icon" />
                <div className="file-hint">{getFileNames(delivererPhoto) || 'Foto o imagen del documento'}</div>
              </div>
              <div className="dropzone-actions">
                <button type="button" className="dropzone-btn" onClick={() => delivererPhotoRef.current?.click()} disabled={!dateStepReady}>
                  <Upload size={16} /> Adjuntar foto
                </button>
                <input type="file" accept={IMAGE_ACCEPT} {...dRest} ref={(e) => { dRef(e); delivererPhotoRef.current = e }} style={{ display: 'none' }} />
              </div>
              {form.formState.errors.deliverer_photo && <span className="error">{form.formState.errors.deliverer_photo.message as string}</span>}
            </div>
          </div>
        </section>

        <section className={`new-doc-section flow-section ${!deliveryStepReady ? 'locked' : ''}`}>
          <FlowHeader step="5" title="Contacto y anexos" caption="Complete el numero de contacto y adjunte anexos si corresponde." />
          <div className="flow-two-columns">
            <div className="field">
              <label>Numero de Contacto *</label>
              <div className="input-with-icon" style={{ marginTop: 8 }}>
                <input {...form.register('contact_number')} placeholder="987654321" disabled={!deliveryStepReady} />
                <Phone size={18} />
              </div>
              {form.formState.errors.contact_number && <span className="error">{form.formState.errors.contact_number.message}</span>}
            </div>

            <div className="field">
              <label>Observacion</label>
              <textarea {...form.register('observation')} style={{ minHeight: 60 }} disabled={!deliveryStepReady} />
            </div>

            <div className="field full">
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
                <label style={{ fontSize: 13, fontWeight: 600, color: '#374151' }}>Anexos Adicionales (max. 4)</label>
                <span style={{ fontSize: 12, color: '#6b7280' }}>{annexes?.length || 0}/4 archivos</span>
              </div>
              <div className={`dropzone-box green-dashed ${!contactStepReady ? 'disabled' : ''}`} onClick={() => contactStepReady && annexesRef.current?.click()}>
                <Plus size={24} />
                <div style={{ fontWeight: 600 }}>Adjuntar archivo</div>
                {annexes?.length ? <div className="file-hint" style={{ color: '#047857' }}>{getFileNames(annexes)}</div> : null}
                <input type="file" multiple accept={DOCUMENT_ACCEPT} {...aRest} ref={(e) => { aRef(e); annexesRef.current = e }} style={{ display: 'none' }} disabled={!contactStepReady} />
              </div>
              <div className="file-hint">Formatos permitidos: DOCX, PDF, JPEG, JPG, PNG. Maximo 4 anexos.</div>
              {form.formState.errors.annexes && <span className="error">{form.formState.errors.annexes.message as string}</span>}
            </div>
          </div>

          {submitError && <div className="error">{submitError}</div>}
          <button className="btn-save-large" type="submit" disabled={!contactStepReady || mutation.isPending}>
            {mutation.isPending ? <Loader2 size={20} className="spin" /> : <Save size={20} />}
            {mutation.isPending ? 'Registrando documento...' : 'Guardar Registro'}
          </button>
        </section>
      </form>
    </div>
  )
}

function FlowHeader({ step, title, caption }: { step: string; title: string; caption: string }) {
  return (
    <div className="flow-section-header">
      <span className="flow-step">{step}</span>
      <div>
        <h2 className="section-title">{title}</h2>
        <div className="flow-caption">{caption}</div>
      </div>
    </div>
  )
}
