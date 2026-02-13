import React, { useState, useEffect } from 'react';
import { Ticket, Customer } from '../types';

interface TicketDetailsProps {
  ticket: Ticket;
  onBack: () => void;
}

const TicketDetails: React.FC<TicketDetailsProps> = ({ ticket, onBack }) => {
  const [customer, setCustomer] = useState<Customer | null>(null);
  const [loadingCustomer, setLoadingCustomer] = useState(false);
  const [isEditing, setIsEditing] = useState(false);
  const [status, setStatus] = useState(ticket.status);
  const [priority, setPriority] = useState(ticket.priority);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    const fetchCustomer = async () => {
      if (!ticket.customerId) return;
      
      try {
        setLoadingCustomer(true);
        const response = await fetch(`${window.petSettings.apiUrl}/customers?id=${ticket.customerId}`, {
          headers: {
            'X-WP-Nonce': window.petSettings.nonce,
          },
        });
        
        if (response.ok) {
          const data = await response.json();
          if (Array.isArray(data) && data.length > 0) {
            setCustomer(data.find((c: Customer) => c.id === ticket.customerId) || null);
          }
        }
      } catch (err) {
        console.error('Failed to fetch customer details', err);
      } finally {
        setLoadingCustomer(false);
      }
    };

    fetchCustomer();
  }, [ticket.customerId]);

  const handleSave = async () => {
    try {
      setSaving(true);
      const response = await fetch(`${window.petSettings.apiUrl}/tickets/${ticket.id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.petSettings.nonce,
        },
        body: JSON.stringify({
          status,
          priority,
        }),
      });

      if (!response.ok) {
        throw new Error('Failed to update ticket');
      }

      // In a real app we'd update the parent list or refetch, 
      // but here we just exit edit mode as the local state is updated
      setIsEditing(false);
      // Ideally we should callback to parent to refresh the ticket data
      // onBack(); // simplistic refresh
    } catch (err) {
      alert('Failed to update ticket');
    } finally {
      setSaving(false);
    }
  };

  const getSlaStatusColor = (status?: string) => {
    switch (status) {
      case 'breached': return '#dc3232'; // Red
      case 'warning': return '#dba617'; // Orange
      case 'achieved': return '#46b450'; // Green
      default: return '#72aee6'; // Blue
    }
  };

  const formatDate = (dateStr?: string) => {
    if (!dateStr) return 'N/A';
    return new Date(dateStr).toLocaleString();
  };

  return (
    <div className="pet-ticket-details">
      <div style={{ marginBottom: '20px' }}>
        <button className="button" onClick={onBack}>&larr; Back to Tickets</button>
        {!isEditing && (
          <button className="button" onClick={() => setIsEditing(true)} style={{ marginLeft: '10px' }}>Edit</button>
        )}
        {isEditing && (
          <>
            <button className="button button-primary" onClick={handleSave} disabled={saving} style={{ marginLeft: '10px' }}>
              {saving ? 'Saving...' : 'Save Changes'}
            </button>
            <button className="button" onClick={() => setIsEditing(false)} disabled={saving} style={{ marginLeft: '10px' }}>Cancel</button>
          </>
        )}
      </div>

      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '20px' }}>
        <div>
          <h2 style={{ marginTop: 0 }}>{ticket.subject}</h2>
          <div style={{ color: '#666', fontSize: '1.1em' }}>
            #{ticket.id} &bull; {ticket.createdAt}
          </div>
        </div>
        <div style={{ textAlign: 'right' }}>
          <div style={{ marginBottom: '5px' }}>
            <strong>Status:</strong>{' '}
            {isEditing ? (
              <select value={status} onChange={(e) => setStatus(e.target.value)}>
                <option value="new">New</option>
                <option value="open">Open</option>
                <option value="pending">Pending</option>
                <option value="resolved">Resolved</option>
                <option value="closed">Closed</option>
              </select>
            ) : (
              <span className={`pet-status-badge status-${status}`}>{status}</span>
            )}
          </div>
          <div>
            <strong>Priority:</strong>{' '}
            {isEditing ? (
              <select value={priority} onChange={(e) => setPriority(e.target.value)}>
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
              </select>
            ) : (
              <span className={`pet-priority-badge priority-${priority}`}>{priority}</span>
            )}
          </div>
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: '30px' }}>
        <div className="pet-ticket-main">
          <div className="pet-box" style={{ background: '#fff', padding: '20px', border: '1px solid #ccd0d4', marginBottom: '20px' }}>
            <h3 style={{ marginTop: 0 }}>Description</h3>
            <div style={{ whiteSpace: 'pre-wrap', lineHeight: '1.5' }}>
              {ticket.description}
            </div>
          </div>

          <div className="pet-box" style={{ background: '#fff', padding: '20px', border: '1px solid #ccd0d4' }}>
            <h3 style={{ marginTop: 0 }}>Activity & Comments</h3>
            <p style={{ color: '#666', fontStyle: 'italic' }}>
              No comments yet. (Comment functionality coming soon)
            </p>
          </div>
        </div>

        <div className="pet-ticket-sidebar">
          <div className="pet-box" style={{ background: '#fff', padding: '20px', border: '1px solid #ccd0d4', marginBottom: '20px' }}>
            <h3 style={{ marginTop: 0 }}>SLA Status</h3>
            {ticket.slaId ? (
              <div>
                <div style={{ marginBottom: '10px' }}>
                  <strong>Status: </strong>
                  <span style={{ 
                    fontWeight: 'bold', 
                    color: getSlaStatusColor(ticket.sla_status),
                    textTransform: 'uppercase'
                  }}>
                    {ticket.sla_status || 'Pending'}
                  </span>
                </div>
                <div style={{ marginBottom: '10px' }}>
                  <strong>Response Due:</strong><br/>
                  {formatDate(ticket.response_due_at)}
                </div>
                <div>
                  <strong>Resolution Due:</strong><br/>
                  {formatDate(ticket.resolution_due_at)}
                </div>
              </div>
            ) : (
              <p style={{ fontStyle: 'italic', color: '#666' }}>No SLA assigned to this ticket.</p>
            )}
          </div>

          <div className="pet-box" style={{ background: '#fff', padding: '20px', border: '1px solid #ccd0d4', marginBottom: '20px' }}>
            <h3 style={{ marginTop: 0 }}>Customer Details</h3>
            {loadingCustomer ? (
              <p>Loading...</p>
            ) : customer ? (
              <div>
                <p><strong>Name:</strong> {customer.name}</p>
                <p><strong>Email:</strong> <a href={`mailto:${customer.contactEmail}`}>{customer.contactEmail}</a></p>
              </div>
            ) : (
              <p>Customer ID: {ticket.customerId}</p>
            )}
          </div>

          <div className="pet-box" style={{ background: '#fff', padding: '20px', border: '1px solid #ccd0d4' }}>
            <h3 style={{ marginTop: 0 }}>Actions</h3>
            <button className="button button-large" disabled style={{ width: '100%', marginBottom: '10px' }}>
              Reply
            </button>
            <button className="button button-large" disabled style={{ width: '100%' }}>
              Close Ticket
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default TicketDetails;
