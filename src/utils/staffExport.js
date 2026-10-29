import { nonTeachingStaffAPI, teachersAPI } from '../services/api.js';
import { downloadExcel, downloadPdf } from './downloads.js';

/** Staff downloads shared by Teaching Staff and Non-Teaching Staff: teaching, non-teaching or everyone. */
export const STAFF_SCOPES = [
  { value: 'all', label: 'All staff' },
  { value: 'teaching', label: 'Teaching staff' },
  { value: 'non_teaching', label: 'Non-teaching staff' },
];

const date = (d) => (d ? String(d).slice(0, 10) : '');
const TEACHING_COLS = [
  { key: 'code', label: 'Code' }, { key: 'name', label: 'Full name' }, { key: 'gender', label: 'Gender' },
  { key: 'contact', label: 'Contact' }, { key: 'email', label: 'Email' }, { key: 'subject', label: 'Subject' },
  { key: 'department', label: 'Department' }, { key: 'position', label: 'Position' }, { key: 'employment', label: 'Employment' },
  { key: 'status', label: 'Status' }, { key: 'joined', label: 'Joined' },
];
const NON_TEACHING_COLS = [
  { key: 'code', label: 'Code' }, { key: 'name', label: 'Full name' }, { key: 'gender', label: 'Gender' },
  { key: 'contact', label: 'Contact' }, { key: 'email', label: 'Email' }, { key: 'department', label: 'Department' },
  { key: 'position', label: 'Position' }, { key: 'employment', label: 'Employment' }, { key: 'status', label: 'Status' },
  { key: 'joined', label: 'Joined' },
];
const PDF_COLS = [
  { key: 'code', label: 'Code' }, { key: 'name', label: 'Name' }, { key: 'type', label: 'Type' },
  { key: 'department', label: 'Department / subject' }, { key: 'position', label: 'Position' }, { key: 'contact', label: 'Contact' },
  { key: 'status', label: 'Status' },
];

async function teaching() {
  const res = await teachersAPI.getAll({ page: 1, limit: 5000 });
  const list = Array.isArray(res?.data?.teachers) ? res.data.teachers : Array.isArray(res?.data) ? res.data : [];
  return list.map((t) => ({
    code: t.teacher_code, name: t.full_name, gender: t.gender, contact: t.contact || t.phone_number, email: t.email,
    subject: t.subject, department: t.department, position: t.position, employment: t.employment_type,
    status: Number(t.is_active) === 1 || t.is_active === null ? 'Active' : 'Inactive', joined: date(t.date_joined), type: 'Teaching',
  }));
}

async function nonTeaching() {
  const res = await nonTeachingStaffAPI.getAll({});
  return (res?.data || []).map((s) => ({
    code: s.hr_code, name: `${s.first_name || ''} ${s.last_name || ''}`.trim(), gender: s.gender, contact: s.phone_number, email: s.email,
    department: s.department_name, position: s.role_name, employment: s.employment_type || s.contract_type,
    status: String(s.staff_status || s.status || '').replace('_', ' '), joined: date(s.date_joined), type: 'Non-teaching',
  }));
}

export async function downloadStaff(format, scope = 'all') {
  const [t, n] = await Promise.all([
    scope === 'non_teaching' ? [] : teaching(),
    scope === 'teaching' ? [] : nonTeaching(),
  ]);
  const title = { all: 'All staff', teaching: 'Teaching staff', non_teaching: 'Non-teaching staff' }[scope];
  if (format === 'excel') {
    downloadExcel(title, [
      ...(scope !== 'non_teaching' ? [{ name: 'Teaching staff', columns: TEACHING_COLS, rows: t }] : []),
      ...(scope !== 'teaching' ? [{ name: 'Non-teaching staff', columns: NON_TEACHING_COLS, rows: n }] : []),
    ]);
    return;
  }
  const rows = [...t.map((r) => ({ ...r, department: r.subject || r.department })), ...n];
  const active = rows.filter((r) => /active/i.test(r.status) && !/inactive/i.test(r.status)).length;
  await downloadPdf({
    title,
    subtitle: 'Staff list',
    columns: PDF_COLS,
    rows,
    summary: [
      { label: 'Staff', value: rows.length },
      ...(scope === 'all' ? [{ label: 'Teaching', value: t.length }, { label: 'Non-teaching', value: n.length }] : []),
      { label: 'Active', value: active },
    ],
  });
}
