<template>
  <div class="min-h-screen bg-white">
    <div class="flex min-h-screen flex-col lg:flex-row">
      <!-- Left: the product, alive -->
      <section class="hero relative hidden overflow-hidden px-12 py-11 lg:flex lg:w-[56%] lg:flex-col lg:justify-between xl:px-16">
        <div class="aurora" aria-hidden="true"><span class="blob b1"></span><span class="blob b2"></span><span class="blob b3"></span></div>
        <div class="hero-grid" aria-hidden="true"></div>
        <svg class="pulse-line" viewBox="0 0 1200 160" preserveAspectRatio="none" aria-hidden="true">
          <path pathLength="1000" d="M0 96 H250 l24 -52 l34 104 l30 -80 l22 28 H560 l24 -52 l34 104 l30 -80 l22 28 H870 l24 -52 l34 104 l30 -80 l22 28 H1200" />
        </svg>

        <header class="relative z-10 flex items-center gap-3">
          <SkoolLogo :size="32" light />
          <span class="glass-pill">
            <img v-if="schoolIsHome" :src="schoolLogo" alt="" class="h-5 w-5 object-contain"><School v-else class="h-4 w-4 text-emerald-200" />{{ schoolName || "St Mark's College Namagoma" }}
          </span>
        </header>

        <div class="relative z-10 max-w-[34rem]">
          <span class="eyebrow"><span class="live-dot"></span>Your school's pulse, live</span>
          <h1 class="mt-6 text-[3.5rem] font-bold leading-[1.03] tracking-tight text-white xl:text-[3.9rem]">
            Every lesson.<br>Every mark.<br><span class="shine">One heartbeat.</span>
          </h1>
          <p class="mt-6 max-w-md text-[1.05rem] leading-relaxed text-emerald-50/75">
            Staff, timetables, lesson plans, exams and report cards, with a portal for every teacher. All in one secure place.
          </p>
          <div class="mt-8 flex flex-wrap gap-2">
            <span v-for="f in features" :key="f.title" class="chip"><component :is="f.icon" class="h-4 w-4 text-emerald-300" />{{ f.title }}</span>
          </div>
        </div>

        <!-- Floating glimpses of the product -->
        <div class="float-stack hidden min-[1680px]:block" aria-hidden="true">
          <div class="glass-card float f1">
            <div class="flex items-center justify-between">
              <span class="card-label">Lessons taught today</span>
              <span class="badge-up"><TrendingUp class="h-3 w-3" />96%</span>
            </div>
            <p class="mt-1 text-2xl font-bold text-white">128<span class="text-sm font-medium text-emerald-100/60"> / 134</span></p>
            <div class="bars">
              <span v-for="(h, i) in bars" :key="i" :style="{ height: `${h}%`, animationDelay: `${0.6 + i * 0.08}s` }"></span>
            </div>
          </div>
          <div class="glass-card float f2">
            <span class="card-label">Time lost this term</span>
            <div class="mt-1 flex items-end justify-between gap-3">
              <p class="text-2xl font-bold text-white">-32%</p>
              <svg viewBox="0 0 90 32" class="h-8 w-24" fill="none"><path class="spark-path" pathLength="100" d="M2 6 C14 8 18 4 28 12 S44 14 52 20 S70 22 88 28" stroke="#6ee7b7" stroke-width="2.5" stroke-linecap="round" /></svg>
            </div>
          </div>
          <div class="glass-card float f3 flex items-center gap-3">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-400/20 text-emerald-200 ring-1 ring-emerald-300/30"><FileBadge class="h-4 w-4" /></span>
            <span>
              <span class="block text-sm font-semibold text-white">Report cards ready</span>
              <span class="block text-xs text-emerald-100/60">S4 A · 52 students</span>
            </span>
            <CircleCheck class="ml-2 h-5 w-5 text-emerald-300" />
          </div>
        </div>

        <footer class="relative z-10 flex flex-wrap items-center justify-between gap-3 text-xs text-emerald-50/55">
          <span>© {{ year }} Skool Monitor</span>
          <span class="flex flex-wrap gap-x-5 gap-y-1">
            <span v-for="p in promises" :key="p" class="flex items-center gap-1.5"><ShieldCheck class="h-3.5 w-3.5 text-emerald-300/80" />{{ p }}</span>
          </span>
        </footer>
      </section>

      <!-- Phones and tablets: a compact version of the hero -->
      <header class="hero relative overflow-hidden px-6 pb-14 pt-9 lg:hidden">
        <div class="aurora" aria-hidden="true"><span class="blob b1"></span><span class="blob b2"></span></div>
        <div class="relative z-10 flex flex-col items-center text-center">
          <SkoolLogo :size="30" light />
          <span class="glass-pill mt-4">
            <img v-if="schoolIsHome" :src="schoolLogo" alt="" class="h-5 w-5 object-contain"><School v-else class="h-4 w-4 text-emerald-200" />{{ schoolName || "St Mark's College Namagoma" }}
          </span>
          <p class="mt-4 text-2xl font-bold leading-tight text-white">Every lesson. Every mark.<br><span class="shine">One heartbeat.</span></p>
        </div>
      </header>

      <!-- Right: sign in -->
      <section class="form-side relative flex flex-1 flex-col items-center justify-center px-5 pb-10 sm:px-10 lg:py-10">
        <button
          type="button"
          class="theme-switch absolute right-4 top-4 z-20 lg:right-6 lg:top-6"
          :aria-label="theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'"
          :title="theme === 'dark' ? 'Light mode' : 'Dark mode'"
          @click="toggleTheme"
        >
          <Sun v-if="theme === 'dark'" class="h-[1.1rem] w-[1.1rem]" />
          <Moon v-else class="h-[1.1rem] w-[1.1rem]" />
        </button>
        <div class="relative z-10 -mt-8 w-full max-w-[26.5rem] lg:mt-0">
          <div class="form-card">
            <h2 class="text-[1.9rem] font-bold tracking-tight text-gray-900">Sign in</h2>
            <p class="mt-1.5 text-sm text-gray-500">Welcome back. Enter your details to open {{ schoolName || 'your school' }}.</p>

            <form class="mt-7 space-y-5" @submit.prevent="login">
              <!-- School code box hidden by the platform owner: the school comes from its sign-in link or this device. -->
              <p v-if="multiSchool && !showSchoolField && schoolCode && schoolName && !schoolIsHome" class="flex flex-wrap items-center gap-x-2 gap-y-1 rounded-xl bg-brand-50/60 px-3 py-2 text-sm text-gray-700 ring-1 ring-brand-100">
                <School class="h-4 w-4 text-brand-700" />Signing in to <strong class="text-gray-900">{{ schoolName }}</strong>
                <button type="button" class="ml-auto text-xs font-medium text-brand-700 hover:underline" @click="forgetSchool">Not your school?</button>
              </p>

              <div v-if="multiSchool && showSchoolField">
                <label for="login-school" class="mb-1.5 block text-sm font-medium text-gray-800">School code</label>
                <div class="field">
                  <span class="field-icon"><School class="h-4 w-4" /></span>
                  <input
                    id="login-school"
                    v-model.trim="schoolCode"
                    type="text"
                    autocomplete="organization"
                    autocapitalize="none"
                    spellcheck="false"
                    placeholder="e.g. stjohns"
                    class="field-input lowercase"
                    @change="checkSchool"
                  >
                </div>
                <p class="mt-1.5 text-xs" :class="schoolError ? 'text-red-600' : 'text-gray-500'">
                  <template v-if="schoolError">{{ schoolError }}</template>
                  <template v-else-if="schoolName && schoolCode">Signing in to <strong class="text-gray-800">{{ schoolName }}</strong></template>
                  <template v-else-if="schoolName">Leave empty to sign in to <strong class="text-gray-800">{{ schoolName }}</strong></template>
                </p>
              </div>

              <div>
                <label for="login-email" class="mb-1.5 block text-sm font-medium text-gray-800">Email or teacher code</label>
                <div class="field">
                  <span class="field-icon"><UserRound class="h-4 w-4" /></span>
                  <input
                    id="login-email"
                    ref="emailInput"
                    v-model.trim="email"
                    type="text"
                    required
                    autocomplete="username"
                    autocapitalize="none"
                    spellcheck="false"
                    autofocus
                    placeholder="you@school.com or TS-012"
                    class="field-input"
                  >
                </div>
              </div>

              <div>
                <div class="mb-1.5 flex items-center justify-between">
                  <label for="login-password" class="text-sm font-medium text-gray-800">Password</label>
                  <button type="button" class="text-sm font-medium text-brand-700 hover:text-brand-800" @click="showForgotHint = !showForgotHint">
                    Forgot password?
                  </button>
                </div>
                <div class="field">
                  <span class="field-icon"><LockKeyhole class="h-4 w-4" /></span>
                  <input
                    id="login-password"
                    v-model="password"
                    :type="showPassword ? 'text' : 'password'"
                    required
                    autocomplete="current-password"
                    placeholder="Enter your password"
                    class="field-input"
                  >
                  <button
                    type="button"
                    class="field-icon border-l border-r-0 hover:text-gray-700"
                    :aria-label="showPassword ? 'Hide password' : 'Show password'"
                    @click="showPassword = !showPassword"
                  >
                    <EyeOff v-if="showPassword" class="h-4 w-4" />
                    <Eye v-else class="h-4 w-4" />
                  </button>
                </div>
                <transition name="fade">
                  <p v-if="showForgotHint" class="mt-2 text-sm text-gray-500">Contact your school administrator to reset your password.</p>
                </transition>
              </div>

              <label class="flex items-center gap-2.5 text-sm text-gray-700">
                <input v-model="rememberMe" type="checkbox" class="h-4 w-4 rounded border-gray-300 accent-brand-600">
                Keep me signed in
              </label>

              <transition name="fade">
                <div v-if="error" class="flex items-start gap-2.5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                  <CircleAlert class="mt-0.5 h-4 w-4 flex-shrink-0" /><span>{{ error }}</span>
                </div>
              </transition>

              <button type="submit" :disabled="loading" class="submit-btn">
                <template v-if="!loading">Sign in<ArrowRight class="arrow h-4 w-4" /></template>
                <template v-else>
                  <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                  </svg>
                  Signing in…
                </template>
              </button>
            </form>

            <p class="mt-7 flex items-center justify-center gap-2 border-t border-gray-100 pt-5 text-center text-xs text-gray-500">
              <ShieldCheck class="h-4 w-4 text-brand-600" />Protected sign-in. Trouble? Ask your school administrator.
            </p>
          </div>

          <p class="mt-6 text-center text-xs text-gray-400 lg:hidden">© {{ year }} Skool Monitor</p>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
  ArrowRight, CalendarDays, CircleAlert, CircleCheck, ClipboardList, Eye, EyeOff, FileBadge, LockKeyhole, Moon, NotebookPen, School, ShieldCheck, Sun, TrendingUp, UserRound, UsersRound,
} from 'lucide-vue-next';
import { authAPI, platformAPI } from '../services/api.js';
import authStore, { setAuth } from '../services/authStore.js';
import schoolLogo from '../assets/logo.png';
import SkoolLogo from '../components/SkoolLogo.vue';
import { theme, toggleTheme } from '../services/theme.js';

const email = ref('');
const password = ref('');
const loading = ref(false);
const error = ref('');
const showPassword = ref(false);
const showForgotHint = ref(false);
const rememberMe = ref(true);
const emailInput = ref(null);
const year = new Date().getFullYear();

const features = [
  { icon: UsersRound, title: 'Staff & HR', text: 'Teaching and support staff, leave and departments.' },
  { icon: CalendarDays, title: 'Timetables', text: 'Clash-free timetables, generated and published.' },
  { icon: NotebookPen, title: 'Teacher portal', text: 'Lesson plans, marks, timetable and performance.' },
  { icon: ClipboardList, title: 'Exams & reports', text: 'AOIs and exams, weighted marks, report cards.' },
];
const promises = ['Secure access', 'Role-based permissions', 'Works on any device'];
// Heights of the little bar chart in the hero card (lessons taught per day).
const bars = [62, 78, 70, 88, 74, 92, 96];

const router = useRouter();

// Several schools on one install: the sign-in form asks which one (remembered on this device).
const multiSchool = ref(false);
// The platform owner can hide the School code box; staff then use their school's sign-in link (?school=code).
const showSchoolField = ref(false);
const route = useRoute();
const linkSchool = String(route.query.school || new URLSearchParams(window.location.search).get('school') || '').trim().toLowerCase();
const schoolCode = ref(linkSchool || localStorage.getItem('school_code') || '');
const schoolName = ref('');
const schoolError = ref('');
// The St Mark's crest belongs to the home school only.
const schoolIsHome = ref(true);

const checkSchool = async () => {
  schoolError.value = '';
  schoolCode.value = schoolCode.value.toLowerCase();
  try {
    const ctx = (await platformAPI.context(schoolCode.value)).data;
    multiSchool.value = multiSchool.value || ctx.multi_school;
    showSchoolField.value = !!ctx.show_school_field;
    schoolName.value = ctx.school?.name || '';
    schoolIsHome.value = ctx.school?.is_home !== false;
    return true;
  } catch (e) {
    schoolName.value = '';
    schoolError.value = e?.response?.data?.message || 'Could not check the school code';
    return false;
  }
};

onMounted(async () => {
  const savedEmail = localStorage.getItem('remembered_email');
  if (savedEmail) {
    email.value = savedEmail;
    rememberMe.value = true;
    emailInput.value?.focus();
  }
  if (!(await checkSchool()) && schoolCode.value) {
    // A remembered code that no longer exists: forget it and fall back to the default school.
    localStorage.removeItem('school_code');
    schoolCode.value = '';
    await checkSchool();
  } else if (linkSchool) {
    // Opened from a school's sign-in link: remember the school on this device.
    localStorage.setItem('school_code', linkSchool);
  }
});

const forgetSchool = async () => {
  localStorage.removeItem('school_code');
  schoolCode.value = '';
  if (route.query.school) router.replace({ path: '/login' });
  await checkSchool();
};

const login = async () => {
  loading.value = true;
  error.value = '';

  try {
    const result = await authAPI.login(email.value, password.value, multiSchool.value || linkSchool ? schoolCode.value : '');

    if (result?.success) {
      const token = result.data?.token || result.token;
      const user = result.data?.user || result.user;

      setAuth(token, user);
      if (user?.school?.code && !user.school.is_home) {
        localStorage.setItem('school_code', user.school.code);
      } else {
        localStorage.removeItem('school_code');
      }

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
/* ---------- Hero: deep green, drifting light, a grid and a heartbeat ---------- */
.hero {
  background:
    radial-gradient(900px 520px at 0% 0%, rgba(16, 185, 129, 0.28), transparent 60%),
    linear-gradient(140deg, #03200f 0%, #064e3b 48%, #065f46 100%);
  color: #fff;
  isolation: isolate;
}
.aurora { position: absolute; inset: 0; z-index: 0; pointer-events: none; }
.blob {
  position: absolute;
  border-radius: 9999px;
  filter: blur(80px);
  opacity: 0.5;
  animation: drift 18s ease-in-out infinite alternate;
}
.b1 { width: 34rem; height: 34rem; background: #059669; top: -12rem; left: -10rem; }
.b2 { width: 28rem; height: 28rem; background: #10b981; bottom: -12rem; right: 5%; animation-duration: 23s; }
.b3 { width: 20rem; height: 20rem; background: #a3e635; top: 38%; left: 46%; opacity: 0.18; animation-duration: 27s; }
@keyframes drift {
  to { transform: translate3d(4rem, 3rem, 0) scale(1.15); }
}
.hero-grid {
  position: absolute;
  inset: 0;
  z-index: 0;
  background-image:
    linear-gradient(rgba(255, 255, 255, 0.055) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255, 255, 255, 0.055) 1px, transparent 1px);
  background-size: 56px 56px;
  -webkit-mask-image: radial-gradient(ellipse at 30% 45%, #000 25%, transparent 75%);
  mask-image: radial-gradient(ellipse at 30% 45%, #000 25%, transparent 75%);
}
.pulse-line {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 17%;
  z-index: 0;
  width: 100%;
  height: 9rem;
}
.pulse-line path {
  fill: none;
  stroke: rgba(134, 239, 172, 0.7);
  stroke-width: 2.4;
  stroke-linecap: round;
  stroke-linejoin: round;
  vector-effect: non-scaling-stroke;
  stroke-dasharray: 1000;
  stroke-dashoffset: 1000;
  filter: drop-shadow(0 0 6px rgba(74, 222, 128, 0.85));
  animation: draw 7s ease-in-out infinite;
}
@keyframes draw {
  0% { stroke-dashoffset: 1000; opacity: 0; }
  8% { opacity: 1; }
  55% { stroke-dashoffset: 0; opacity: 1; }
  85% { opacity: 0.15; }
  100% { stroke-dashoffset: 0; opacity: 0; }
}

.glass-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  border-radius: 9999px;
  border: 1px solid rgba(255, 255, 255, 0.16);
  background: rgba(255, 255, 255, 0.08);
  padding: 0.35rem 0.8rem;
  font-size: 0.75rem;
  font-weight: 500;
  color: rgba(236, 253, 245, 0.92);
  backdrop-filter: blur(10px);
}
.eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 0.6rem;
  border-radius: 9999px;
  border: 1px solid rgba(134, 239, 172, 0.25);
  background: rgba(16, 185, 129, 0.1);
  padding: 0.4rem 0.95rem;
  font-size: 0.8rem;
  font-weight: 500;
  letter-spacing: 0.01em;
  color: #a7f3d0;
}
.live-dot {
  position: relative;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 9999px;
  background: #34d399;
}
.live-dot::after {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: 9999px;
  background: #34d399;
  animation: ping 1.8s cubic-bezier(0, 0, 0.2, 1) infinite;
}
@keyframes ping { 75%, 100% { transform: scale(2.6); opacity: 0; } }

.shine {
  background: linear-gradient(90deg, #6ee7b7 0%, #ecfccb 25%, #34d399 50%, #bef264 75%, #6ee7b7 100%);
  background-size: 200% auto;
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
  animation: shine 7s linear infinite;
}
@keyframes shine { to { background-position: 200% center; } }

.chip {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  border-radius: 0.85rem;
  border: 1px solid rgba(255, 255, 255, 0.12);
  background: rgba(255, 255, 255, 0.06);
  padding: 0.5rem 0.8rem;
  font-size: 0.82rem;
  font-weight: 500;
  color: rgba(240, 253, 244, 0.9);
  backdrop-filter: blur(8px);
  transition: background 0.2s ease, transform 0.2s ease;
}
.chip:hover { background: rgba(255, 255, 255, 0.11); transform: translateY(-1px); }

/* ---------- Floating product glimpses ---------- */
.float-stack {
  position: absolute;
  z-index: 5;
  right: 2.5rem;
  top: 50%;
  width: 17rem;
  transform: translateY(-58%);
}
.glass-card {
  border-radius: 1.25rem;
  border: 1px solid rgba(255, 255, 255, 0.15);
  background: linear-gradient(160deg, rgba(255, 255, 255, 0.14), rgba(255, 255, 255, 0.05));
  padding: 1rem 1.1rem;
  box-shadow: 0 24px 60px -24px rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(16px);
}
.glass-card + .glass-card { margin-top: 1rem; }
.f2 { margin-left: -3.5rem; margin-right: 3.5rem; }
.f3 { margin-left: 1.5rem; }
.float { animation: bob 7s ease-in-out infinite; }
.f2 { animation-delay: -2.3s; }
.f3 { animation-delay: -4.6s; }
@keyframes bob { 50% { transform: translateY(-10px); } }
.card-label { font-size: 0.72rem; font-weight: 500; color: rgba(209, 250, 229, 0.7); }
.badge-up {
  display: inline-flex;
  align-items: center;
  gap: 0.2rem;
  border-radius: 9999px;
  background: rgba(74, 222, 128, 0.18);
  padding: 0.1rem 0.45rem;
  font-size: 0.68rem;
  font-weight: 600;
  color: #a7f3d0;
}
.bars {
  display: flex;
  align-items: flex-end;
  gap: 0.35rem;
  height: 2.6rem;
  margin-top: 0.6rem;
}
.bars span {
  flex: 1;
  border-radius: 0.3rem 0.3rem 0.15rem 0.15rem;
  background: linear-gradient(180deg, #6ee7b7, rgba(16, 185, 129, 0.35));
  transform-origin: bottom;
  transform: scaleY(0);
  animation: grow 0.7s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
}
@keyframes grow { to { transform: scaleY(1); } }
.spark-path { stroke-dasharray: 100; stroke-dashoffset: 100; animation: line-in 1.6s 0.9s ease-out forwards; }
@keyframes line-in { to { stroke-dashoffset: 0; } }

/* ---------- Form side ---------- */
.form-side {
  background:
    radial-gradient(600px 300px at 100% 0%, rgba(16, 185, 129, 0.07), transparent 70%),
    radial-gradient(500px 300px at 0% 100%, rgba(16, 185, 129, 0.06), transparent 70%),
    #ffffff;
}
.form-card {
  border-radius: 1.75rem;
  border: 1px solid rgba(17, 24, 39, 0.06);
  background: #ffffff;
  padding: 2rem 1.6rem;
  box-shadow:
    0 1px 2px rgba(16, 24, 40, 0.04),
    0 30px 70px -30px rgba(6, 78, 59, 0.35);
}
@media (min-width: 640px) { .form-card { padding: 2.4rem 2.3rem; } }
@media (min-width: 1024px) {
  .form-card { border-color: transparent; box-shadow: none; padding-left: 0.5rem; padding-right: 0.5rem; background: transparent; }
}

.field {
  @apply flex items-stretch overflow-hidden rounded-2xl border border-gray-200 bg-gray-50/80 transition;
}
.field:hover { @apply border-gray-300; }
.field:focus-within {
  @apply border-brand-500 bg-white;
  box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.14);
}
.field-icon {
  @apply flex w-12 flex-shrink-0 items-center justify-center text-gray-400 transition-colors;
}
.field:focus-within .field-icon { @apply text-brand-600; }
.field-input {
  @apply min-w-0 flex-1 bg-transparent py-3.5 pr-3 text-[0.95rem] text-gray-900 outline-none placeholder:text-gray-400;
}

.submit-btn {
  position: relative;
  display: flex;
  width: 100%;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  overflow: hidden;
  border-radius: 1rem;
  background: linear-gradient(135deg, #10b981 0%, #059669 45%, #047857 100%);
  padding: 0.95rem 1rem;
  font-size: 0.95rem;
  font-weight: 600;
  color: #fff;
  box-shadow: 0 14px 30px -12px rgba(5, 150, 105, 0.65), inset 0 1px 0 rgba(255, 255, 255, 0.25);
  transition: transform 0.15s ease, box-shadow 0.2s ease, filter 0.2s ease;
}
.submit-btn::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(110deg, transparent 30%, rgba(255, 255, 255, 0.35) 50%, transparent 70%);
  transform: translateX(-120%);
  transition: transform 0.7s ease;
}
.submit-btn:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 18px 36px -12px rgba(5, 150, 105, 0.75), inset 0 1px 0 rgba(255, 255, 255, 0.25); }
.submit-btn:hover:not(:disabled)::after { transform: translateX(120%); }
.submit-btn:hover:not(:disabled) .arrow { transform: translateX(3px); }
.submit-btn:active:not(:disabled) { transform: translateY(0); filter: brightness(0.97); }
.submit-btn:focus-visible { outline: none; box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.3); }
.submit-btn:disabled { cursor: not-allowed; opacity: 0.65; }
.arrow { transition: transform 0.2s ease; }

.fade-enter-active,
.fade-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.fade-enter-from,
.fade-leave-to { opacity: 0; transform: translateY(-4px); }

/* ---------- Light / dark switch, and the dark form side ---------- */
.theme-switch {
  display: inline-flex;
  height: 2.5rem;
  width: 2.5rem;
  align-items: center;
  justify-content: center;
  border-radius: 9999px;
  border: 1px solid rgb(229 231 235);
  background: rgba(255, 255, 255, 0.85);
  color: rgb(75 85 99);
  backdrop-filter: blur(8px);
  transition: background-color 0.2s, color 0.2s, transform 0.3s;
}
.theme-switch:hover { color: rgb(4 120 87); transform: rotate(-12deg); }
.theme-switch:focus-visible { outline: 2px solid #10b981; outline-offset: 2px; }
html.dark .theme-switch { background: rgba(22, 33, 28, 0.9); border-color: #31413a; color: #fcd34d; }
@media (max-width: 1023px) {
  /* On phones the switch sits on the green header. */
  .theme-switch { position: fixed; border-color: rgba(255, 255, 255, 0.2); background: rgba(255, 255, 255, 0.12); color: #ecfdf5; }
  html.dark .theme-switch { background: rgba(255, 255, 255, 0.1); border-color: rgba(255, 255, 255, 0.2); color: #fcd34d; }
}

html.dark .form-side {
  background:
    radial-gradient(600px 300px at 100% 0%, rgba(16, 185, 129, 0.1), transparent 70%),
    radial-gradient(500px 300px at 0% 100%, rgba(16, 185, 129, 0.08), transparent 70%),
    #0a110e;
}
html.dark .form-card {
  border-color: rgba(134, 239, 172, 0.1);
  background: #111a16;
  box-shadow: 0 30px 70px -30px rgba(0, 0, 0, 0.8);
}
@media (min-width: 1024px) {
  html.dark .form-card { background: transparent; box-shadow: none; border-color: transparent; }
}
html.dark .field { border-color: #31413a; background: #0d1512; }
html.dark .field:hover { border-color: #3d4e46; }
html.dark .field:focus-within { border-color: #10b981; background: #111a16; box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.18); }
html.dark .field-icon { color: #66746e; }
html.dark .field:focus-within .field-icon { color: #34d399; }
html.dark .field-input { color: #e6efea; }
html.dark .field-input::placeholder { color: #66746e; }
html.dark .submit-btn { box-shadow: 0 14px 30px -12px rgba(16, 185, 129, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.2); }

/* Calm version for people who ask their device for less motion. */
@media (prefers-reduced-motion: reduce) {
  .blob, .float, .shine, .live-dot::after { animation: none; }
  .pulse-line path { animation: none; stroke-dashoffset: 0; opacity: 0.6; }
  .bars span { animation: none; transform: none; }
  .spark-path { animation: none; stroke-dashoffset: 0; }
  .submit-btn, .submit-btn::after, .arrow { transition: none; }
}
</style>
