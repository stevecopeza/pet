import React, { useEffect, useState } from 'react';
import { ActivityLog } from '../types';
import Feed from './Feed';
import { DataTable, Column } from './DataTable';

const Activity = () => {
  const [logs, setLogs] = useState<ActivityLog[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchLogs = async () => {
      try {
        const response = await fetch(`${window.petSettings.apiUrl}/activity?limit=100`, {
          headers: {
            'X-WP-Nonce': window.petSettings.nonce,
          },
        });

        if (!response.ok) {
          throw new Error('Failed to fetch activity logs');
        }

        const data = await response.json();
        setLogs(data);
      } catch (err) {
        setError(err instanceof Error ? err.message : 'An unknown error occurred');
      } finally {
        setLoading(false);
      }
    };

    fetchLogs();
  }, []);

  const columns: Column<ActivityLog>[] = [
    { key: 'createdAt', header: 'Date/Time', render: (val) => val },
    { key: 'type', header: 'Type', render: (val) => <span style={{ textTransform: 'uppercase', fontSize: '11px', fontWeight: 'bold', padding: '2px 6px', background: '#eee', borderRadius: '3px' }}>{val}</span> },
    { key: 'description', header: 'Description', render: (val) => val },
    { key: 'userId', header: 'User ID', render: (val) => val || '-' },
    { 
      key: 'relatedEntityType', 
      header: 'Related Entity', 
      render: (val, item) => val ? `${val} #${item.relatedEntityId}` : '-' 
    },
  ];

  if (loading) return <div>Loading activity feed...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;

  return (
    <div className="pet-activity">
      <Feed />
      <h2 style={{ marginTop: '30px' }}>Activity Log</h2>
      <p>Recent system activity.</p>
      
      <DataTable 
        columns={columns} 
        data={logs} 
        emptyMessage="No activity recorded yet." 
      />
    </div>
  );
};

export default Activity;
