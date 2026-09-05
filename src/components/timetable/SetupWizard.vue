<template>
  <div class="bg-white rounded-xl border border-slate-200 p-6">
    <!-- Progress Header -->
    <div class="mb-8">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-2xl font-bold text-slate-900">Timetable Setup Wizard</h2>
        <div class="text-sm text-slate-600">
          Step {{ currentStep }} of {{ totalSteps }} · 
          <span class="font-medium text-blue-600">{{ progressPercentage }}% Complete</span>
        </div>
      </div>
      
      <!-- Progress Bar -->
      <div class="h-2 bg-slate-200 rounded-full overflow-hidden">
        <div 
          class="h-full bg-blue-600 transition-all duration-300 ease-out"
          :style="{ width: progressPercentage + '%' }"
        ></div>
      </div>
      
      <!-- Step Indicators -->
      <div class="flex items-center justify-between mt-4">
        <div 
          v-for="(step, index) in steps" 
          :key="step.id"
          class="flex flex-col items-center"
          :class="{ 'opacity-50': index > currentStep - 1 }"
        >
          <div 
            class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium transition-colors"
            :class="getStepClass(index)"
          >
            <span v-if="index < currentStep - 1">✓</span>
            <span v-else>{{ index + 1 }}</span>
          </div>
          <span class="text-xs mt-1 text-slate-600 max-w-[80px] text-center">{{ step.label }}</span>
        </div>
      </div>
    </div>
    
    <!-- Step Content -->
    <div class="min-h-[400px]">
      <!-- Step 1: Academic Session -->
      <div v-if="currentStep === 1" class="space-y-6">
        <h3 class="text-lg font-semibold text-slate-900">Academic Session</h3>
        <p class="text-slate-600">Select or create the academic session for this timetable.</p>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Session Name</label>
            <input v-model="wizardData.session_name" type="text" class="input w-full" placeholder="e.g., 2025 Term 1">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Academic Year</label>
            <input v-model.number="wizardData.academic_year" type="number" class="input w-full" placeholder="2025">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Term</label>
            <select v-model.number="wizardData.term" class="input w-full">
              <option :value="1">Term 1</option>
              <option :value="2">Term 2</option>
              <option :value="3">Term 3</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Start Date</label>
            <input v-model="wizardData.start_date" type="date" class="input w-full">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">End Date</label>
            <input v-model="wizardData.end_date" type="date" class="input w-full">
          </div>
        </div>
        
        <div class="flex items-center gap-2">
          <input v-model="wizardData.is_active" type="checkbox" id="setActive" class="rounded border-slate-300">
          <label for="setActive" class="text-sm text-slate-700">Set as active session</label>
        </div>
      </div>
      
      <!-- Step 2: Bell Schedule -->
      <div v-if="currentStep === 2" class="space-y-6">
        <h3 class="text-lg font-semibold text-slate-900">Bell Schedule</h3>
        <p class="text-slate-600">Configure the daily bell schedule and periods.</p>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Schedule Type</label>
            <select v-model="wizardData.schedule_type" class="input w-full">
              <option value="weekly">Weekly Schedule</option>
              <option value="fortnightly">Fortnightly Schedule</option>
              <option value="custom">Custom Cycle</option>
              <option value="rotation">Day Rotation</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Day Pattern</label>
            <select v-model="wizardData.day_pattern" class="input w-full">
              <option value="uniform">Uniform Schedule (all days same)</option>
              <option value="custom">Custom Day Schedule</option>
            </select>
          </div>
        </div>
        
        <div class="border border-slate-200 rounded-lg p-4">
          <h4 class="font-medium text-slate-900 mb-3">Special Periods</h4>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <label class="flex items-center gap-2 text-sm">
              <input v-model="wizardData.special_periods.devotion" type="checkbox" class="rounded">
              Devotion
            </label>
            <label class="flex items-center gap-2 text-sm">
              <input v-model="wizardData.special_periods.breakfast" type="checkbox" class="rounded">
              Breakfast
            </label>
            <label class="flex items-center gap-2 text-sm">
              <input v-model="wizardData.special_periods.break" type="checkbox" class="rounded">
              Break
            </label>
            <label class="flex items-center gap-2 text-sm">
              <input v-model="wizardData.special_periods.lunch" type="checkbox" class="rounded">
              Lunch
            </label>
            <label class="flex items-center gap-2 text-sm">
              <input v-model="wizardData.special_periods.mentorship" type="checkbox" class="rounded">
              Mentorship
            </label>
            <label class="flex items-center gap-2 text-sm">
              <input v-model="wizardData.special_periods.games" type="checkbox" class="rounded">
              Games
            </label>
            <label class="flex items-center gap-2 text-sm">
              <input v-model="wizardData.special_periods.prep" type="checkbox" class="rounded">
              Prep
            </label>
            <label class="flex items-center gap-2 text-sm">
              <input v-model="wizardData.special_periods.supper" type="checkbox" class="rounded">
              Supper
            </label>
          </div>
        </div>
      </div>
      
      <!-- Step 3: Classes & Streams -->
      <div v-if="currentStep === 3" class="space-y-6">
        <h3 class="text-lg font-semibold text-slate-900">Classes & Streams</h3>
        <p class="text-slate-600">Verify your classes and streams are configured correctly.</p>
        
        <div class="bg-slate-50 rounded-lg p-4">
          <div class="flex items-center justify-between mb-3">
            <span class="text-sm font-medium text-slate-700">Total Classes: {{ classesCount }}</span>
            <button @click="$emit('navigate', 'classes')" class="text-sm text-blue-600 hover:text-blue-800">Manage Classes →</button>
          </div>
          <div v-if="classesCount > 0" class="text-sm text-green-600">✓ Classes configured</div>
          <div v-else class="text-sm text-amber-600">⚠ No classes found. Please add classes first.</div>
        </div>
      </div>
      
      <!-- Step 4: Subjects -->
      <div v-if="currentStep === 4" class="space-y-6">
        <h3 class="text-lg font-semibold text-slate-900">Subjects</h3>
        <p class="text-slate-600">Verify your subjects are configured correctly.</p>
        
        <div class="bg-slate-50 rounded-lg p-4">
          <div class="flex items-center justify-between mb-3">
            <span class="text-sm font-medium text-slate-700">Total Subjects: {{ subjectsCount }}</span>
            <button @click="$emit('navigate', 'subjects')" class="text-sm text-blue-600 hover:text-blue-800">Manage Subjects →</button>
          </div>
          <div v-if="subjectsCount > 0" class="text-sm text-green-600">✓ Subjects configured</div>
          <div v-else class="text-sm text-amber-600">⚠ No subjects found. Please add subjects first.</div>
        </div>
      </div>
      
      <!-- Step 5: Teachers -->
      <div v-if="currentStep === 5" class="space-y-6">
        <h3 class="text-lg font-semibold text-slate-900">Teachers</h3>
        <p class="text-slate-600">Verify your teachers are configured correctly.</p>
        
        <div class="bg-slate-50 rounded-lg p-4">
          <div class="flex items-center justify-between mb-3">
            <span class="text-sm font-medium text-slate-700">Total Teachers: {{ teachersCount }}</span>
            <button @click="$emit('navigate', 'teachers')" class="text-sm text-blue-600 hover:text-blue-800">Manage Teachers →</button>
          </div>
          <div v-if="teachersCount > 0" class="text-sm text-green-600">✓ Teachers configured</div>
          <div v-else class="text-sm text-amber-600">⚠ No teachers found. Please add teachers first.</div>
        </div>
      </div>
      
      <!-- Step 6: Rooms -->
      <div v-if="currentStep === 6" class="space-y-6">
        <h3 class="text-lg font-semibold text-slate-900">Rooms</h3>
        <p class="text-slate-600">Configure classrooms, laboratories, and other rooms.</p>
        
        <div class="bg-slate-50 rounded-lg p-4">
          <div class="flex items-center justify-between mb-3">
            <span class="text-sm font-medium text-slate-700">Total Rooms: {{ roomsCount }}</span>
            <button @click="$emit('navigate', 'rooms')" class="text-sm text-blue-600 hover:text-blue-800">Manage Rooms →</button>
          </div>
          <div v-if="roomsCount > 0" class="text-sm text-green-600">✓ Rooms configured</div>
          <div v-else class="text-sm text-amber-600">⚠ No rooms found. Please add rooms first.</div>
        </div>
      </div>
      
      <!-- Step 7: School Events -->
      <div v-if="currentStep === 7" class="space-y-6">
        <h3 class="text-lg font-semibold text-slate-900">School Events</h3>
        <p class="text-slate-600">Configure school-wide events (assembly, devotion, etc.).</p>
        
        <div class="bg-slate-50 rounded-lg p-4">
          <div class="flex items-center justify-between mb-3">
            <span class="text-sm font-medium text-slate-700">Total Events: {{ eventsCount }}</span>
            <button @click="$emit('navigate', 'school-events')" class="text-sm text-blue-600 hover:text-blue-800">Manage Events →</button>
          </div>
          <div v-if="eventsCount > 0" class="text-sm text-green-600">✓ Events configured</div>
          <div v-else class="text-sm text-amber-600">⚠ No events found. Events are optional but recommended.</div>
        </div>
      </div>
      
      <!-- Step 8: Lesson Requirements -->
      <div v-if="currentStep === 8" class="space-y-6">
        <h3 class="text-lg font-semibold text-slate-900">Lesson Requirements</h3>
        <p class="text-slate-600">Define how many periods each subject needs per week for each class.</p>
        
        <div class="bg-slate-50 rounded-lg p-4">
          <div class="flex items-center justify-between mb-3">
            <span class="text-sm font-medium text-slate-700">Total Requirements: {{ requirementsCount }}</span>
            <button @click="$emit('navigate', 'lesson-requirements')" class="text-sm text-blue-600 hover:text-blue-800">Manage Requirements →</button>
          </div>
          <div v-if="requirementsCount > 0" class="text-sm text-green-600">✓ Lesson requirements configured</div>
          <div v-else class="text-sm text-amber-600">⚠ No lesson requirements found. This is required for generation.</div>
        </div>
      </div>
      
      <!-- Step 9: Teacher Availability -->
      <div v-if="currentStep === 9" class="space-y-6">
        <h3 class="text-lg font-semibold text-slate-900">Teacher Availability</h3>
        <p class="text-slate-600">Set when teachers are available for teaching.</p>
        
        <div class="bg-slate-50 rounded-lg p-4">
          <div class="flex items-center justify-between mb-3">
            <span class="text-sm font-medium text-slate-700">Availability Records: {{ availabilityCount }}</span>
            <button @click="$emit('navigate', 'teacher-availability')" class="text-sm text-blue-600 hover:text-blue-800">Manage Availability →</button>
          </div>
          <div class="text-sm text-slate-600">Teacher availability is optional. If not set, teachers are assumed available for all periods.</div>
        </div>
      </div>
      
      <!-- Step 10: Constraints -->
      <div v-if="currentStep === 10" class="space-y-6">
        <h3 class="text-lg font-semibold text-slate-900">Constraints & Rules</h3>
        <p class="text-slate-600">Configure generation constraints to optimize the timetable.</p>
        
        <div class="space-y-4">
          <div class="bg-slate-50 rounded-lg p-4">
            <div class="flex items-center justify-between mb-3">
              <span class="text-sm font-medium text-slate-700">Total Constraints: {{ constraintsCount }}</span>
              <button @click="$emit('navigate', 'constraints')" class="text-sm text-blue-600 hover:text-blue-800">Manage Constraints →</button>
            </div>
            <div class="text-sm text-slate-600">Constraints are optional but recommended for better results.</div>
          </div>
          
          <div class="border border-slate-200 rounded-lg p-4">
            <h4 class="font-medium text-slate-900 mb-3">Quick Constraints</h4>
            <div class="space-y-2">
              <label class="flex items-center gap-2 text-sm">
                <input v-model="wizardData.quickConstraints.noDoubleBooking" type="checkbox" class="rounded">
                Prevent teacher double booking
              </label>
              <label class="flex items-center gap-2 text-sm">
                <input v-model="wizardData.quickConstraints.maxLessonsPerDay" type="number" class="input w-24" placeholder="6">
                Maximum lessons per day per teacher
              </label>
              <label class="flex items-center gap-2 text-sm">
                <input v-model="wizardData.quickConstraints.minFreePeriods" type="number" class="input w-24" placeholder="1">
                Minimum free periods per day
              </label>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Step 11: Validation -->
      <div v-if="currentStep === 11" class="space-y-6">
        <h3 class="text-lg font-semibold text-slate-900">Validation</h3>
        <p class="text-slate-600">Review the validation results before generating.</p>
        
        <div class="space-y-4">
          <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <h4 class="font-medium text-green-800 mb-2">✓ Ready Items</h4>
            <ul class="text-sm text-green-700 space-y-1">
              <li v-for="item in validationResults.ready" :key="item">{{ item }}</li>
            </ul>
          </div>
          
          <div v-if="validationResults.warnings.length > 0" class="bg-amber-50 border border-amber-200 rounded-lg p-4">
            <h4 class="font-medium text-amber-800 mb-2">⚠ Warnings</h4>
            <ul class="text-sm text-amber-700 space-y-1">
              <li v-for="item in validationResults.warnings" :key="item">{{ item }}</li>
            </ul>
          </div>
          
          <div v-if="validationResults.errors.length > 0" class="bg-red-50 border border-red-200 rounded-lg p-4">
            <h4 class="font-medium text-red-800 mb-2">✗ Errors</h4>
            <ul class="text-sm text-red-700 space-y-1">
              <li v-for="item in validationResults.errors" :key="item">{{ item }}</li>
            </ul>
          </div>
          
          <div v-if="validationResults.missing.length > 0" class="bg-slate-100 border border-slate-300 rounded-lg p-4">
            <h4 class="font-medium text-slate-800 mb-2">○ Missing Items</h4>
            <ul class="text-sm text-slate-700 space-y-1">
              <li v-for="item in validationResults.missing" :key="item">{{ item }}</li>
            </ul>
          </div>
        </div>
      </div>
      
      <!-- Step 12: Generate -->
      <div v-if="currentStep === 12" class="space-y-6">
        <h3 class="text-lg font-semibold text-slate-900">Generate Timetable</h3>
        <p class="text-slate-600">Configure generation settings and start the process.</p>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Generation Mode</label>
            <select v-model="wizardData.generationMode" class="input w-full">
              <option value="automatic">Automatic (AI-optimized)</option>
              <option value="semi-automatic">Semi-Automatic (with guidance)</option>
              <option value="manual">Manual (step-by-step)</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Optimization Mode</label>
            <select v-model="wizardData.optimizationMode" class="input w-full">
              <option value="balanced">Balanced (speed + accuracy)</option>
              <option value="fast">Fast (quick generation)</option>
              <option value="accurate">Maximum Accuracy (slower)</option>
            </select>
          </div>
        </div>
        
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <h4 class="font-medium text-blue-800 mb-2">Generation Summary</h4>
          <div class="text-sm text-blue-700 space-y-1">
            <p>• Classes: {{ classesCount }}</p>
            <p>• Teachers: {{ teachersCount }}</p>
            <p>• Subjects: {{ subjectsCount }}</p>
            <p>• Rooms: {{ roomsCount }}</p>
            <p>• Lesson Requirements: {{ requirementsCount }}</p>
          </div>
        </div>
        
        <button 
          @click="startGeneration" 
          :disabled="generating || validationResults.errors.length > 0"
          class="w-full btn-primary py-3 text-lg"
          :class="{ 'opacity-50 cursor-not-allowed': generating || validationResults.errors.length > 0 }"
        >
          {{ generating ? 'Generating Timetable...' : 'Start Generation' }}
        </button>
        
        <div v-if="generationProgress > 0" class="bg-slate-100 rounded-lg p-4">
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-slate-700">Generation Progress</span>
            <span class="text-sm text-slate-600">{{ generationProgress }}%</span>
          </div>
          <div class="h-2 bg-slate-200 rounded-full overflow-hidden">
            <div class="h-full bg-blue-600 transition-all" :style="{ width: generationProgress + '%' }"></div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Navigation Buttons -->
    <div class="flex items-center justify-between mt-8 pt-6 border-t border-slate-200">
      <button 
        @click="previousStep" 
        :disabled="currentStep === 1"
        class="btn-secondary"
        :class="{ 'opacity-50 cursor-not-allowed': currentStep === 1 }"
      >
        ← Previous
      </button>
      
      <button 
        v-if="currentStep < totalSteps"
        @click="nextStep"
        class="btn-primary"
      >
        Next →
      </button>
      
      <button 
        v-else
        @click="completeWizard"
        class="btn-primary"
      >
        Complete Setup
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
  classesCount: { type: Number, default: 0 },
  subjectsCount: { type: Number, default: 0 },
  teachersCount: { type: Number, default: 0 },
  roomsCount: { type: Number, default: 0 },
  eventsCount: { type: Number, default: 0 },
  requirementsCount: { type: Number, default: 0 },
  availabilityCount: { type: Number, default: 0 },
  constraintsCount: { type: Number, default: 0 }
});

const emit = defineEmits(['navigate', 'complete', 'generate']);

const currentStep = ref(1);
const totalSteps = 12;
const generating = ref(false);
const generationProgress = ref(0);

const wizardData = ref({
  session_name: '',
  academic_year: new Date().getFullYear(),
  term: 1,
  start_date: '',
  end_date: '',
  is_active: true,
  schedule_type: 'weekly',
  day_pattern: 'uniform',
  special_periods: {
    devotion: true,
    breakfast: false,
    break: true,
    lunch: true,
    mentorship: false,
    games: false,
    prep: false,
    supper: false
  },
  quickConstraints: {
    noDoubleBooking: true,
    maxLessonsPerDay: 6,
    minFreePeriods: 1
  },
  generationMode: 'automatic',
  optimizationMode: 'balanced'
});

const steps = [
  { id: 'session', label: 'Session' },
  { id: 'bell', label: 'Bell' },
  { id: 'classes', label: 'Classes' },
  { id: 'subjects', label: 'Subjects' },
  { id: 'teachers', label: 'Teachers' },
  { id: 'rooms', label: 'Rooms' },
  { id: 'events', label: 'Events' },
  { id: 'requirements', label: 'Lessons' },
  { id: 'availability', label: 'Availability' },
  { id: 'constraints', label: 'Constraints' },
  { id: 'validation', label: 'Validate' },
  { id: 'generate', label: 'Generate' }
];

const progressPercentage = computed(() => {
  return Math.round((currentStep.value / totalSteps) * 100);
});

const validationResults = computed(() => {
  const ready = [];
  const warnings = [];
  const errors = [];
  const missing = [];
  
  if (props.classesCount > 0) ready.push('Classes configured');
  else missing.push('Classes not configured');
  
  if (props.subjectsCount > 0) ready.push('Subjects configured');
  else missing.push('Subjects not configured');
  
  if (props.teachersCount > 0) ready.push('Teachers configured');
  else missing.push('Teachers not configured');
  
  if (props.roomsCount > 0) ready.push('Rooms configured');
  else missing.push('Rooms not configured');
  
  if (props.requirementsCount > 0) ready.push('Lesson requirements configured');
  else errors.push('Lesson requirements not configured (required)');
  
  if (props.constraintsCount === 0) {
    warnings.push('No constraints configured - generation may be suboptimal');
  }
  
  if (props.availabilityCount === 0) {
    warnings.push('No teacher availability set - teachers assumed available for all periods');
  }
  
  return { ready, warnings, errors, missing };
});

const getStepClass = (index) => {
  if (index < currentStep.value - 1) {
    return 'bg-green-600 text-white';
  } else if (index === currentStep.value - 1) {
    return 'bg-blue-600 text-white';
  }
  return 'bg-slate-200 text-slate-600';
};

const nextStep = () => {
  if (currentStep.value < totalSteps) {
    currentStep.value++;
  }
};

const previousStep = () => {
  if (currentStep.value > 1) {
    currentStep.value--;
  }
};

const startGeneration = () => {
  generating.value = true;
  generationProgress.value = 0;
  
  const interval = setInterval(() => {
    generationProgress.value += 10;
    if (generationProgress.value >= 100) {
      clearInterval(interval);
      generating.value = false;
      emit('generate', wizardData.value);
    }
  }, 500);
};

const completeWizard = () => {
  emit('complete', wizardData.value);
};

onMounted(() => {
  // Pre-fill dates if available
  const today = new Date();
  wizardData.value.start_date = today.toISOString().split('T')[0];
  const endDate = new Date(today.setMonth(today.getMonth() + 3));
  wizardData.value.end_date = endDate.toISOString().split('T')[0];
});
</script>
