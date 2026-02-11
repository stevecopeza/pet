import React, { useEffect, useState } from 'react';
import { Quote, QuoteLine } from '../types';
import { DataTable, Column } from './DataTable';
import MalleableFieldsRenderer from './MalleableFieldsRenderer';

interface QuoteDetailsProps {
  quoteId: number;
  onBack: () => void;
}

const QuoteDetails: React.FC<QuoteDetailsProps> = ({ quoteId, onBack }) => {
  const [quote, setQuote] = useState<Quote | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [activeSchema, setActiveSchema] = useState<any | null>(null);

  const fetchSchema = async () => {
    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/schemas/quote?status=active`, {
        headers: {
          'X-WP-Nonce': nonce,
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
  
  // Add Line Form State
  const [description, setDescription] = useState('');
  const [quantity, setQuantity] = useState(1);
  const [unitPrice, setUnitPrice] = useState(0);
  const [group, setGroup] = useState('development'); // default group
  const [addingLine, setAddingLine] = useState(false);

  const fetchQuote = async () => {
    try {
      setLoading(true);
      const response = await fetch(`${window.petSettings.apiUrl}/quotes/${quoteId}`, {
        headers: {
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch quote details');
      }

      const data = await response.json();
      setQuote(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchQuote();
    fetchSchema();
  }, [quoteId]);

  const handleAddLine = async (e: React.FormEvent) => {
    e.preventDefault();
    setAddingLine(true);
    
    try {
      const response = await fetch(`${window.petSettings.apiUrl}/quotes/${quoteId}/lines`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.petSettings.nonce,
        },
        body: JSON.stringify({
          description,
          quantity,
          unitPrice,
          lineGroupType: group
        }),
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.error || 'Failed to add line item');
      }

      // Reset form and refresh quote
      setDescription('');
      setQuantity(1);
      setUnitPrice(0);
      fetchQuote();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Error adding line item');
    } finally {
      setAddingLine(false);
    }
  };

  const lineColumns: Column<QuoteLine>[] = [
    { key: 'description', header: 'Description' },
    { key: 'group', header: 'Group' },
    { key: 'quantity', header: 'Qty' },
    { key: 'unitPrice', header: 'Unit Price', render: (_, item) => <span>${item.unitPrice.toFixed(2)}</span> },
    { key: 'total', header: 'Total', render: (_, item) => <span>${item.total.toFixed(2)}</span> },
  ];

  if (loading) return <div>Loading quote details...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;
  if (!quote) return <div>Quote not found</div>;

  return (
    <div className="pet-quote-details">
      <div style={{ marginBottom: '20px' }}>
        <button className="button" onClick={onBack}>&larr; Back to Quotes</button>
      </div>

      <div className="card" style={{ padding: '20px', marginBottom: '20px', background: '#fff', border: '1px solid #ccd0d4' }}>
        <h2>Quote #{quote.id} (v{quote.version})</h2>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px' }}>
          <div>
            <p><strong>Customer ID:</strong> {quote.customerId}</p>
            <p><strong>State:</strong> <span className={`pet-status-badge status-${quote.state.toLowerCase()}`}>{quote.state}</span></p>
          </div>
          <div>
            <p><strong>Total Value:</strong> ${quote.lines.reduce((sum, line) => sum + line.total, 0).toFixed(2)}</p>
            <p><strong>Items:</strong> {quote.lines.length}</p>
          </div>
        </div>

        {activeSchema && quote.malleableData && (
          <MalleableFieldsRenderer 
            schema={activeSchema} 
            values={quote.malleableData} 
            onChange={() => {}} 
            readOnly={true}
          />
        )}
      </div>

      <h3>Line Items</h3>
      <DataTable 
        columns={lineColumns} 
        data={quote.lines} 
        emptyMessage="No line items yet." 
      />

      <div className="card" style={{ marginTop: '20px', padding: '20px', background: '#f0f0f1', border: '1px solid #ccd0d4' }}>
        <h4>Add Line Item</h4>
        <form onSubmit={handleAddLine} style={{ display: 'grid', gridTemplateColumns: '2fr 1fr 1fr 1fr auto', gap: '10px', alignItems: 'end' }}>
          <div>
            <label style={{ display: 'block', marginBottom: '5px' }}>Description</label>
            <input 
              type="text" 
              className="regular-text" 
              style={{ width: '100%' }}
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              required
            />
          </div>
          <div>
            <label style={{ display: 'block', marginBottom: '5px' }}>Group</label>
            <select 
              value={group} 
              onChange={(e) => setGroup(e.target.value)}
              style={{ width: '100%' }}
            >
              <option value="development">Development</option>
              <option value="design">Design</option>
              <option value="hosting">Hosting</option>
              <option value="consulting">Consulting</option>
            </select>
          </div>
          <div>
            <label style={{ display: 'block', marginBottom: '5px' }}>Quantity</label>
            <input 
              type="number" 
              step="0.1"
              min="0.1"
              style={{ width: '100%' }}
              value={quantity}
              onChange={(e) => setQuantity(parseFloat(e.target.value))}
              required
            />
          </div>
          <div>
            <label style={{ display: 'block', marginBottom: '5px' }}>Unit Price</label>
            <input 
              type="number" 
              step="0.01"
              min="0"
              style={{ width: '100%' }}
              value={unitPrice}
              onChange={(e) => setUnitPrice(parseFloat(e.target.value))}
              required
            />
          </div>
          <div>
            <button type="submit" className="button button-primary" disabled={addingLine}>
              {addingLine ? 'Adding...' : 'Add Line'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default QuoteDetails;
