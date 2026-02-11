import React, { useState, useEffect } from 'react';
import { Customer, Quote } from '../types';
import MalleableFieldsRenderer from './MalleableFieldsRenderer';

interface QuoteFormProps {
  onSuccess: () => void;
  onCancel: () => void;
  initialData?: Quote;
}

const QuoteForm: React.FC<QuoteFormProps> = ({ onSuccess, onCancel, initialData }) => {
  const isEditMode = !!initialData;
  const [customerId, setCustomerId] = useState(initialData?.customerId?.toString() || '');
  const [totalValue, setTotalValue] = useState(initialData?.totalValue?.toString() || '0.00');
  const [currency, setCurrency] = useState(initialData?.currency || 'USD');
  const [acceptedAt, setAcceptedAt] = useState(initialData?.acceptedAt || '');
  const [malleableData, setMalleableData] = useState<Record<string, any>>(initialData?.malleableData || {});
  const [activeSchema, setActiveSchema] = useState<any | null>(null);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [loading, setLoading] = useState(false);
  const [loadingCustomers, setLoadingCustomers] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchSchema = async () => {
      try {
        // @ts-ignore
        const apiUrl = window.petSettings?.apiUrl;
        // @ts-ignore
        const nonce = window.petSettings?.nonce;

        const response = await fetch(`${apiUrl}/schemas/quote?status=active`, {
          headers: { 'X-WP-Nonce': nonce }
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

    const fetchCustomers = async () => {
      try {
        // @ts-ignore
        const apiUrl = window.petSettings?.apiUrl;
        // @ts-ignore
        const nonce = window.petSettings?.nonce;

        const response = await fetch(`${apiUrl}/customers`, {
          headers: {
            'X-WP-Nonce': nonce,
          },
        });

        if (!response.ok) {
          throw new Error('Failed to fetch customers');
        }

        const data = await response.json();
        setCustomers(data);
        if (!isEditMode && data.length > 0 && !customerId) {
          setCustomerId(data[0].id.toString());
        }
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Failed to load customers');
      } finally {
        setLoadingCustomers(false);
      }
    };

    fetchCustomers();
    fetchSchema();
  }, [isEditMode]);

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
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const url = isEditMode 
        ? `${apiUrl}/quotes/${initialData!.id}`
        : `${apiUrl}/quotes`;

      const response = await fetch(url, {
        method: isEditMode ? 'PUT' : 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify({ 
          customerId: parseInt(customerId, 10),
          totalValue: parseFloat(totalValue),
          currency,
          acceptedAt: acceptedAt || null,
          malleableData
        }),
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || `Failed to ${isEditMode ? 'update' : 'create'} quote`);
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
      <h3>{isEditMode ? 'Edit Quote' : 'Create New Quote'}</h3>
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

        <div style={{ marginBottom: '10px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>Total Value:</label>
          <input 
            type="number" 
            step="0.01"
            value={totalValue} 
            onChange={(e) => setTotalValue(e.target.value)} 
            style={{ width: '100%', maxWidth: '400px', padding: '8px', border: '1px solid #ddd', borderRadius: '4px' }}
          />
        </div>

        <div style={{ marginBottom: '10px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>Currency:</label>
          <input 
            type="text" 
            value={currency} 
            onChange={(e) => setCurrency(e.target.value)} 
            style={{ width: '100%', maxWidth: '400px', padding: '8px', border: '1px solid #ddd', borderRadius: '4px' }}
          />
        </div>

        <div style={{ marginBottom: '10px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>Accepted At:</label>
          <input 
            type="datetime-local" 
            value={acceptedAt} 
            onChange={(e) => setAcceptedAt(e.target.value)} 
            style={{ width: '100%', maxWidth: '400px', padding: '8px', border: '1px solid #ddd', borderRadius: '4px' }}
          />
        </div>

        {activeSchema && (
          <MalleableFieldsRenderer 
            schema={activeSchema} 
            values={malleableData} 
            onChange={(key, value) => setMalleableData(prev => ({ ...prev, [key]: value }))} 
          />
        )}

        <div style={{ marginTop: '20px' }}>
          <button 
            type="submit" 
            disabled={loading}
            style={{ marginRight: '10px', padding: '8px 16px', background: '#007cba', color: 'white', border: 'none', cursor: 'pointer' }}
          >
            {loading ? 'Saving...' : (isEditMode ? 'Update Quote' : 'Create Quote')}
          </button>
          <button 
            type="button" 
            onClick={onCancel}
            style={{ padding: '8px 16px', background: '#f0f0f1', border: '1px solid #ccc', cursor: 'pointer' }}
          >
            Cancel
          </button>
        </div>
      </form>
    </div>
  );
};

export default QuoteForm;
