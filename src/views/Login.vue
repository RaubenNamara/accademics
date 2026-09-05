<template>
  <div class="min-h-screen bg-white">
    <div class="flex min-h-screen flex-col lg:flex-row">
      <!-- LEFT PANEL - Branding -->
      <section class="hidden lg:flex lg:w-1/2 items-center justify-center bg-gradient-to-br from-blue-50 to-indigo-50 p-12">
        <div class="max-w-lg text-center">
          <div class="mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-blue-600 shadow-lg mb-6">
              <i class="fas fa-school text-3xl text-white"></i>
            </div>
            <h1 class="text-4xl font-bold text-slate-900 mb-3">Accademics</h1>
            <p class="text-lg text-slate-600">School Management System</p>
          </div>

          <h2 class="text-2xl font-semibold text-slate-800 mb-4">
            Empowering Education Through Innovation
          </h2>
          <p class="text-slate-600">
            Streamline your school's operations with our comprehensive HR and academic management platform.
          </p>
        </div>
      </section>

      <!-- RIGHT PANEL - Login Form -->
      <section class="flex flex-1 items-center justify-center px-4 py-12 sm:px-6 lg:px-8">
        <div class="w-full max-w-md">
          <!-- Mobile Logo -->
          <div class="text-center lg:hidden mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-xl bg-blue-600 shadow-lg mb-4">
              <i class="fas fa-school text-2xl text-white"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-900">Accademics</h1>
          </div>

          <div class="text-center mb-8">
            <img
              :src="schoolLogo"
              alt="St. Mark's College Namagoma"
              class="mx-auto mb-6 h-16 w-auto object-contain"
            >
            <h2 class="text-2xl font-semibold text-slate-900 mb-2">Sign in</h2>
            <p class="text-slate-600">to continue to Accademics</p>
          </div>

          <form @submit.prevent="login" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
              <input
                v-model.trim="email"
                type="email"
                required
                autocomplete="email"
                placeholder="Enter your email"
                class="w-full px-4 py-3 rounded-lg border border-slate-300 text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
              >
            </div>

            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="text-sm font-medium text-slate-700">Password</label>
                <a href="#" class="text-sm font-medium text-blue-600 hover:text-blue-700 transition-colors">
                  Forgot password?
                </a>
              </div>
              <div class="relative">
                <input
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  required
                  autocomplete="current-password"
                  placeholder="Enter your password"
                  class="w-full px-4 py-3 rounded-lg border border-slate-300 text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                >
                <button
                  type="button"
                  @click="showPassword = !showPassword"
                  class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 transition-colors"
                >
                  <i :class="showPassword ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                </button>
              </div>
            </div>

            <div class="flex items-center">
              <input
                v-model="rememberMe"
                type="checkbox"
                id="remember"
                class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
              >
              <label for="remember" class="ml-2 text-sm text-slate-600">Remember me</label>
            </div>

            <transition name="fade">
              <div
                v-if="error"
                class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-600"
              >
                {{ error }}
              </div>
            </transition>

            <button
              type="submit"
              :disabled="loading"
              class="w-full py-3 px-4 rounded-lg bg-blue-600 text-white font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span v-if="!loading">Sign in</span>
              <span v-else class="flex items-center justify-center gap-2">
                <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Signing in...
              </span>
            </button>
          </form>

          <p class="mt-8 text-center text-sm text-slate-500">
            © {{ new Date().getFullYear() }} Accademics. All rights reserved.
          </p>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { authAPI } from '../services/api.js';
import authStore, { setAuth } from '../services/authStore.js';
import schoolLogo from '../assets/logo.png';

const email = ref('');
const password = ref('');
const loading = ref(false);
const error = ref('');
const showPassword = ref(false);
const rememberMe = ref(true);

const router = useRouter();

onMounted(() => {
  const savedEmail = localStorage.getItem('remembered_email');
  if (savedEmail) {
    email.value = savedEmail;
    rememberMe.value = true;
  }
});

const login = async () => {
  loading.value = true;
  error.value = '';

  try {
    console.log('Attempting login with:', email.value);
    const result = await authAPI.login(email.value, password.value);
    console.log('Login result:', result);

    if (result?.success) {
      const token = result.data?.token || result.token;
      const user = result.data?.user || result.user;

      setAuth(token, user);

      if (rememberMe.value) {
        localStorage.setItem('remembered_email', email.value);
      } else {
        localStorage.removeItem('remembered_email');
      }

      router.push(authStore.getHomeRoute());
    } else {
      console.log('Login failed:', result);
      error.value = result?.data?.message || result?.message || 'Login failed';
    }
  } catch (err) {
    console.error('Login error:', err);
    console.error('Error response:', err?.response);
    error.value =
      err?.response?.data?.message ||
      'Login failed. Please check your details and try again.';
  } finally {
    loading.value = false;
  }
};
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
