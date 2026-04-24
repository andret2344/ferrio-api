import './styles/app.scss';
import * as bootstrap from 'bootstrap';
import axios from 'axios';
import './reports';

(window as any).axios = axios;
(window as any).bootstrap = bootstrap;
