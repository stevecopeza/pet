import React, { useEffect, useState } from 'react';
import { Article } from '../types';
import { DataTable, Column } from './DataTable';
import AddArticleForm from './AddArticleForm';

const Knowledge = () => {
  const [articles, setArticles] = useState<Article[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);

  const fetchArticles = async () => {
    try {
      setLoading(true);
      const response = await fetch(`${window.petSettings.apiUrl}/articles`, {
        headers: {
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch articles');
      }

      const data = await response.json();
      setArticles(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchArticles();
  }, []);

  const handleAddSuccess = () => {
    setShowAddForm(false);
    fetchArticles();
  };

  const columns: Column<Article>[] = [
    { key: 'id', header: 'ID' },
    { key: 'title', header: 'Title', render: (val) => <strong>{val}</strong> },
    { key: 'category', header: 'Category', render: (val) => <span style={{ textTransform: 'capitalize' }}>{val}</span> },
    { key: 'status', header: 'Status', render: (val) => <span style={{ textTransform: 'capitalize' }}>{val}</span> },
    { key: 'createdAt', header: 'Created' },
    { key: 'updatedAt', header: 'Last Updated', render: (val) => val || '-' },
  ];

  if (loading && !articles.length) return <div>Loading knowledge base...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;

  return (
    <div className="pet-knowledge">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h2>Knowledge Base</h2>
        {!showAddForm && (
          <button className="button button-primary" onClick={() => setShowAddForm(true)}>
            Create New Article
          </button>
        )}
      </div>

      {showAddForm && (
        <AddArticleForm 
          onSuccess={handleAddSuccess} 
          onCancel={() => setShowAddForm(false)} 
        />
      )}

      <DataTable 
        columns={columns} 
        data={articles} 
        emptyMessage="No articles found." 
      />
    </div>
  );
};

export default Knowledge;
