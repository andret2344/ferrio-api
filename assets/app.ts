import './styles/app.scss';
import * as bootstrap from 'bootstrap';
import axios from 'axios';
import './reports';
import './create';

(window as any).axios = axios;
(window as any).bootstrap = bootstrap;
