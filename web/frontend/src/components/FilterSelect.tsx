import { ChevronDown } from 'lucide-react'
import React from 'react'

type Option = { value: string | number; label: string }

type FilterSelectProps = {
  value: string | number
  onChange: (value: string) => void
  options: Option[]
  placeholder?: string
  className?: string
  style?: React.CSSProperties
}

export function FilterSelect({ value, onChange, options, placeholder = 'Seleccionar...', className = '', style }: FilterSelectProps) {
  return (
    <div className={`filter-select-container ${className}`} style={style}>
      <select 
        value={value} 
        onChange={(e) => onChange(e.target.value)}
        className="filter-select"
      >
        <option value="">{placeholder}</option>
        {options.map(opt => (
          <option key={opt.value} value={opt.value}>{opt.label}</option>
        ))}
      </select>
      <ChevronDown size={16} className="filter-select-icon" />
    </div>
  )
}
