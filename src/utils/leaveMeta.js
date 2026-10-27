/** Leave: how each type and status looks, and small date helpers. Shared by the HR page and the teacher portal. */
import { Baby, CalendarDays, HandHeart, HeartPulse, TreePalm, Users, Wallet } from 'lucide-vue-next';

export const LEAVE_TYPES = {
  annual: { label: 'Annual', icon: TreePalm, tile: 'bg-emerald-50 text-emerald-700 ring-emerald-200', bar: 'bg-emerald-500' },
  sick: { label: 'Sick', icon: HeartPulse, tile: 'bg-rose-50 text-rose-700 ring-rose-200', bar: 'bg-rose-500' },
  maternity: { label: 'Maternity', icon: Baby, tile: 'bg-violet-50 text-violet-700 ring-violet-200', bar: 'bg-violet-500' },
  paternity: { label: 'Paternity', icon: Users, tile: 'bg-sky-50 text-sky-700 ring-sky-200', bar: 'bg-sky-500' },
  compassionate: { label: 'Compassionate', icon: HandHeart, tile: 'bg-amber-50 text-amber-700 ring-amber-200', bar: 'bg-amber-500' },
  unpaid: { label: 'Unpaid', icon: Wallet, tile: 'bg-gray-100 text-gray-700 ring-gray-200', bar: 'bg-gray-500' },
  other: { label: 'Other', icon: CalendarDays, tile: 'bg-brand-50 text-brand-700 ring-brand-200', bar: 'bg-brand-500' },
};
export const leaveType = (t) => LEAVE_TYPES[t] || { ...LEAVE_TYPES.other, label: t ? String(t).replace(/^\w/, (c) => c.toUpperCase()) : 'Leave' };

export const LEAVE_STATUS = {
  pending: { label: 'Pending', dot: 'bg-amber-500', pill: 'bg-amber-50 text-amber-800 ring-1 ring-amber-200' },
  approved: { label: 'Approved', dot: 'bg-emerald-500', pill: 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' },
  rejected: { label: 'Declined', dot: 'bg-red-500', pill: 'bg-red-50 text-red-700 ring-1 ring-red-200' },
  cancelled: { label: 'Withdrawn', dot: 'bg-gray-400', pill: 'bg-gray-100 text-gray-600 ring-1 ring-gray-200' },
};
export const leaveStatus = (s) => LEAVE_STATUS[s] || { label: s, dot: 'bg-gray-400', pill: 'bg-gray-100 text-gray-600' };

const asDate = (d) => new Date(`${String(d).slice(0, 10)}T00:00:00`);
export const todayISO = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
export const dayCount = (start, end) => (start && end && end >= start ? Math.round((asDate(end) - asDate(start)) / 86400000) + 1 : 0);
export const monthShort = (d) => asDate(d).toLocaleDateString('en-GB', { month: 'short' });
export const dayOfMonth = (d) => asDate(d).getDate();
export const shortDate = (d) => asDate(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
export const longDate = (d) => asDate(d).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
export const dateRange = (r) => (r.start_date === r.end_date ? shortDate(r.start_date) : `${shortDate(r.start_date)} – ${shortDate(r.end_date)}`);

/** Where an (approved or pending) request sits in time, e.g. "Starts in 3 days", "On leave now". */
export const leaveTiming = (r, today = todayISO()) => {
  if (r.end_date < today) return { label: 'Ended', tone: 'text-gray-500' };
  if (r.start_date <= today) {
    const left = dayCount(today, r.end_date);
    return { label: left === 1 ? 'Last day today' : `On leave, ${left} days left`, tone: 'text-emerald-700' };
  }
  const until = dayCount(today, r.start_date) - 1;
  return { label: until === 1 ? 'Starts tomorrow' : `Starts in ${until} days`, tone: 'text-sky-700' };
};
