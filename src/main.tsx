import { createRoot } from 'react-dom/client';
import App from './app/App';
import './i18n';
import './styles/index.css';

document.documentElement.classList.add('dark');

createRoot(document.getElementById('root')!).render(<App />);
