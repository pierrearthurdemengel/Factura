import { useState, useMemo } from 'react';
import { 
  DndContext, 
  DragOverlay, 
  closestCorners, 
  KeyboardSensor, 
  PointerSensor, 
  useSensor, 
  useSensors, 
  type DragStartEvent, 
  type DragEndEvent 
} from '@dnd-kit/core';
import { 
  SortableContext, 
  verticalListSortingStrategy, 
  useSortable 
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { type Invoice } from '../api/factura';
import { useAudio } from '../context/AudioScope';
import './InvoiceKanbanBoard.css';

interface InvoiceKanbanBoardProps {
  invoices: Invoice[];
  onStateChange: (id: string, newStatus: string) => Promise<void>;
  disabled?: boolean;
}

const COLUMNS = [
  { id: 'DRAFT', title: 'Brouillons' },
  { id: 'SENT', title: 'Envoyees' },
  { id: 'ACKNOWLEDGED', title: 'Acceptees' },
  { id: 'PAID', title: 'Payees' },
];

function KanbanCard({ invoice }: Readonly<{ invoice: Invoice }>) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id: invoice.id, data: invoice });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
  };

  return (
    <div
      ref={setNodeRef}
      style={style}
      {...attributes}
      {...listeners}
      className={`kanban-card ${isDragging ? 'dragging' : ''}`}
    >
      <div className="kanban-card-header">
        <span className="kanban-card-number">{invoice.number || 'Brouillon'}</span>
        <span className="kanban-card-amount">{invoice.totalIncludingTax} €</span>
      </div>
      <div className="kanban-card-body">
        <p className="kanban-card-client">{invoice.buyer?.name || 'Client inconnu'}</p>
        <p className="kanban-card-date">{new Date(invoice.issueDate).toLocaleDateString('fr-FR')}</p>
      </div>
    </div>
  );
}

export default function InvoiceKanbanBoard({ invoices, onStateChange, disabled }: Readonly<InvoiceKanbanBoardProps>) {
  const [activeInvoice, setActiveInvoice] = useState<Invoice | null>(null);
  const audio = useAudio();

  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: {
        distance: 5, // Requires 5px movement before dragging to allow clicks
      },
    }),
    useSensor(KeyboardSensor)
  );

  const columnsData = useMemo(() => {
    const acc: Record<string, Invoice[]> = {
      DRAFT: [], SENT: [], ACKNOWLEDGED: [], PAID: []
    };
    invoices.forEach(inv => {
      if (acc[inv.status]) {
        acc[inv.status].push(inv);
      }
    });
    return acc;
  }, [invoices]);

  const handleDragStart = (event: DragStartEvent) => {
    const { active } = event;
    const inv = invoices.find(i => i.id === active.id);
    if (inv) setActiveInvoice(inv);
  };

  const handleDragEnd = async (event: DragEndEvent) => {
    setActiveInvoice(null);
    if (disabled) return;

    const { active, over } = event;
    if (!over) return;

    const inv = invoices.find(i => i.id === active.id);
    if (!inv) return;

    // Is it dropped over a column id or another card?
    const targetStatus = COLUMNS.find(c => c.id === over.id)?.id || 
                         invoices.find(i => i.id === over.id)?.status;

    if (!targetStatus || targetStatus === inv.status) return;

    if (targetStatus === 'PAID') {
      audio.playArpeggio();
      // Gamification: Jubilation — charge dynamiquement (~12 KB)
      import('canvas-confetti').then(({ default: confetti }) => {
        confetti({
          particleCount: 150,
          spread: 80,
          origin: { y: 0.6 },
          colors: ['#A855F7', '#3B82F6', '#10B981', '#F59E0B'],
          disableForReducedMotion: true
        });
      });
    } else {
      audio.playTick();
    }

    await onStateChange(inv.id, targetStatus);
  };

  return (
    <div className="kanban-board">
      <DndContext 
        sensors={sensors}
        collisionDetection={closestCorners}
        onDragStart={handleDragStart}
        onDragEnd={handleDragEnd}
      >
        <div className="kanban-columns-container">
          {COLUMNS.map((col) => (
            <div key={col.id} className="kanban-column" data-status={col.id}>
              <div className="kanban-column-header">
                <h3 className="kanban-column-title">{col.title}</h3>
                <span className="kanban-column-count">{columnsData[col.id].length}</span>
              </div>
              
              <div className="kanban-column-body">
                <SortableContext items={columnsData[col.id].map(i => i.id)} strategy={verticalListSortingStrategy}>
                  <div className="kanban-droppable-area" id={col.id}>
                    {columnsData[col.id].map((invoice) => (
                      <KanbanCard key={invoice.id} invoice={invoice} />
                    ))}
                    {columnsData[col.id].length === 0 && (
                      <div className="kanban-empty-placeholder">
                        Glisser une facture ici
                      </div>
                    )}
                  </div>
                </SortableContext>
              </div>
            </div>
          ))}
        </div>

        <DragOverlay>
          {activeInvoice ? <KanbanCard invoice={activeInvoice} /> : null}
        </DragOverlay>
      </DndContext>
    </div>
  );
}
