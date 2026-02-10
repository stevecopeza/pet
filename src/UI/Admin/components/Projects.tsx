import React, { useEffect, useState } from 'react';
import { Project } from '../types';
import { DataTable, Column } from './DataTable';
import AddProjectForm from './AddProjectForm';

const Projects = () => {
  const [projects, setProjects] = useState<Project[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);

  const fetchProjects = async () => {
    try {
      setLoading(true);
      const response = await fetch(`${window.petSettings.apiUrl}/projects`, {
        headers: {
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch projects');
      }

      const data = await response.json();
      setProjects(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchProjects();
  }, []);

  const handleAddSuccess = () => {
    setShowAddForm(false);
    fetchProjects();
  };

  const columns: Column<Project>[] = [
    { key: 'id', header: 'ID' },
    { key: 'name', header: 'Project Name', render: (_, item) => <strong>{item.name}</strong> },
    { key: 'customerId', header: 'Customer ID' },
    { key: 'soldHours', header: 'Sold Hours' },
    { key: 'tasks', header: 'Tasks', render: (_, item) => <span>{item.tasks.length} tasks</span> },
  ];

  if (loading && !projects.length) return <div>Loading projects...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;

  return (
    <div className="pet-projects">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h2>Delivery (Projects)</h2>
        {!showAddForm && (
          <button className="button button-primary" onClick={() => setShowAddForm(true)}>
            Add New Project
          </button>
        )}
      </div>

      {showAddForm && (
        <AddProjectForm 
          onSuccess={handleAddSuccess} 
          onCancel={() => setShowAddForm(false)} 
        />
      )}

      <DataTable 
        columns={columns} 
        data={projects} 
        emptyMessage="No projects found." 
      />
    </div>
  );
};

export default Projects;
