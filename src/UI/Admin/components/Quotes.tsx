import React, { useEffect, useState, useRef } from 'react';
import { Quote } from '../types';
import { DataTable, Column } from './DataTable';
import QuoteForm from './QuoteForm';
import QuoteDetails from './QuoteDetails';

const Quotes = () => {
  const [quotes, setQuotes] = useState<Quote[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingQuote, setEditingQuote] = useState<Quote | null>(null);
  const [selectedQuoteId, setSelectedQuoteId] = useState<number | null>(null);
  const [selectedIds, setSelectedIds] = useState<(string | number)[]>([]);
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

  const fetchQuotes = async () => {
    try {
      setLoading(true);
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/quotes`, {
        headers: {
          'X-WP-Nonce': nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch quotes');
      }

      const data = await response.json();
      setQuotes(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchQuotes();
    fetchSchema();
  }, []);

  const handleAddSuccess = (savedQuote?: Quote) => {
    setShowAddForm(false);
    setEditingQuote(null);
    
    if (savedQuote && savedQuote.id) {
      setSelectedQuoteId(savedQuote.id);
    } else {
      fetchQuotes();
    }
  };

  const handleEdit = (quote: Quote) => {
    setEditingQuote(quote);
    setShowAddForm(true);
  };

  const handleArchive = async (id: number) => {
    if (!confirm('Are you sure you want to archive this quote?')) return;

    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/quotes/${id}`, {
        method: 'DELETE',
        headers: {
          'X-WP-Nonce': nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to archive quote');
      }

      fetchQuotes();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Failed to archive');
    }
  };

  const handleBulkArchive = async () => {
    if (!confirm(`Are you sure you want to archive ${selectedIds.length} quotes?`)) return;

    // @ts-ignore
    const apiUrl = window.petSettings?.apiUrl;
    // @ts-ignore
    const nonce = window.petSettings?.nonce;

    // Process sequentially
    for (const id of selectedIds) {
      try {
        await fetch(`${apiUrl}/quotes/${id}`, {
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
    fetchQuotes();
  };

  const columns: Column<Quote>[] = [
    { 
      header: 'ID', 
      key: 'id',
      render: (val: any, item: Quote) => (
        <button 
          type="button"
          onClick={() => setSelectedQuoteId(item.id)}
          style={{ 
            background: 'none', 
            border: 'none', 
            color: '#2271b1', 
            cursor: 'pointer', 
            padding: 0, 
            textAlign: 'left',
            fontWeight: 'bold',
            fontSize: 'inherit'
          }}
        >
          {String(val)}
        </button>
      )
    },
    { header: 'Customer ID', key: 'customerId' },
    { header: 'State', key: 'state' },
    { header: 'Version', key: 'version' },
    { header: 'Currency', key: 'currency' },
    { 
      header: 'Total Value', 
      key: 'totalValue',
      render: (val: any) => val ? `$${Number(val).toFixed(2)}` : '-'
    },
    { 
      header: 'Accepted At', 
      key: 'acceptedAt',
      render: (val: any) => val ? new Date(val).toLocaleDateString() : '-'
    },
    { 
      header: 'Line Total', 
      key: 'lines',
      render: (_, quote) => {
        const total = (quote.lines || []).reduce((sum, line) => sum + line.total, 0);
        return `$${total.toFixed(2)}`;
      }
    },
    ...(activeSchema?.fields || activeSchema?.schema || []).map((field: any) => ({
      key: field.key as keyof Quote,
      header: field.label,
      render: (_: any, item: Quote) => {
        const value = item.malleableData?.[field.key];
        return value !== undefined && value !== null ? String(value) : '-';
      }
    })),
  ];

  if (selectedQuoteId) {
    return (
      <QuoteDetails 
        quoteId={selectedQuoteId} 
        onBack={() => setSelectedQuoteId(null)} 
      />
    );
  }

  return (
    <div className="pet-quotes">

      {showAddForm ? (
        <QuoteForm 
          onSuccess={handleAddSuccess} 
          onCancel={() => { setShowAddForm(false); setEditingQuote(null); }}
          initialData={editingQuote || undefined}
        />
      ) : (
        <>
          <div className="pet-header-actions" style={{ marginBottom: '20px', display: 'flex', gap: '10px' }}>
            <button 
              onClick={() => { setEditingQuote(null); setShowAddForm(true); }}
              className="button button-primary"
            >
              Start building quote
            </button>
            {selectedIds.length > 0 && (
              <button 
                onClick={handleBulkArchive}
                className="button button-secondary"
                style={{ color: '#b32d2e', borderColor: '#b32d2e' }}
              >
                Archive Selected ({selectedIds.length})
              </button>
            )}
          </div>

          {selectedIds.length > 0 && (
            <div style={{ padding: '10px', background: '#e5f5fa', border: '1px solid #b5e1ef', marginBottom: '15px', display: 'flex', alignItems: 'center', gap: '15px' }}>
              <strong>{selectedIds.length} items selected</strong>
              <button className="button button-link-delete" style={{ color: '#a00', borderColor: '#a00' }} onClick={handleBulkArchive}>Archive Selected</button>
            </div>
          )}

          {error && <div className="notice notice-error"><p>{error}</p></div>}
          
          {loading ? (
            <div>Loading quotes...</div>
          ) : (
            <DataTable 
              columns={columns} 
              data={quotes} 
              emptyMessage="No quotes found. Create a new quote to get started." 
              selection={{
                selectedIds,
                onSelectionChange: setSelectedIds
              }}
              actions={(quote) => (
                <div className="pet-actions">
                  <button 
                    onClick={() => setSelectedQuoteId(quote.id)}
                    className="button button-small"
                    style={{ marginRight: '5px' }}
                  >
                    View
                  </button>
                  <button 
                    onClick={() => handleEdit(quote)}
                    className="button button-small"
                    style={{ marginRight: '5px' }}
                    disabled={quote.state !== 'draft'}
                  >
                    Edit
                  </button>
                  <button 
                    onClick={() => handleArchive(quote.id)}
                    className="button button-small button-link-delete"
                  >
                    Archive
                  </button>
                </div>
              )}
            />
          )}
        </>
      )}
    </div>
  );
};

export default Quotes;
