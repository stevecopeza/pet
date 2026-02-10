import React from 'react';
import Dashboard from './components/Dashboard';
import Projects from './components/Projects';
import Quotes from './components/Quotes';
import TimeEntries from './components/TimeEntries';
import Customers from './components/Customers';
import Employees from './components/Employees';
import Support from './components/Support';
import Knowledge from './components/Knowledge';
import Activity from './components/Activity';
import Settings from './components/Settings';

const App = () => {
  const currentPage = window.petSettings?.currentPage || 'pet-dashboard';

  const getPageTitle = (slug: string) => {
    switch (slug) {
      case 'pet-dashboard': return 'Overview';
      case 'pet-dashboards': return 'Dashboards';
      case 'pet-crm': return 'CRM';
      case 'pet-quotes-sales': return 'Quotes & Sales';
      case 'pet-delivery': return 'Delivery';
      case 'pet-time': return 'Time';
      case 'pet-support': return 'Support';
      case 'pet-knowledge': return 'Knowledge';
      case 'pet-people': return 'People';
      case 'pet-activity': return 'Activity';
      case 'pet-settings': return 'Settings';
      default: return 'PET';
    }
  };

  const renderContent = () => {
    switch (currentPage) {
      case 'pet-dashboard':
        return <Dashboard />;
      case 'pet-delivery':
        return <Projects />;
      case 'pet-quotes-sales':
        return <Quotes />;
      case 'pet-time':
        return <TimeEntries />;
      case 'pet-crm':
        return <Customers />;
      case 'pet-people':
        return <Employees />;
      case 'pet-support':
        return <Support />;
      case 'pet-knowledge':
        return <Knowledge />;
      case 'pet-activity':
        return <Activity />;
      case 'pet-settings':
        return <Settings />;
      default:
        return (
          <div className="pet-card" style={{ padding: '40px', textAlign: 'center', color: '#666' }}>
            <h2 style={{ marginTop: 0 }}>Coming Soon</h2>
            <p>The {getPageTitle(currentPage)} module is currently under development.</p>
          </div>
        );
    }
  };

  return (
    <div className="pet-admin-dashboard" style={{ padding: '20px', background: '#fff', marginTop: '20px' }}>
      <header style={{ marginBottom: '30px', borderBottom: '1px solid #eee', paddingBottom: '20px' }}>
        <h1 style={{ margin: 0 }}>PET - {getPageTitle(currentPage)}</h1>
        <p style={{ margin: '10px 0 0', color: '#666' }}>Welcome to the Project Estimation Tool.</p>
      </header>
      
      <main>
        {renderContent()}
      </main>
    </div>
  );
};

export default App;
