import { useState, useRef, useEffect } from 'react';
import { format, isValid, parse } from 'date-fns';
import { fr } from 'date-fns/locale';
import { DayPicker } from 'react-day-picker';
import 'react-day-picker/dist/style.css';
import './DatePicker.css';

interface DatePickerProps {
  value: string;
  onChange: (dateStr: string) => void;
  placeholder?: string;
  className?: string;
}

export default function DatePicker({ value, onChange, placeholder = "Sélectionner une date", className = "" }: DatePickerProps) {
  const [isOpen, setIsOpen] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);

  // Convert string (YYYY-MM-DD from parent state) to Date object robustly
  const selectedDate = value ? parse(value, 'yyyy-MM-dd', new Date()) : undefined;

  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const handleSelect = (date?: Date) => {
    if (date && isValid(date)) {
      onChange(format(date, 'yyyy-MM-dd'));
      setIsOpen(false);
    } else {
      onChange('');
    }
  };

  return (
    <div className={`custom-datepicker-container ${className}`} ref={containerRef}>
      <div 
        className={`custom-datepicker-input ${isOpen ? 'active' : ''}`}
        onClick={() => setIsOpen(!isOpen)}
        tabIndex={0}
      >
        <span className={selectedDate ? 'has-value' : 'placeholder'}>
          {selectedDate && isValid(selectedDate) ? format(selectedDate, 'PPP', { locale: fr }) : placeholder}
        </span>
        <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" strokeWidth="2" fill="none" strokeLinecap="round" strokeLinejoin="round" className="calendar-icon">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
          <line x1="16" y1="2" x2="16" y2="6"></line>
          <line x1="8" y1="2" x2="8" y2="6"></line>
          <line x1="3" y1="10" x2="21" y2="10"></line>
        </svg>
      </div>

      {isOpen && (
        <div className="custom-datepicker-popover">
          <DayPicker
            mode="single"
            selected={selectedDate}
            onSelect={handleSelect}
            locale={fr}
            weekStartsOn={1}
            showOutsideDays
            className="premium-calendar"
          />
        </div>
      )}
    </div>
  );
}
