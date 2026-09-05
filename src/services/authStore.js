import { reactive } from 'vue';
import {
  normalizeRole,
  canAccessRoute,
  getDefaultRoute,
  getUserRole
} from '../config/accessControl.js';

const state = reactive({
  token: localStorage.getItem('token'),
  user: JSON.parse(localStorage.getItem('user') || 'null')
});

const authStore = {
  get token() {
    return state.token;
  },

  get user() {
    return state.user;
  },

  setAuth(token, user) {
    state.token = token;
    state.user = user;
    localStorage.setItem('token', token);
    localStorage.setItem('user', JSON.stringify(user));
  },

  clearAuth() {
    state.token = null;
    state.user = null;
    localStorage.removeItem('token');
    localStorage.removeItem('user');
  },

  isAuthenticated() {
    return !!state.token;
  },

  hasRole(role) {
    return getUserRole(state.user) === normalizeRole(role);
  },

  hasAnyRole(roles) {
    const current = getUserRole(state.user);
    return roles.some((r) => current === normalizeRole(r));
  },

  getRole() {
    return getUserRole(state.user);
  },

  canAccess(path) {
    return canAccessRoute(path, getUserRole(state.user));
  },

  getHomeRoute() {
    return getDefaultRoute(getUserRole(state.user));
  },

  getUser() {
    return state.user;
  }
};

export const setAuth = authStore.setAuth.bind(authStore);
export const clearAuth = authStore.clearAuth.bind(authStore);

export default authStore;