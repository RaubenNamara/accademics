<template>
  <div class="min-h-screen bg-white">
    <div class="flex min-h-screen flex-col lg:flex-row">
      <!-- LEFT PANEL - Branding -->
      <section class="hidden lg:flex lg:w-1/2 relative items-center justify-center bg-gradient-to-br from-slate-900 via-slate-900 to-blue-950 p-12 overflow-hidden">
        <div class="absolute -top-24 -left-24 w-96 h-96 bg-white/10 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-32 -right-16 w-[28rem] h-[28rem] bg-blue-400/20 rounded-full blur-3xl"></div>
        <div class="absolute inset-0 opacity-[0.07]" style="background-image: radial-gradient(circle, #fff 1px, transparent 1px); background-size: 24px 24px;"></div>

        <div class="relative max-w-lg text-white">
          <div class="mb-10 flex items-center gap-4">
            <div class="w-16 h-16 rounded-2xl bg-white/10 backdrop-blur flex items-center justify-center ring-1 ring-white/20 p-2 flex-shrink-0">
              <img :src="schoolLogo" alt="St. Mark's College Namagoma" class="w-full h-full object-contain">
            </div>
            <div>
              <p class="text-xs uppercase tracking-widest text-blue-200 font-semibold">St. Mark's College Namagoma</p>
              <h1 class="text-2xl font-bold">Accademics</h1>
            </div>
          </div>

          <h2 class="text-3xl font-bold leading-tight mb-4">
            One platform for the whole school
          </h2>
          <p class="text-blue-100 text-lg mb-10">
            Manage students, staff, timetables, and academic performance in one place.
          </p>

          <ul class="space-y-4">
            <li v-for="f in features" :key="f.text" class="flex items-center gap-3">
              <span class="w-9 h-9 rounded-lg bg-white/10 flex items-center justify-center flex-shrink-0">
                <i :class="['fas', f.icon, 'text-sm']"></i>
              </span>
              <span class="text-blue-50">{{ f.text }}</span>
            </li>
          </ul>
        </div>
      </section>

      <!-- RIGHT PANEL - Login Form -->
      <section class="flex flex-1 items-center justify-center px-4 py-12 sm:px-6 lg:px-8 bg-slate-50 lg:bg-white">
        <div class="w-full max-w-md">
          <!-- Mobile Logo -->
          <div class="text-center lg:hidden mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-xl bg-blue-600 shadow-lg mb-4 p-2">
              <img :src="schoolLogo" alt="St. Mark's College Namagoma" class="w-full h-full object-contain">
            </div>
            <h1 class="text-2xl font-bold text-slate-900">Accademics</h1>
            <p class="text-sm text-slate-500">St. Mark's College Namagoma</p>
          </div>

          <div class="bg-white rounded-2xl shadow-lg border border-slate-200 p-8">
            <div class="text-center mb-8 hidden lg:block">
              <h2 class="text-2xl font-semibold text-slate-900 mb-2">Welcome back</h2>
              <p class="text-slate-600">Sign in to continue to Accademics</p>
            </div>
            <div class="text-center mb-8 lg:hidden">
              <h2 class="text-xl font-semibold text-slate-900 mb-1">Sign in</h2>
              <p class="text-sm text-slate-600">to continue to Accademics</p>
            </div>

            <form @submit.prevent="login" class="space-y-5">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Email</label>
                <div class="relative">
                  <i class="fas fa-envelope absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                  <input
                    ref="emailInput"
                    v-model.trim="email"
                    type="email"
                    required
                    autocomplete="email"
                    autofocus
                    placeholder="you@school.com"
                    class="w-full pl-10 pr-4 py-3 rounded-lg border border-slate-300 text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                  >
                </div>
              </div>

              <div>
                <div class="flex items-center justify-between mb-1.5">
                  <label class="text-sm font-medium text-slate-700">Password</label>
                  <button
                    type="button"
                    @click="showForgotHint = !showForgotHint"
                    class="text-sm font-medium text-blue-600 hover:text-blue-700 transition-colors"
                  >
                    Forgot password?
                  </button>
                </div>
                <div class="relative">
                  <i class="fas fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                  <input
                    v-model="password"
                    :type="showPassword ? 'text' : 'password'"
                    required
                    autocomplete="current-password"
                    placeholder="Enter your password"
                    class="w-full pl-10 pr-11 py-3 rounded-lg border border-slate-300 text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                  >
                  <button
                    type="button"
                    @click="showPassword = !showPassword"
                    :aria-label="showPassword ? 'Hide password' : 'Show password'"
                    class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 transition-colors"
                  >
                    <i :class="showPassword ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                  </button>
                </div>

                <transition name="fade">
                  <p v-if="showForgotHint" class="mt-2 text-sm text-slate-500">
                    Contact your school administrator to reset your password.
                  </p>
                </transition>
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
                  class="flex items-start gap-2.5 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"
                >
                  <i class="fas fa-circle-exclamation mt-0.5"></i>
                  <span>{{ error }}</span>
                </div>
              </transition>

              <button
                type="submit"
                :disabled="loading"
                class="w-full py-3 px-4 rounded-lg bg-blue-600 text-white font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm hover:shadow"
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
          </div>

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
const showForgotHint = ref(false);
const rememberMe = ref(true);
const emailInput = ref(null);

const features = [
  { icon: 'fa-user-graduate', text: 'Student records & digital e-files' },
  { icon: 'fa-calendar-alt', text: 'Class timetables & scheduling' },
  { icon: 'fa-users-cog', text: 'HR, payroll & leave management' },
  { icon: 'fa-chart-line', text: 'Academic performance tracking' }
];

const router = useRouter();

onMounted(() => {
  const savedEmail = localStorage.getItem('remembered_email');
  if (savedEmail) {
    email.value = savedEmail;
    rememberMe.value = true;
    emailInput.value?.focus();
  }
});

const login = async () => {
  loading.value = true;
  error.value = '';

  try {
    const result = await authAPI.login(email.value, password.value);

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
      error.value = result?.data?.message || result?.message || 'Login failed';
    }
  } catch (err) {
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
