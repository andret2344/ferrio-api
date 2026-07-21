import './styles/app.scss';
import * as bootstrap from 'bootstrap';
import axios from 'axios';
import './csrfProtection';
import './reports';
import './create';
import './sidebar';
import './holidayDetail';
import './languages';
import './users';
import './charts';

(window as any).axios = axios;
(window as any).bootstrap = bootstrap;

document.addEventListener('DOMContentLoaded', () => {
    const triggers = document.querySelectorAll<HTMLElement>('[data-bs-toggle="tooltip"]');
    triggers.forEach(el => bootstrap.Tooltip.getOrCreateInstance(el));
});
