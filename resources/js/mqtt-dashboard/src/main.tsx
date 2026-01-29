import React from 'react';
import ReactDOM from 'react-dom/client';
import { Dashboard } from './components/Dashboard';
import '../../../../resources/css/mqtt-dashboard.css';

const rootElement = document.getElementById('mqtt-dashboard-root');

if (!rootElement) {
  throw new Error('Root element not found');
}

ReactDOM.createRoot(rootElement).render(
  <React.StrictMode>
    <Dashboard />
  </React.StrictMode>
);
