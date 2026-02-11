import React, { useEffect, useState } from 'react';
import { Employee } from '../types';
import { DataTable, Column } from './DataTable';
import EmployeeForm from './EmployeeForm';
import Teams from './Teams';

const Employees = () => {
  const [activeTab, setActiveTab] = useState<'org' | 'teams' | 'people' | 'kpis'>('people');
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingEmployee, setEditingEmployee] = useState<Employee | null>(null);
  const [selectedIds, setSelectedIds] = useState<(string | number)[]>([]);
  const [activeSchema, setActiveSchema] = useState<any | null>(null);

  const fetchSchema = async () => {
    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/schemas/employee?status=active`, {
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

  const fetchEmployees = async () => {
    try {
      setLoading(true);
      // @ts-ignore
      const response = await fetch(`${window.petSettings.apiUrl}/employees`, {
        headers: {
          // @ts-ignore
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch employees');
      }

      const data = await response.json();
      setEmployees(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (activeTab === 'people') {
      fetchEmployees();
      fetchSchema();
    }
  }, [activeTab]);

  const handleFormSuccess = () => {
    setShowAddForm(false);
    setEditingEmployee(null);
    fetchEmployees();
  };

  const handleEdit = (employee: Employee) => {
    setEditingEmployee(employee);
    setShowAddForm(true);
  };

  const handleArchive = async (id: number) => {
    if (!confirm('Are you sure you want to archive this employee?')) return;

    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/employees/${id}`, {
        method: 'DELETE',
        headers: {
          'X-WP-Nonce': nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to archive employee');
      }

      fetchEmployees();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Failed to archive');
    }
  };

  const handleBulkArchive = async () => {
    if (!confirm(`Are you sure you want to archive ${selectedIds.length} employees?`)) return;

    // @ts-ignore
    const apiUrl = window.petSettings?.apiUrl;
    // @ts-ignore
    const nonce = window.petSettings?.nonce;

    // Process sequentially
    for (const id of selectedIds) {
      try {
        await fetch(`${apiUrl}/employees/${id}`, {
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
    fetchEmployees();
  };

  const columns: Column<Employee>[] = [
    { key: 'id', header: 'ID' },
    { 
      key: 'avatarUrl' as keyof Employee, 
      header: '', 
      render: (val: any) => val ? <img src={String(val)} alt="Avatar" style={{ width: '32px', height: '32px', borderRadius: '50%', verticalAlign: 'middle' }} /> : null 
    },
    { 
      key: 'firstName', 
      header: 'Name', 
      render: (val: any, item: Employee) => (
        <button 
          type="button"
          onClick={() => handleEdit(item)}
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
          {String(val)} {item.lastName}
        </button>
      )
    },
    { key: 'email', header: 'Email' },
    { key: 'status', header: 'Status', render: (val: any) => <span className={`status-badge status-${String(val).toLowerCase()}`}>{String(val)}</span> },
    { key: 'hireDate', header: 'Hire Date', render: (val: any) => val ? new Date(val).toLocaleDateString() : '-' },
    { key: 'managerId', header: 'Manager ID', render: (val: any) => val ? String(val) : '-' },
    // Add malleable fields if they exist in schema
    ...(activeSchema?.fields || activeSchema?.schema || []).map((field: any) => ({
      key: field.key as keyof Employee,
      header: field.label,
      render: (_: any, item: Employee) => {
        const value = item.malleableData?.[field.key];
        return value !== undefined && value !== null ? String(value) : '-';
      }
    })),
    { key: 'createdAt', header: 'Created At' },
    { key: 'archivedAt', header: 'Archived At', render: (val: any) => val ? <span style={{color: '#999'}}>{String(val)}</span> : '-' },
  ];

  return (
    <div className="pet-employees-container">
      <div style={{ marginBottom: '20px', borderBottom: '1px solid #eee' }}>
        <button 
          className={`button ${activeTab === 'org' ? 'button-primary' : ''}`}
          onClick={() => setActiveTab('org')}
          style={{ marginRight: '10px', marginBottom: '-1px', borderRadius: '4px 4px 0 0' }}
        >
          Org
        </button>
        <button 
          className={`button ${activeTab === 'teams' ? 'button-primary' : ''}`}
          onClick={() => setActiveTab('teams')}
          style={{ marginRight: '10px', marginBottom: '-1px', borderRadius: '4px 4px 0 0' }}
        >
          Teams
        </button>
        <button 
          className={`button ${activeTab === 'people' ? 'button-primary' : ''}`}
          onClick={() => setActiveTab('people')}
          style={{ marginRight: '10px', marginBottom: '-1px', borderRadius: '4px 4px 0 0' }}
        >
          People
        </button>
        <button 
          className={`button ${activeTab === 'kpis' ? 'button-primary' : ''}`}
          onClick={() => setActiveTab('kpis')}
          style={{ marginBottom: '-1px', borderRadius: '4px 4px 0 0' }}
        >
          KPIs
        </button>
      </div>

      {activeTab === 'org' && (
        <div className="pet-org">
          <h2>Organization Structure</h2>
          <p>Coming Soon</p>
        </div>
      )}

      {activeTab === 'teams' && (
        <Teams />
      )}

      {activeTab === 'kpis' && (
        <div className="pet-kpis">
          <h2>Staff KPIs</h2>
          <p>Coming Soon</p>
        </div>
      )}

      {activeTab === 'people' && (
        <div className="pet-employees">
          {loading && !employees.length ? <div>Loading employees...</div> :
          error ? <div style={{ color: 'red' }}>Error: {error}</div> :
          <>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
              <h2>People (Employees)</h2>
              {!showAddForm && (
                <button className="button button-primary" onClick={() => setShowAddForm(true)}>
                  Add New Employee
                </button>
              )}
            </div>

            {showAddForm && (
              <EmployeeForm 
                onSuccess={handleFormSuccess} 
                onCancel={() => { setShowAddForm(false); setEditingEmployee(null); }} 
                initialData={editingEmployee || undefined}
              />
            )}

            {selectedIds.length > 0 && (
              <div style={{ padding: '10px', background: '#e5f5fa', border: '1px solid #b5e1ef', marginBottom: '15px', display: 'flex', alignItems: 'center', gap: '15px' }}>
                <strong>{selectedIds.length} items selected</strong>
                <button className="button" onClick={handleBulkArchive}>Archive Selected</button>
              </div>
            )}

            <DataTable 
              columns={columns} 
              data={employees} 
              emptyMessage="No employees found."
              selection={{
                selectedIds,
                onSelectionChange: setSelectedIds
              }}
              actions={(item) => (
                <div style={{ display: 'flex', gap: '5px', justifyContent: 'flex-end' }}>
                  <button 
                    className={`button button-small`}
                    onClick={() => handleEdit(item)}
                  >
                    Edit
                  </button>
                  <button 
                    className={`button button-small button-link-delete`}
                    style={{ color: '#a00', borderColor: '#a00' }}
                    onClick={() => handleArchive(item.id)}
                  >
                    Archive
                  </button>
                </div>
              )}
            />
          </>
          }
        </div>
      )}
    </div>
  );
};

export default Employees;
