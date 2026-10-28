import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import './services/theme.js'; // applies light / dark before the app paints
import './assets/style.css';
import './assets/dark.css';
import { showAlert } from './services/dialog.js';

// Every alert() in the app opens the in-app message window instead of the browser's popup.
window.alert = (message) => showAlert(message);

const app = createApp(App);
app.use(router);
app.mount('#app');
