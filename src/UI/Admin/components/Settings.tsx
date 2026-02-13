import React, { useEffect, useState } from 'react';
import { Setting } from '../types';
import { DataTable, Column } from './DataTable';
import SchemaManagement from './SchemaManagement';
import Calendars from './Calendars';
import SlaDefinitions from './SlaDefinitions';

// Extended interface for UI that includes id
interface SettingWithId extends Setting {
  id: string;
}

const Settings = () => {
  const [activeTab, setActiveTab] = useState<'general' | 'schemas' | 'calendars' | 'slas'>('general');
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

  return (
    <div className="pet-settings">
      <div style={{ marginBottom: '20px', borderBottom: '1px solid #ddd' }}>
        <button
          onClick={() => setActiveTab('general')}
          style={{
            padding: '10px 20px',
            border: 'none',
            background: activeTab === 'general' ? '#fff' : 'transparent',
            borderBottom: activeTab === 'general' ? '2px solid #007cba' : 'none',
            cursor: 'pointer',
            fontWeight: activeTab === 'general' ? 'bold' : 'normal',
            color: activeTab === 'general' ? '#000' : '#555',
            fontSize: '14px'
          }}
        >
          General Settings
        </button>
        <button
          onClick={() => setActiveTab('schemas')}
          style={{
            padding: '10px 20px',
            border: 'none',
            background: activeTab === 'schemas' ? '#fff' : 'transparent',
            borderBottom: activeTab === 'schemas' ? '2px solid #007cba' : 'none',
            cursor: 'pointer',
            fontWeight: activeTab === 'schemas' ? 'bold' : 'normal',
            color: activeTab === 'schemas' ? '#000' : '#555',
            fontSize: '14px'
          }}
        >
          Schemas & Malleable Fields
        </button>
        <button
          onClick={() => setActiveTab('calendars')}
          style={{
            padding: '10px 20px',
            border: 'none',
            background: activeTab === 'calendars' ? '#fff' : 'transparent',
            borderBottom: activeTab === 'calendars' ? '2px solid #007cba' : 'none',
            cursor: 'pointer',
            fontWeight: activeTab === 'calendars' ? 'bold' : 'normal',
            color: activeTab === 'calendars' ? '#000' : '#555',
            fontSize: '14px'
          }}
        >
          Calendars
        </button>
        <button
          onClick={() => setActiveTab('slas')}
          style={{
            padding: '10px 20px',
            border: 'none',
            background: activeTab === 'slas' ? '#fff' : 'transparent',
            borderBottom: activeTab === 'slas' ? '2px solid #007cba' : 'none',
            cursor: 'pointer',
            fontWeight: activeTab === 'slas' ? 'bold' : 'normal',
            color: activeTab === 'slas' ? '#000' : '#555',
            fontSize: '14px'
          }}
        >
          SLA Definitions
        </button>
      </div>

      {activeTab === 'general' && (
        <>
          <h2>System Settings</h2>
          <p>Configure global plugin settings.</p>
          
          {loading && <div>Loading settings...</div>}
          {error && <div style={{ color: 'red' }}>Error: {error}</div>}
          
          {!loading && !error && (
            <DataTable 
              columns={columns} 
              data={settings} 
              emptyMessage="No settings defined." 
            />
          )}
          
          <div style={{ marginTop: '20px', padding: '15px', background: '#f0f0f1', border: '1px solid #ccd0d4' }}>
            <h3>Note</h3>
            <p>These settings are stored in the database and affect plugin behavior globally.</p>
          </div>
        </>
      )}

      {activeTab === 'schemas' && <SchemaManagement />}
      {activeTab === 'calendars' && <Calendars />}
      {activeTab === 'slas' && <SlaDefinitions />}
    </div>
  );
};

export default Settings;
