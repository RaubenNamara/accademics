import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import './services/theme.js'; // applies light / dark before the app paints
import './assets/style.css';
import './assets/dark.css';

const app = createApp(App);
app.use(router);
app.mount('#app');
