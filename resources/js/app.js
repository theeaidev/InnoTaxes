import './bootstrap';
import 'bootstrap';
import '@coreui/coreui';

import Alpine from 'alpinejs';
import registerAeatRequestHistory from './aeat/fiscal-data-history';

window.Alpine = Alpine;

registerAeatRequestHistory(Alpine);

Alpine.start();
