import React, { useEffect, useState } from 'react';
import { Employee } from '../types';
import { DataTable, Column } from './DataTable';
import AddEmployeeForm from './AddEmployeeForm';

const Employees = () => {
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);

  const fetchEmployees = async () => {
    try {
      setLoading(true);
      const response = await fetch(`${window.petSettings.apiUrl}/employees`, {
        headers: {
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
    fetchEmployees();
  }, []);

  const handleAddSuccess = () => {
    setShowAddForm(false);
    fetchEmployees();
  };

  const columns: Column<Employee>[] = [
    { key: 'id', header: 'ID' },
    { key: 'wpUserId', header: 'WP User ID' },
    { key: 'firstName', header: 'First Name', render: (val, item) => <strong>{val} {item.lastName}</strong> },
    { key: 'email', header: 'Email' },
    { key: 'createdAt', header: 'Created At' },
    { key: 'archivedAt', header: 'Archived At', render: (val) => val || '-' },
  ];

  if (loading && !employees.length) return <div>Loading employees...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;

  return (
    <div className="pet-employees">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h2>People (Employees)</h2>
        {!showAddForm && (
          <button className="button button-primary" onClick={() => setShowAddForm(true)}>
            Add New Employee
          </button>
        )}
      </div>

      {showAddForm && (
        <AddEmployeeForm 
          onSuccess={handleAddSuccess} 
          onCancel={() => setShowAddForm(false)} 
        />
      )}

      <DataTable 
        columns={columns} 
        data={employees} 
        emptyMessage="No employees found." 
      />
    </div>
  );
};

export default Employees;
