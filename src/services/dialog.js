import { reactive } from 'vue';

/**
 * In-app message window, used instead of the browser's alert(). main.js routes window.alert here, so
 * every page gets it. A closed-term refusal gets its own look and a way to Terms & Years.
 */
export const dialogState = reactive({ open: false, tone: 'info', title: '', message: '', action: null });

const toneOf = (m) => {
  if (/is closed, so its records/i.test(m)) return 'locked';
  if (/fail|error|cannot|could not|can't|invalid|required|not allowed|denied|missing/i.test(m)) return 'error';
  if (/saved|success|deleted|updated|created|added|removed|done/i.test(m)) return 'success';
  return 'info';
};
const TITLES = { locked: 'This term is closed', error: 'Not saved', success: 'Done', info: 'Notice' };

export function showAlert(message, opts = {}) {
  const text = String(message ?? '').replace(/^(Failed to save|Failed to delete|Error):\s*/i, '');
  const tone = opts.tone || toneOf(text);
  // The same message twice in a row (inline error + alert) shows once.
  if (dialogState.open && dialogState.message === text) return;
  Object.assign(dialogState, {
    open: true,
    tone,
    title: opts.title || TITLES[tone],
    message: text,
    action: opts.action ?? (tone === 'locked' ? { label: 'Open Terms & Years', to: '/academic-calendar' } : null),
  });
}

export function closeAlert() {
  dialogState.open = false;
}
