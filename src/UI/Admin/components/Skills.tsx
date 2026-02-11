import React, { useEffect, useState } from 'react';
import { DataTable, Column } from './DataTable';
import SkillForm from './SkillForm';

interface Skill {
  id: number;
  name: string;
  description: string;
  capability_id: number;
}

const Skills = () => {
  const [skills, setSkills] = useState<Skill[]>([]);
  const [loading, setLoading] = useState(true);
  const [showAddForm, setShowAddForm] = useState(false);

  const fetchSkills = async () => {
    try {
      setLoading(true);
      // @ts-ignore
      const response = await fetch(`${window.petSettings.apiUrl}/skills`, {
        headers: {
          // @ts-ignore
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (response.ok) {
        const data = await response.json();
        setSkills(data);
      }
    } catch (err) {
      console.error('Failed to fetch skills', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchSkills();
  }, []);

  const columns: Column<Skill>[] = [
    { key: 'name', header: 'Skill Name' },
    { key: 'description', header: 'Description' },
    // Add capability name if available, for now just ID
    { key: 'capability_id', header: 'Capability ID' }, 
  ];

  if (showAddForm) {
      return (
          <div>
              <div style={{ marginBottom: '20px' }}>
                  <button 
                      className="button" 
                      onClick={() => setShowAddForm(false)}
                  >
                      &larr; Back to Skills
                  </button>
              </div>
              <SkillForm 
                  onSuccess={() => {
                      setShowAddForm(false);
                      fetchSkills();
                  }} 
                  onCancel={() => setShowAddForm(false)} 
              />
          </div>
      );
  }

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h3>Skills Library</h3>
        <button className="button button-primary" onClick={() => setShowAddForm(true)}>
            Add Skill
        </button>
      </div>

      <DataTable
        data={skills}
        columns={columns}
        loading={loading}
        emptyMessage="No skills defined yet."
      />
    </div>
  );
};

export default Skills;
