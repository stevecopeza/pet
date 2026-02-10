import React, { useState, useEffect } from 'react';
import { Employee, Project } from '../types';

interface AddTimeEntryFormProps {
  onSuccess: () => void;
  onCancel: () => void;
}

const AddTimeEntryForm: React.FC<AddTimeEntryFormProps> = ({ onSuccess, onCancel }) => {
  const [employeeId, setEmployeeId] = useState('');
  const [projectId, setProjectId] = useState('');
  const [taskId, setTaskId] = useState('');
  const [start, setStart] = useState('');
  const [end, setEnd] = useState('');
  const [description, setDescription] = useState('');
  const [isBillable, setIsBillable] = useState(true);

  const [employees, setEmployees] = useState<Employee[]>([]);
  const [projects, setProjects] = useState<Project[]>([]);
  const [loading, setLoading] = useState(false);
  const [loadingData, setLoadingData] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchData = async () => {
      try {
        const [empRes, projRes] = await Promise.all([
          fetch(`${window.petSettings.apiUrl}/employees`, { headers: { 'X-WP-Nonce': window.petSettings.nonce } }),
          fetch(`${window.petSettings.apiUrl}/projects`, { headers: { 'X-WP-Nonce': window.petSettings.nonce } })
        ]);

        if (!empRes.ok || !projRes.ok) {
          throw new Error('Failed to fetch required data');
        }

        const empData = await empRes.json();
        const projData = await projRes.json();

        setEmployees(empData);
        setProjects(projData);

        if (empData.length > 0) setEmployeeId(empData[0].id.toString());
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Failed to load data');
      } finally {
        setLoadingData(false);
      }
    };

    fetchData();
  }, []);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!employeeId || !taskId || !start || !end) {
      setError('Please fill in all required fields');
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const response = await fetch(`${window.petSettings.apiUrl}/time-entries`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.petSettings.nonce,
        },
        body: JSON.stringify({ 
          employeeId: parseInt(employeeId, 10),
          taskId: parseInt(taskId, 10),
          start,
          end,
          isBillable,
          description
        }),
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || data.error || 'Failed to log time');
      }

      onSuccess();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  const selectedProject = projects.find(p => p.id.toString() === projectId);

  return (
    <div className="pet-form-container" style={{ padding: '20px', background: '#f9f9f9', border: '1px solid #ddd', marginBottom: '20px' }}>
      <h3>Log Time Entry</h3>
      {error && <div style={{ color: 'red', marginBottom: '10px' }}>{error}</div>}
      <form onSubmit={handleSubmit}>
        <div style={{ marginBottom: '10px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>Employee:</label>
          {loadingData ? (
            <div>Loading...</div>
          ) : (
            <select 
              value={employeeId} 
              onChange={(e) => setEmployeeId(e.target.value)}
              required
              style={{ width: '100%', maxWidth: '400px' }}
            >
              <option value="">Select an employee</option>
              {employees.map(e => (
                <option key={e.id} value={e.id}>{e.firstName} {e.lastName}</option>
              ))}
            </select>
          )}
        </div>

        <div style={{ marginBottom: '10px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>Project:</label>
          <select 
            value={projectId} 
            onChange={(e) => {
              setProjectId(e.target.value);
              setTaskId(''); // Reset task when project changes
            }}
            style={{ width: '100%', maxWidth: '400px' }}
          >
            <option value="">Select a project</option>
            {projects.map(p => (
              <option key={p.id} value={p.id}>{p.name}</option>
            ))}
          </select>
        </div>

        <div style={{ marginBottom: '10px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>Task:</label>
          <select 
            value={taskId} 
            onChange={(e) => setTaskId(e.target.value)}
            required
            disabled={!projectId}
            style={{ width: '100%', maxWidth: '400px' }}
          >
            <option value="">Select a task</option>
            {selectedProject?.tasks.map(t => (
              <option key={t.id} value={t.id}>{t.name}</option>
            ))}
          </select>
          {!projectId && <small style={{ display: 'block', color: '#666' }}>Select a project first</small>}
        </div>

        <div style={{ marginBottom: '10px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>Start Time:</label>
          <input 
            type="datetime-local" 
            value={start} 
            onChange={(e) => setStart(e.target.value)} 
            required 
            style={{ width: '100%', maxWidth: '400px' }}
          />
        </div>

        <div style={{ marginBottom: '10px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>End Time:</label>
          <input 
            type="datetime-local" 
            value={end} 
            onChange={(e) => setEnd(e.target.value)} 
            required 
            style={{ width: '100%', maxWidth: '400px' }}
          />
        </div>

        <div style={{ marginBottom: '10px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>Description:</label>
          <textarea 
            value={description} 
            onChange={(e) => setDescription(e.target.value)} 
            rows={3}
            style={{ width: '100%', maxWidth: '400px' }}
          />
        </div>

        <div style={{ marginBottom: '10px' }}>
          <label>
            <input 
              type="checkbox" 
              checked={isBillable} 
              onChange={(e) => setIsBillable(e.target.checked)} 
            />
            {' '}Billable
          </label>
        </div>

        <div style={{ marginTop: '15px' }}>
          <button 
            type="submit" 
            disabled={loading || loadingData}
            className="button button-primary"
            style={{ marginRight: '10px' }}
          >
            {loading ? 'Saving...' : 'Save Entry'}
          </button>
          <button 
            type="button" 
            onClick={onCancel}
            className="button"
            disabled={loading}
          >
            Cancel
          </button>
        </div>
      </form>
    </div>
  );
};

export default AddTimeEntryForm;
