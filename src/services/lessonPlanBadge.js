import { ref } from 'vue';
import { lessonPlansAPI } from './api.js';

/** Submitted lesson plans no reviewer has opened yet: the red badge on "Lesson Plans" in the sidebar. */
export const newLessonPlans = ref(0);

export async function refreshNewLessonPlans() {
  try {
    newLessonPlans.value = Number((await lessonPlansAPI.newCount()).data?.new || 0);
  } catch {
    /* the badge is a hint; leave it as it was */
  }
}
