import { createRoot } from 'react-dom/client';
import App from './app/App';
import './styles/index.css';
import './styles/ionic-theme.css';
import { registerServiceWorker } from './lib/registerSW';

// Theme decision lives in ThemeProvider; default to dark before React mounts
// to avoid a flash of light styles.
try {
  const stored = localStorage.getItem('agri-sync.theme');
  if (stored !== 'light') document.documentElement.classList.add('dark');
} catch { document.documentElement.classList.add('dark'); }

createRoot(document.getElementById('root')!).render(<App />);

// Register the service worker after the app has mounted. No-op in dev,
// iframes, preview hosts, and native Capacitor shells.
void registerServiceWorker();
