/** Lesson plan template (NCDC competency-based), shared by the form, the view and the PDF. */

export const PERIOD_MINUTES = 40;

/** Lesson lengths per level: O-Level single/double, A-Level up to quadruple. */
export const SPANS = [
  { value: 1, label: 'Single', minutes: 40 },
  { value: 2, label: 'Double', minutes: 80 },
  { value: 3, label: 'Triple', minutes: 120 },
  { value: 4, label: 'Quadruple', minutes: 160 },
];
export const spansFor = (level) => SPANS.filter((s) => s.value <= (level === 'A-Level' ? 4 : 2));
export const spanLabel = (n) => SPANS.find((s) => s.value === Number(n))?.label || '';

/** Sections written as text, in the template's order (key = API field). */
export const PLAN_SECTIONS = [
  { key: 'competences', group: 'outcomes', label: 'Competency', hint: 'The learner … (what the learner does to show the competency)', rows: 2, required: true },
  { key: 'generic_skills', group: 'outcomes', label: 'Generic skill(s)', hint: 'One per line, e.g. Critical thinking & problem-solving: evaluating options …', rows: 3, list: true },
  { key: 'values_text', group: 'outcomes', label: 'Value(s)', hint: 'e.g. Hard work for self-reliance; Integrity', rows: 2 },
  { key: 'cross_cutting', group: 'outcomes', label: 'Cross-cutting issue(s)', hint: 'e.g. Life skills (self-management); Environmental awareness', rows: 2 },
  { key: 'objectives', group: 'outcomes', label: 'Key Learning Outcome(s)', hint: 'The learner should be able to …', rows: 3, required: true },
  { key: 'prerequisite', group: 'content', label: 'Pre-Requisite Knowledge', hint: 'What learners already know from earlier lessons', rows: 2, divider: true },
  { key: 'materials', group: 'content', label: 'Learning materials', hint: 'Charts, specimens, textbooks, computers, projector …', rows: 2 },
  { key: 'references_text', group: 'content', label: 'References', hint: 'Syllabus (NCDC), scheme of work, textbooks', rows: 2 },
];

export const SELF_ASSESSMENT = { key: 'reflection', label: 'Teacher Self-Assessment', hint: 'Filled after the lesson: what worked, what learners found hard, what to change', rows: 3 };

/** Starting phases for a new plan; minutes are spread to fill the lesson. */
export function defaultProcedure(minutes) {
  const intro = 10;
  const close = 10;
  return [
    { minutes: intro, teacher: 'Climate setting & introduction: ', learners: '' },
    { minutes: Math.max(5, minutes - intro - close), teacher: '', learners: '' },
    { minutes: close, teacher: 'Conclusion & assignment: ', learners: '' },
  ];
}

export const PLAN_STATUS = {
  draft: { label: 'Draft', style: 'bg-gray-100 text-gray-700' },
  submitted: { label: 'Awaiting review', style: 'bg-amber-50 text-amber-800' },
  approved: { label: 'Approved', style: 'bg-brand-100 text-brand-800' },
  returned: { label: 'Returned for changes', style: 'bg-red-50 text-red-700' },
};

export const formatDate = (d) => (d ? new Date(`${String(d).slice(0, 10)}T00:00:00`).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) : '');

/** "21st September 2026" as in the paper template. */
export function longDate(d) {
  if (!d) return '';
  const dt = new Date(`${String(d).slice(0, 10)}T00:00:00`);
  if (Number.isNaN(dt.getTime())) return String(d);
  const n = dt.getDate();
  const sfx = n % 10 === 1 && n !== 11 ? 'st' : n % 10 === 2 && n !== 12 ? 'nd' : n % 10 === 3 && n !== 13 ? 'rd' : 'th';
  return `${n}${sfx} ${dt.toLocaleDateString('en-GB', { month: 'long' })} ${dt.getFullYear()}`;
}

const to12 = (mins) => {
  const h = Math.floor(mins / 60) % 24;
  const m = mins % 60;
  return `${String(((h + 11) % 12) + 1).padStart(2, '0')}:${String(m).padStart(2, '0')} ${h < 12 ? 'AM' : 'PM'}`;
};
/** "07:30 AM – 10:10 AM" from a start time and minutes. */
export function timeRange(start, minutes) {
  if (!start) return '';
  const [h, m] = String(start).split(':').map(Number);
  const from = h * 60 + m;
  return `${to12(from)} – ${to12(from + Number(minutes || 0))}`;
}
/** "160 Minutes (2 hours 40 minutes)". */
export function durationText(minutes) {
  const m = Number(minutes || 0);
  if (!m) return '';
  const h = Math.floor(m / 60);
  const r = m % 60;
  const parts = [h ? `${h} hour${h > 1 ? 's' : ''}` : '', r ? `${r} minutes` : ''].filter(Boolean).join(' ');
  return h ? `${m} Minutes (${parts})` : `${m} Minutes`;
}
