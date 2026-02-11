import React, { useState, useEffect } from 'react';
import MalleableFieldsRenderer from './MalleableFieldsRenderer';
import { Customer, SchemaDefinition, Ticket, Site, Sla } from '../types';

interface TicketFormProps {
  initialData?: Ticket;
  onSuccess: () => void;
  onCancel: () => void;
}

const TicketForm: React.FC<TicketFormProps> = ({ initialData, onSuccess, onCancel }) => {
  const isEditMode = !!initialData;
  const [customerId, setCustomerId] = useState(initialData?.customerId?.toString() || '');
  const [siteId, setSiteId] = useState(initialData?.siteId?.toString() || '');
  const [slaId, setSlaId] = useState(initialData?.slaId?.toString() || '');
  const [subject, setSubject] = useState(initialData?.subject || '');
  const [description, setDescription] = useState(initialData?.description || '');
  const [priority, setPriority] = useState(initialData?.priority || 'medium');
  const [status, setStatus] = useState(initialData?.status || 'new');
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [sites, setSites] = useState<Site[]>([]);
  const [slas, setSlas] = useState<Sla[]>([]);
  // @ts-ignore
  const [malleableData, setMalleableData] = useState<Record<string, any>>(initialData?.malleableData || {});
  const [activeSchema, setActiveSchema] = useState<SchemaDefinition | null>(null);
  const [loading, setLoading] = useState(false);
  const [loadingCustomers, setLoadingCustomers] = useState(true);
  const [loadingSites, setLoadingSites] = useState(false);
  const [loadingSlas, setLoadingSlas] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchCustomers = async () => {
      try {
        // @ts-ignore
        const response = await fetch(`${window.petSettings.apiUrl}/customers`, {
          headers: {
            // @ts-ignore
            'X-WP-Nonce': window.petSettings.nonce,
          },
        });

        if (!response.ok) {
          throw new Error('Failed to fetch customers');
        }

        const data = await response.json();
        setCustomers(data);
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Failed to load customers');
      } finally {
        setLoadingCustomers(false);
      }
    };

    const fetchSlas = async () => {
      setLoadingSlas(true);
      try {
        // @ts-ignore
        const response = await fetch(`${window.petSettings.apiUrl}/slas`, {
          headers: {
            // @ts-ignore
            'X-WP-Nonce': window.petSettings.nonce,
          },
        });

        if (response.ok) {
          const data = await response.json();
          setSlas(data);
        }
      } catch (err) {
        console.error('Failed to fetch SLAs', err);
      } finally {
        setLoadingSlas(false);
      }
    };

    const fetchSchema = async () => {
      try {
        // @ts-ignore
        const response = await fetch(`${window.petSettings.apiUrl}/schemas/ticket?status=active`, {
          headers: {
            // @ts-ignore
            'X-WP-Nonce': window.petSettings.nonce,
          },
        });

        if (response.ok) {
          const data = await response.json();
          if (Array.isArray(data) && data.length > 0) {
            setActiveSchema(data[0]);
          }
        }
      } catch (err) {
        console.error('Failed to fetch schema', err);
      }
    };

    fetchCustomers();
    fetchSlas();
    fetchSchema();
  }, [isEditMode]);

  useEffect(() => {
    const fetchSites = async () => {
      if (!customerId) {
        setSites([]);
        setSiteId('');
        return;
      }

      setLoadingSites(true);
      try {
        // @ts-ignore
        const response = await fetch(`${window.petSettings.apiUrl}/sites?customer_id=${customerId}`, {
          headers: {
            // @ts-ignore
            'X-WP-Nonce': window.petSettings.nonce,
          },
        });

        if (response.ok) {
          const data = await response.json();
          setSites(data);
          
          // If editing and we have a siteId, it will be set by initialData state
          // If changing customer, reset siteId unless it matches (unlikely)
          if (!isEditMode || (initialData?.customerId.toString() !== customerId)) {
            // Don't auto-select site for now
            setSiteId('');
          }
        }
      } catch (err) {
        console.error('Failed to fetch sites', err);
      } finally {
        setLoadingSites(false);
      }
    };

    fetchSites();
  }, [customerId, isEditMode, initialData]);

  const handleMalleableChange = (key: string, value: any) => {
    setMalleableData(prev => ({
      ...prev,
      [key]: value
    }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!customerId) {
      setError('Please select a customer');
      return;
    }

    setLoading(true);
    setError(null);

    try {
      // @ts-ignore
      const apiUrl = window.petSettings.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings.nonce;
      
      const url = isEditMode 
        ? `${apiUrl}/tickets/${initialData!.id}`
        : `${apiUrl}/tickets`;

      const body: any = { 
        customerId: parseInt(customerId, 10),
        subject,
        description,
        priority,
        malleableData
      };

      if (siteId) {
        body.siteId = parseInt(siteId, 10);
      }

      if (slaId) {
        body.slaId = parseInt(slaId, 10);
      }

      if (isEditMode) {
        body.status = status;
      }

      const response = await fetch(url, {
        method: isEditMode ? 'PUT' : 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify(body),
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || `Failed to ${isEditMode ? 'update' : 'create'} ticket`);
      }

      onSuccess();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="pet-form-container" style={{ padding: '20px', background: '#f9f9f9', border: '1px solid #ddd', marginBottom: '20px' }}>
      <h3>{isEditMode ? 'Edit Ticket' : 'Create New Ticket'}</h3>
      {error && <div style={{ color: 'red', marginBottom: '10px' }}>{error}</div>}
      <form onSubmit={handleSubmit}>
        <div style={{ marginBottom: '10px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>Customer:</label>
          {loadingCustomers ? (
            <div>Loading customers...</div>
          ) : (
            <select 
              value={customerId} 
              onChange={(e) => setCustomerId(e.target.value)}
              required
              style={{ width: '100%', maxWidth: '400px' }}
            >
              <option value="">Select a customer</option>
              {customers.map(c => (
                <option key={c.id} value={c.id}>{c.name}</option>
              ))}
            </select>
          )}
        </div>

        {customerId && (
          <div style={{ marginBottom: '10px' }}>
            <label style={{ display: 'block', marginBottom: '5px' }}>Site (Optional):</label>
            {loadingSites ? (
              <div>Loading sites...</div>
            ) : (
              <select 
                value={siteId} 
                onChange={(e) => setSiteId(e.target.value)}
                style={{ width: '100%', maxWidth: '400px' }}
              >
                <option value="">Select a site</option>
                {sites.map(s => (
                  <option key={s.id} value={s.id}>{s.name}</option>
                ))}
              </select>
            )}
          </div>
        )}

        <div style={{ marginBottom: '10px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>SLA (Optional):</label>
          {loadingSlas ? (
            <div>Loading SLAs...</div>
          ) : (
            <select
              value={slaId}
              onChange={(e) => setSlaId(e.target.value)}
              style={{ width: '100%', maxWidth: '400px' }}
            >
              <option value="">Select SLA</option>
              {slas.map(s => (
                <option key={s.id} value={s.id}>{s.name} ({s.target_response_hours}h / {s.target_resolution_hours}h)</option>
              ))}
            </select>
          )}
        </div>

        <div style={{ marginBottom: '10px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>Subject:</label>
          <input 
            type="text" 
            value={subject} 
            onChange={(e) => setSubject(e.target.value)} 
            required 
            style={{ width: '100%', maxWidth: '400px' }}
          />
        </div>

        <div style={{ marginBottom: '10px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>Priority:</label>
          <select 
            value={priority} 
            onChange={(e) => setPriority(e.target.value)}
            style={{ width: '100%', maxWidth: '400px' }}
          >
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
            <option value="critical">Critical</option>
          </select>
        </div>

        {isEditMode && (
          <div style={{ marginBottom: '10px' }}>
            <label style={{ display: 'block', marginBottom: '5px' }}>Status:</label>
            <select 
              value={status} 
              onChange={(e) => setStatus(e.target.value)}
              style={{ width: '100%', maxWidth: '400px' }}
            >
              <option value="new">New</option>
              <option value="open">Open</option>
              <option value="pending">Pending</option>
              <option value="resolved">Resolved</option>
              <option value="closed">Closed</option>
            </select>
          </div>
        )}

        <div style={{ marginBottom: '10px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>Description:</label>
          <textarea 
            value={description} 
            onChange={(e) => setDescription(e.target.value)} 
            required 
            rows={4}
            style={{ width: '100%', maxWidth: '400px' }}
          />
        </div>

        {activeSchema && (
          <div style={{ marginBottom: '20px', padding: '15px', background: '#fff', border: '1px solid #eee' }}>
            <h4 style={{ marginTop: 0, marginBottom: '15px' }}>Additional Information</h4>
            <MalleableFieldsRenderer 
              schema={activeSchema}
              values={malleableData}
              onChange={handleMalleableChange}
            />
          </div>
        )}

        <div style={{ marginTop: '15px' }}>
          <button 
            type="submit" 
            disabled={loading || loadingCustomers}
            className="button button-primary"
            style={{ marginRight: '10px' }}
          >
            {loading ? 'Saving...' : (isEditMode ? 'Update Ticket' : 'Create Ticket')}
          </button>
          <button 
            type="button" 
            onClick={onCancel}
            className="button"
            disabled={loading}
          >
            Cancel
          </button>
        </div>
      </form>
    </div>
  );
};

export default TicketForm;
