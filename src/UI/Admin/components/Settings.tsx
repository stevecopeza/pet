import React, { useEffect, useState } from 'react';
import { Setting } from '../types';
import { DataTable, Column } from './DataTable';

// Extended interface for UI that includes id
interface SettingWithId extends Setting {
  id: string;
}

const Settings = () => {
  const [settings, setSettings] = useState<SettingWithId[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchSettings = async () => {
    try {
      const response = await fetch(`${window.petSettings.apiUrl}/settings`, {
        headers: {
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch settings');
      }

      const data: Setting[] = await response.json();
      // Add id property required by DataTable
      setSettings(data.map(s => ({ ...s, id: s.key })));
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchSettings();
  }, []);

  const handleValueChange = async (key: string, newValue: string) => {
    try {
      const response = await fetch(`${window.petSettings.apiUrl}/settings`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.petSettings.nonce,
        },
        body: JSON.stringify({ key, value: newValue }),
      });

      if (!response.ok) {
        throw new Error('Failed to update setting');
      }
      
      // Update local state
      setSettings(prev => prev.map(s => s.key === key ? { ...s, value: newValue } : s));

    } catch (err) {
      alert(err instanceof Error ? err.message : 'Update failed');
    }
  };

  const columns: Column<SettingWithId>[] = [
    { key: 'key', header: 'Key', render: (val) => <strong>{val as string}</strong> },
    { 
      key: 'value', 
      header: 'Value', 
      render: (val, item) => (
        <input 
          type="text" 
          defaultValue={val as string} 
          onBlur={(e) => {
            if (e.target.value !== val) {
              handleValueChange(item.key, e.target.value);
            }
          }}
          style={{ width: '100%', maxWidth: '300px' }}
        />
      )
    },
    { key: 'description', header: 'Description', render: (val) => <em style={{ color: '#666' }}>{val as string}</em> },
    { key: 'updatedAt', header: 'Last Updated', render: (val) => val as string || '-' },
  ];

  if (loading) return <div>Loading settings...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;

  return (
    <div className="pet-settings">
      <h2>System Settings</h2>
      <p>Configure global plugin settings.</p>
      
      <DataTable 
        columns={columns} 
        data={settings} 
        emptyMessage="No settings defined." 
      />
      
      <div style={{ marginTop: '20px', padding: '15px', background: '#f0f0f1', border: '1px solid #ccd0d4' }}>
        <h3>Environment Info</h3>
        <p><strong>API URL:</strong> {window.petSettings.apiUrl}</p>
      </div>
    </div>
  );
};

export default Settings;
