import React, { useEffect, useState } from 'react';
import { Ticket } from '../types';
import { DataTable, Column } from './DataTable';
import TicketForm from './TicketForm';
import TicketDetails from './TicketDetails';

const Support = () => {
  const [tickets, setTickets] = useState<Ticket[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingTicket, setEditingTicket] = useState<Ticket | null>(null);
  const [selectedTicket, setSelectedTicket] = useState<Ticket | null>(null);
  const [selectedIds, setSelectedIds] = useState<(string | number)[]>([]);

  const fetchTickets = async () => {
    try {
      setLoading(true);
      // @ts-ignore
      const response = await fetch(`${window.petSettings.apiUrl}/tickets`, {
        headers: {
          // @ts-ignore
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch tickets');
      }

      const data = await response.json();
      setTickets(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchTickets();
  }, []);

  const handleFormSuccess = () => {
    setShowAddForm(false);
    setEditingTicket(null);
    fetchTickets();
  };

  const handleEdit = (ticket: Ticket) => {
    setEditingTicket(ticket);
    setShowAddForm(true);
  };

  const handleArchive = async (id: number) => {
    if (!confirm('Are you sure you want to archive this ticket?')) return;

    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/tickets/${id}`, {
        method: 'DELETE',
        headers: {
          'X-WP-Nonce': nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to archive ticket');
      }

      fetchTickets();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Failed to archive');
    }
  };

  const handleBulkArchive = async () => {
    if (!confirm(`Are you sure you want to archive ${selectedIds.length} tickets?`)) return;

    // @ts-ignore
    const apiUrl = window.petSettings?.apiUrl;
    // @ts-ignore
    const nonce = window.petSettings?.nonce;

    // Process sequentially
    for (const id of selectedIds) {
      try {
        await fetch(`${apiUrl}/tickets/${id}`, {
          method: 'DELETE',
          headers: {
            'X-WP-Nonce': nonce,
          },
        });
      } catch (e) {
        console.error(`Failed to archive ${id}`, e);
      }
    }
    
    setSelectedIds([]);
    fetchTickets();
  };

  const columns: Column<Ticket>[] = [
    { key: 'id', header: 'ID' },
    { key: 'subject', header: 'Subject', render: (val, item) => (
      <a 
        href="#" 
        onClick={(e) => { 
          e.preventDefault(); 
          setSelectedTicket(item); 
        }}
        style={{ fontWeight: 'bold' }}
      >
        {val}
      </a>
    ) },
    { key: 'customerId', header: 'Customer ID' },
    { key: 'priority', header: 'Priority', render: (val) => <span className={`pet-priority-badge priority-${val}`}>{val}</span> },
    { key: 'status', header: 'Status', render: (val) => <span className={`pet-status-badge status-${val}`}>{val}</span> },
    { key: 'createdAt', header: 'Created' },
    { key: 'archivedAt', header: 'Archived', render: (val) => val ? 'Yes' : '-' },
  ];

  if (selectedTicket) {
    return (
      <TicketDetails 
        ticket={selectedTicket} 
        onBack={() => {
          setSelectedTicket(null);
          fetchTickets();
        }} 
      />
    );
  }

  if (loading && !tickets.length) return <div>Loading tickets...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;

  return (
    <div className="pet-support">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h2>Support (Tickets)</h2>
        {!showAddForm && (
          <button className="button button-primary" onClick={() => setShowAddForm(true)}>
            Create New Ticket
          </button>
        )}
      </div>

      {showAddForm && (
        <TicketForm 
          onSuccess={handleFormSuccess} 
          onCancel={() => { setShowAddForm(false); setEditingTicket(null); }} 
          initialData={editingTicket || undefined}
        />
      )}

      {selectedIds.length > 0 && (
        <div style={{ padding: '10px', background: '#e5f5fa', border: '1px solid #b5e1ef', marginBottom: '15px', display: 'flex', alignItems: 'center', gap: '15px' }}>
          <strong>{selectedIds.length} items selected</strong>
          <button className="button" onClick={handleBulkArchive}>Archive Selected</button>
        </div>
      )}

      <DataTable 
        columns={columns} 
        data={tickets} 
        emptyMessage="No tickets found." 
        selection={{
          selectedIds,
          onSelectionChange: setSelectedIds
        }}
        actions={(item) => (
          <div style={{ display: 'flex', gap: '5px', justifyContent: 'flex-end' }}>
            <button 
              className="button button-small"
              onClick={() => setSelectedTicket(item)}
            >
              View
            </button>
            <button 
              className="button button-small"
              onClick={() => handleEdit(item)}
            >
              Edit
            </button>
            <button 
              className="button button-small button-link-delete"
              style={{ color: '#a00', borderColor: '#a00' }}
              onClick={() => handleArchive(item.id)}
            >
              Archive
            </button>
          </div>
        )}
      />
    </div>
  );
};

export default Support;
