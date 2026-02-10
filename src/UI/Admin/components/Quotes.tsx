import React, { useEffect, useState } from 'react';
import { Quote } from '../types';
import { DataTable, Column } from './DataTable';
import AddQuoteForm from './AddQuoteForm';
import QuoteDetails from './QuoteDetails';

const Quotes = () => {
  const [quotes, setQuotes] = useState<Quote[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [selectedQuoteId, setSelectedQuoteId] = useState<number | null>(null);

  const fetchQuotes = async () => {
    try {
      setLoading(true);
      const response = await fetch(`${window.petSettings.apiUrl}/quotes`, {
        headers: {
          'X-WP-Nonce': window.petSettings.nonce,
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
  }, []);

  const handleAddSuccess = () => {
    setShowAddForm(false);
    fetchQuotes();
  };

  const columns: Column<Quote>[] = [
    { 
      key: 'id', 
      header: 'ID',
      render: (_, item) => (
        <a 
          href="#" 
          onClick={(e) => { 
            e.preventDefault(); 
            setSelectedQuoteId(item.id); 
          }}
          style={{ fontWeight: 'bold' }}
        >
          #{item.id}
        </a>
      )
    },
    { key: 'customerId', header: 'Customer ID' },
    { key: 'state', header: 'State', render: (_, item) => <span>{item.state}</span> },
    { key: 'version', header: 'Version' },
    { 
      key: 'lines', 
      header: 'Lines', 
      render: (_, item) => <span>{item.lines.length} lines</span> 
    },
    {
      key: 'id',
      header: 'Actions',
      render: (_, item) => (
        <button 
          className="button button-small"
          onClick={() => setSelectedQuoteId(item.id)}
        >
          Manage Lines
        </button>
      )
    }
  ];

  if (selectedQuoteId) {
    return (
      <QuoteDetails 
        quoteId={selectedQuoteId} 
        onBack={() => {
          setSelectedQuoteId(null);
          fetchQuotes(); // Refresh list when returning
        }} 
      />
    );
  }

  if (loading && !quotes.length) return <div>Loading quotes...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;

  return (
    <div className="pet-quotes">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h2>Commercial (Quotes)</h2>
        {!showAddForm && (
          <button className="button button-primary" onClick={() => setShowAddForm(true)}>
            Create New Quote
          </button>
        )}
      </div>

      {showAddForm && (
        <AddQuoteForm 
          onSuccess={handleAddSuccess} 
          onCancel={() => setShowAddForm(false)} 
        />
      )}

      <DataTable 
        columns={columns} 
        data={quotes} 
        emptyMessage="No quotes found." 
      />
    </div>
  );
};

export default Quotes;
