import React, { useEffect, useState } from 'react';
import { Quote, QuoteComponent } from '../types';
import { DataTable, Column } from './DataTable';
import MalleableFieldsRenderer from './MalleableFieldsRenderer';
import AddComponentForm from './AddComponentForm';
import AddCostAdjustmentForm from './AddCostAdjustmentForm';

interface QuoteDetailsProps {
  quoteId: number;
  onBack: () => void;
}

const QuoteDetails: React.FC<QuoteDetailsProps> = ({ quoteId, onBack }) => {
  console.log('QuoteDetails rendering for ID:', quoteId);
  const [quote, setQuote] = useState<Quote | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [activeSchema, setActiveSchema] = useState<any | null>(null);
  const [catalogItems, setCatalogItems] = useState<{ id: number; name: string; unit_price: number; unit_cost: number; type: string }[]>([]);
  
  // Section Builder State
  const [showTypeSelection, setShowTypeSelection] = useState(false);
  const [selectedComponentType, setSelectedComponentType] = useState<'product' | 'service' | 'recurring-service' | 'repeat-product' | null>(null);
  const [showAdjustmentForm, setShowAdjustmentForm] = useState(false);

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
      console.log('Fetched quote data:', data);
      console.log('Quote components:', data.components);
      setQuote(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  const fetchCatalog = async () => {
    try {
      const response = await fetch(`${window.petSettings.apiUrl}/catalog-items`, {
        headers: { 'X-WP-Nonce': window.petSettings.nonce }
      });
      if (response.ok) {
        const data = await response.json();
        setCatalogItems(data);
      }
    } catch (err) {
      console.error('Failed to fetch catalog items', err);
    }
  };

  useEffect(() => {
    console.log('QuoteDetails mounted/updated. Fetching data...');
    fetchQuote();
    fetchSchema();
    fetchCatalog();
    return () => console.log('QuoteDetails unmounting');
  }, [quoteId]);

  const handleSend = async () => {
    if (!confirm('Are you sure you want to send this quote?')) return;
    try {
      setLoading(true);
      const response = await fetch(`${window.petSettings.apiUrl}/quotes/${quoteId}/send`, {
        method: 'POST',
        headers: {
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });
      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.error || 'Failed to send quote');
      }
      await fetchQuote();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Error sending quote');
      setLoading(false);
    }
  };

  const handleAccept = async () => {
    if (!confirm('Are you sure you want to mark this quote as ACCEPTED? This will create a project.')) return;
    try {
      setLoading(true);
      const response = await fetch(`${window.petSettings.apiUrl}/quotes/${quoteId}/accept`, {
        method: 'POST',
        headers: {
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });
      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.error || 'Failed to accept quote');
      }
      await fetchQuote();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Error accepting quote');
      setLoading(false);
    }
  };

  const handleComponentAdded = () => {
    setSelectedComponentType(null);
    setShowTypeSelection(false);
    fetchQuote();
  };

  const handleAdjustmentAdded = () => {
    setShowAdjustmentForm(false);
    fetchQuote();
  };

  const handleRemoveAdjustment = async (adjustmentId: number) => {
    if (!confirm('Are you sure you want to remove this adjustment?')) return;
    try {
      setLoading(true);
      const response = await fetch(`${window.petSettings.apiUrl}/quotes/${quoteId}/adjustments/${adjustmentId}`, {
        method: 'DELETE',
        headers: {
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });
      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.error || 'Failed to remove adjustment');
      }
      await fetchQuote();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Error removing adjustment');
      setLoading(false);
    }
  };

  const componentColumns: Column<QuoteComponent>[] = [
    { key: 'description', header: 'Description', render: (_, item) => {
        if (item.items && item.items.length > 0) {
            return (
                <div>
                    <strong>{item.description}</strong>
                    <ul style={{ margin: '5px 0 0 15px', fontSize: '0.9em', color: '#666' }}>
                        {item.items.map((subItem, idx) => (
                            <li key={idx}>{subItem.description} ({subItem.quantity} x ${subItem.unitSellPrice})</li>
                        ))}
                    </ul>
                </div>
            );
        }
        return item.description;
    }},
    { key: 'type', header: 'Type', render: (_, item) => <span style={{ textTransform: 'capitalize' }}>{item.type}</span> },
    { key: 'sellValue', header: 'Value', render: (_, item) => <span>${item.sellValue.toFixed(2)}</span> },
  ];

  if (loading) return <div>Loading quote details...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;
  if (!quote) return <div>Quote not found</div>;

  const totalInternalCost = quote.totalInternalCost ?? (quote.components || []).reduce((sum, c) => sum + (c.internalCost || 0), 0);
  const adjustedCost = quote.adjustedTotalInternalCost ?? totalInternalCost;
  const margin = quote.margin ?? (quote.totalValue - adjustedCost);
  
  const readinessIssues: string[] = [];
  if ((quote.components || []).length === 0) readinessIssues.push('At least one component is required');
  if (margin < 0) readinessIssues.push('Margin cannot be negative');
  if (!quote.title) readinessIssues.push('Title is required');
  
  const isReady = readinessIssues.length === 0;

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
            <p><strong>Title:</strong> {quote.title}</p>
            {quote.description && <p><strong>Description:</strong> {quote.description}</p>}
            <p><strong>State:</strong> <span className={`pet-status-badge status-${quote.state.toLowerCase()}`}>{quote.state}</span></p>
            <div style={{ marginTop: '10px' }}>
              {quote.state === 'draft' && (
                <div>
                  <button 
                    className="button" 
                    onClick={handleSend}
                    disabled={!isReady}
                    title={!isReady ? readinessIssues.join('\n') : 'Send to customer'}
                    style={{ opacity: !isReady ? 0.5 : 1 }}
                  >
                    Send Quote
                  </button>
                  {!isReady && (
                    <div style={{ color: '#d63638', fontSize: '12px', marginTop: '5px' }}>
                      <strong>Not ready to send:</strong>
                      <ul style={{ margin: '5px 0 0 15px' }}>
                        {readinessIssues.map((issue, i) => <li key={i}>{issue}</li>)}
                      </ul>
                    </div>
                  )}
                </div>
              )}
              {quote.state === 'sent' && (
                <button className="button button-primary" onClick={handleAccept}>Accept Quote</button>
              )}
            </div>
          </div>
          <div>
            <p><strong>Total Value:</strong> ${quote.totalValue.toFixed(2)}</p>
            <p><strong>Base Cost:</strong> ${totalInternalCost.toFixed(2)}</p>
            {quote.costAdjustments && quote.costAdjustments.length > 0 && (
                <p><strong>Adjusted Cost:</strong> ${adjustedCost.toFixed(2)}</p>
            )}
            <p><strong>Margin:</strong> <span style={{ color: margin < 0 ? 'red' : 'green' }}>${margin.toFixed(2)}</span></p>
            <p><strong>Components:</strong> {(quote.components || []).length}</p>
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

      <h3>Quote Components</h3>
      {Object.entries((quote.components || []).reduce((groups: Record<string, QuoteComponent[]>, component) => {
        const section = component.section || 'General';
        if (!groups[section]) groups[section] = [];
        groups[section].push(component);
        return groups;
      }, {})).map(([section, components]) => (
        <div key={section} style={{ marginBottom: '20px' }}>
          <h4 style={{ borderBottom: '1px solid #ddd', paddingBottom: '5px' }}>{section}</h4>
          <DataTable 
            columns={componentColumns} 
            data={components} 
            emptyMessage="No components in this section." 
          />
        </div>
      ))}
      {(quote.components || []).length === 0 && <p>No components added yet.</p>}

      <div style={{ marginTop: '30px' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <h3>Cost Adjustments</h3>
            {!showAdjustmentForm && (
                <button className="button" onClick={() => setShowAdjustmentForm(true)}>Add Adjustment</button>
            )}
        </div>
        
        {showAdjustmentForm && (
            <div className="card" style={{ padding: '20px', marginTop: '15px', background: '#fff', border: '1px solid #ccd0d4', maxWidth: '600px' }}>
                <h4>Add Cost Adjustment</h4>
                <AddCostAdjustmentForm 
                    quoteId={quoteId}
                    onSuccess={handleAdjustmentAdded}
                    onCancel={() => setShowAdjustmentForm(false)}
                />
            </div>
        )}

        {quote.costAdjustments && quote.costAdjustments.length > 0 ? (
          <table className="widefat fixed striped" style={{ marginTop: '10px', border: '1px solid #ccd0d4' }}>
            <thead>
                <tr>
                    <th style={{ textAlign: 'left', padding: '10px' }}>Description</th>
                    <th style={{ textAlign: 'left', padding: '10px' }}>Amount</th>
                    <th style={{ textAlign: 'left', padding: '10px' }}>Reason</th>
                    <th style={{ textAlign: 'left', padding: '10px' }}>Approved By</th>
                    <th style={{ textAlign: 'left', padding: '10px' }}>Date</th>
                    <th style={{ textAlign: 'right', padding: '10px' }}>Actions</th>
                </tr>
            </thead>
            <tbody>
                {quote.costAdjustments.map(adj => (
                    <tr key={adj.id}>
                        <td style={{ padding: '10px' }}>{adj.description}</td>
                        <td style={{ padding: '10px' }}>${adj.amount.toFixed(2)}</td>
                        <td style={{ padding: '10px' }}>{adj.reason}</td>
                        <td style={{ padding: '10px' }}>{adj.approvedBy}</td>
                        <td style={{ padding: '10px' }}>{new Date(adj.appliedAt).toLocaleDateString()}</td>
                        <td style={{ padding: '10px', textAlign: 'right' }}>
                            <button 
                                className="button button-link-delete" 
                                onClick={() => handleRemoveAdjustment(adj.id)}
                                style={{ color: '#a00' }}
                            >
                                Remove
                            </button>
                        </td>
                    </tr>
                ))}
            </tbody>
          </table>
        ) : (
            !showAdjustmentForm && <p style={{ fontStyle: 'italic', color: '#666' }}>No cost adjustments recorded.</p>
        )}
      </div>

      <div style={{ marginTop: '20px' }}>
        {!showTypeSelection && !selectedComponentType && (
            <button className="button button-primary" onClick={() => setShowTypeSelection(true)}>
                Add Component
            </button>
        )}

        {showTypeSelection && (
            <div className="card" style={{ padding: '20px', background: '#fff', border: '1px solid #ccd0d4', maxWidth: '600px' }}>
                <h3>Select Component Type</h3>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '15px', marginTop: '15px' }}>
                    <button className="button" onClick={() => { setSelectedComponentType('product'); setShowTypeSelection(false); }}>
                        Once-off Product
                        <span style={{ display: 'block', fontSize: '0.8em', color: '#666', marginTop: '5px' }}>One-time hardware or license</span>
                    </button>
                    <button className="button" onClick={() => { setSelectedComponentType('service'); setShowTypeSelection(false); }}>
                        Once-off Service
                        <span style={{ display: 'block', fontSize: '0.8em', color: '#666', marginTop: '5px' }}>One-time labor or fee</span>
                    </button>
                    <button className="button" onClick={() => { setSelectedComponentType('repeat-product'); setShowTypeSelection(false); }}>
                        Repeat Product
                        <span style={{ display: 'block', fontSize: '0.8em', color: '#666', marginTop: '5px' }}>Subscription hardware/license</span>
                    </button>
                    <button className="button" onClick={() => { setSelectedComponentType('recurring-service'); setShowTypeSelection(false); }}>
                        Recurring Service
                        <span style={{ display: 'block', fontSize: '0.8em', color: '#666', marginTop: '5px' }}>SLA, Retainer, or Ongoing Service</span>
                    </button>
                </div>
                <div style={{ marginTop: '15px', textAlign: 'right' }}>
                    <button className="button" onClick={() => setShowTypeSelection(false)}>Cancel</button>
                </div>
            </div>
        )}

        {selectedComponentType && (
            <div className="card" style={{ padding: '20px', background: '#fff', border: '1px solid #ccd0d4', maxWidth: '600px' }}>
                <h3>
                  Add {
                    selectedComponentType === 'product' ? 'Once-off Product' :
                    selectedComponentType === 'service' ? 'Once-off Service' :
                    selectedComponentType === 'repeat-product' ? 'Repeat Product' :
                    'Recurring Service'
                  }
                </h3>
                <AddComponentForm 
                    type={selectedComponentType}
                    catalogItems={catalogItems}
                    onSuccess={handleComponentAdded}
                    onCancel={() => setSelectedComponentType(null)}
                    quoteId={quoteId}
                />
            </div>
        )}
      </div>
    </div>
  );
};

export default QuoteDetails;
