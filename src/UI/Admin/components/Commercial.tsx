import React, { useState } from 'react';
import Quotes from './Quotes';
import Leads from './Leads';

const Commercial = () => {
  const [activeTab, setActiveTab] = useState<'leads' | 'quotes'>('leads');

  return (
    <div>
      <div className="pet-tabs" style={{ display: 'flex', borderBottom: '1px solid #ccc', marginBottom: '20px' }}>
        <button
          className={`pet-tab ${activeTab === 'leads' ? 'active' : ''}`}
          onClick={() => setActiveTab('leads')}
          style={{
            padding: '10px 20px',
            border: 'none',
            background: activeTab === 'leads' ? '#fff' : '#f0f0f0',
            borderBottom: activeTab === 'leads' ? '2px solid #2271b1' : 'none',
            cursor: 'pointer',
            fontWeight: activeTab === 'leads' ? 'bold' : 'normal'
          }}
        >
          Leads
        </button>
        <button
          className={`pet-tab ${activeTab === 'quotes' ? 'active' : ''}`}
          onClick={() => setActiveTab('quotes')}
          style={{
            padding: '10px 20px',
            border: 'none',
            background: activeTab === 'quotes' ? '#fff' : '#f0f0f0',
            borderBottom: activeTab === 'quotes' ? '2px solid #2271b1' : 'none',
            cursor: 'pointer',
            fontWeight: activeTab === 'quotes' ? 'bold' : 'normal'
          }}
        >
          Quotes
        </button>
      </div>

      {activeTab === 'leads' ? <Leads /> : <Quotes />}
    </div>
  );
};

export default Commercial;
