import React, { useEffect, useState } from 'react';
import { DataTable, Column } from './DataTable';

interface ConversationSummary {
  id: string; // Mapped from uuid
  uuid: string;
  context_type: string;
  context_id: string;
  subject: string;
  state: string;
  created_at: string;
}

const Conversations = () => {
  const [conversations, setConversations] = useState<ConversationSummary[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchConversations = async () => {
      try {
        setLoading(true);
        // @ts-ignore
        const apiUrl = window.petSettings?.apiUrl;
        // @ts-ignore
        const nonce = window.petSettings?.nonce;

        if (!apiUrl || !nonce) {
          setError('API settings missing');
          return;
        }

        const response = await fetch(`${apiUrl}/conversations/me?limit=50`, {
          headers: {
            'X-WP-Nonce': nonce,
          },
        });

        if (!response.ok) {
          throw new Error('Failed to fetch conversations');
        }

        const data = await response.json();
        const mappedData = data.map((item: any) => ({
            ...item,
            id: item.uuid
        }));
        setConversations(mappedData);
      } catch (err) {
        setError(err instanceof Error ? err.message : 'An unknown error occurred');
      } finally {
        setLoading(false);
      }
    };

    fetchConversations();
  }, []);

  const columns: Column<ConversationSummary>[] = [
    { key: 'subject', header: 'Subject', render: (val, item) => (
      <a href={`admin.php?page=pet-conversations&id=${item.uuid}`} onClick={(e) => {
          // Ideally we should have a router here, but for now let's just use the href if we are not in a SPA fully
          // Actually, if we want to stay in the SPA, we need a way to navigate.
          // But ConversationPanel takes contextType/Id props.
          // Maybe we just link to the context?
          // For now let's just show the subject.
          e.preventDefault();
          alert('To be implemented: Open conversation ' + item.uuid);
      }} style={{ fontWeight: 'bold' }}>{val}</a>
    )},
    { key: 'context_type', header: 'Context', render: (val, item) => `${val} #${item.context_id}` },
    { key: 'state', header: 'Status', render: (val) => (
      <span className={`pet-status-badge status-${val}`}>{val}</span>
    )},
    { key: 'created_at', header: 'Created', render: (val) => new Date(val).toLocaleString() },
  ];

  if (loading) return <div>Loading conversations...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;

  return (
    <div className="pet-conversations">
      <h2>My Conversations</h2>
      <p>Recent conversations you are involved in.</p>
      
      <DataTable 
        columns={columns} 
        data={conversations} 
        emptyMessage="No conversations found." 
      />
    </div>
  );
};

export default Conversations;
