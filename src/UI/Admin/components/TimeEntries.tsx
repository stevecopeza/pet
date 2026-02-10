import React, { useEffect, useState } from 'react';
import { TimeEntry } from '../types';
import { DataTable, Column } from './DataTable';
import AddTimeEntryForm from './AddTimeEntryForm';

const TimeEntries = () => {
  const [entries, setEntries] = useState<TimeEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);

  const fetchEntries = async () => {
    try {
      setLoading(true);
      const response = await fetch(`${window.petSettings.apiUrl}/time-entries`, {
        headers: {
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch time entries');
      }

      const data = await response.json();
      setEntries(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchEntries();
  }, []);

  const handleAddSuccess = () => {
    setShowAddForm(false);
    fetchEntries();
  };

  const columns: Column<TimeEntry>[] = [
    { key: 'id', header: 'ID' },
    { key: 'employeeId', header: 'Employee' },
    { key: 'taskId', header: 'Task' },
    { key: 'start', header: 'Start' },
    { key: 'end', header: 'End' },
    { key: 'duration', header: 'Duration (m)' },
    { key: 'description', header: 'Description' },
    { key: 'billable', header: 'Billable', render: (_, item) => <span>{item.billable ? 'Yes' : 'No'}</span> },
  ];

  if (loading && !entries.length) return <div>Loading time entries...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;

  return (
    <div className="pet-time-entries">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h2>Time (Entries)</h2>
        {!showAddForm && (
          <button className="button button-primary" onClick={() => setShowAddForm(true)}>
            Log Time Entry
          </button>
        )}
      </div>

      {showAddForm && (
        <AddTimeEntryForm 
          onSuccess={handleAddSuccess} 
          onCancel={() => setShowAddForm(false)} 
        />
      )}

      <DataTable 
        columns={columns} 
        data={entries} 
        emptyMessage="No time entries found." 
      />
    </div>
  );
};

export default TimeEntries;
