import React, { useEffect, useState } from 'react';
import { Customer } from '../types';
import { DataTable, Column } from './DataTable';
import AddCustomerForm from './AddCustomerForm';

const Customers = () => {
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);

  const fetchCustomers = async () => {
    try {
      setLoading(true);
      const response = await fetch(`${window.petSettings.apiUrl}/customers`, {
        headers: {
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch customers');
      }

      const data = await response.json();
      setCustomers(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchCustomers();
  }, []);

  const handleAddSuccess = () => {
    setShowAddForm(false);
    fetchCustomers();
  };

  const columns: Column<Customer>[] = [
    { key: 'id', header: 'ID' },
    { key: 'name', header: 'Customer Name', render: (val) => <strong>{val}</strong> },
    { key: 'contactEmail', header: 'Email' },
    { key: 'createdAt', header: 'Created At' },
    { key: 'archivedAt', header: 'Archived At', render: (val) => val || '-' },
  ];

  if (loading && !customers.length) return <div>Loading customers...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;

  return (
    <div className="pet-customers">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h2>Customers</h2>
        {!showAddForm && (
          <button className="button button-primary" onClick={() => setShowAddForm(true)}>
            Add New Customer
          </button>
        )}
      </div>

      {showAddForm && (
        <AddCustomerForm 
          onSuccess={handleAddSuccess} 
          onCancel={() => setShowAddForm(false)} 
        />
      )}

      <DataTable 
        columns={columns} 
        data={customers} 
        emptyMessage="No customers found." 
      />
    </div>
  );
};

export default Customers;
