import React, { useEffect, useState } from 'react';
import { Ticket } from '../types';
import { DataTable, Column } from './DataTable';
import AddTicketForm from './AddTicketForm';

const Support = () => {
  const [tickets, setTickets] = useState<Ticket[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);

  const fetchTickets = async () => {
    try {
      setLoading(true);
      const response = await fetch(`${window.petSettings.apiUrl}/tickets`, {
        headers: {
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

  const handleAddSuccess = () => {
    setShowAddForm(false);
    fetchTickets();
  };

  const columns: Column<Ticket>[] = [
    { key: 'id', header: 'ID' },
    { key: 'subject', header: 'Subject', render: (val) => <strong>{val}</strong> },
    { key: 'customerId', header: 'Customer ID' },
    { key: 'priority', header: 'Priority', render: (val) => <span style={{ textTransform: 'capitalize' }}>{val}</span> },
    { key: 'status', header: 'Status', render: (val) => <span style={{ textTransform: 'capitalize' }}>{val}</span> },
    { key: 'createdAt', header: 'Created' },
    { key: 'resolvedAt', header: 'Resolved', render: (val) => val || '-' },
  ];

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
        <AddTicketForm 
          onSuccess={handleAddSuccess} 
          onCancel={() => setShowAddForm(false)} 
        />
      )}

      <DataTable 
        columns={columns} 
        data={tickets} 
        emptyMessage="No tickets found." 
      />
    </div>
  );
};

export default Support;
