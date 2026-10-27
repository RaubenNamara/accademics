import { jsPDF } from 'jspdf';
import autoTable from 'jspdf-autotable';

/**
 * Subject label colours: light background + dark text pairs that keep at
 * least 4.5:1 contrast. A subject always gets the same colour.
 */
const SUBJECT_COLOURS = [
  { bg: '#DCFCE7', border: '#86EFAC', text: '#14532D' },
  { bg: '#DBEAFE', border: '#93C5FD', text: '#1E3A8A' },
  { bg: '#FEF3C7', border: '#FCD34D', text: '#78350F' },
  { bg: '#FCE7F3', border: '#F9A8D4', text: '#831843' },
  { bg: '#EDE9FE', border: '#C4B5FD', text: '#4C1D95' },
  { bg: '#CFFAFE', border: '#67E8F9', text: '#164E63' },
  { bg: '#FFEDD5', border: '#FDBA74', text: '#7C2D12' },
  { bg: '#E0E7FF', border: '#A5B4FC', text: '#312E81' },
  { bg: '#ECFCCB', border: '#BEF264', text: '#365314' },
  { bg: '#FEE2E2', border: '#FCA5A5', text: '#7F1D1D' },
  { bg: '#F1F5F9', border: '#CBD5E1', text: '#0F172A' },
  { bg: '#CCFBF1', border: '#5EEAD4', text: '#134E4A' },
];

export function subjectColour(subjectId) {
  if (!subjectId) return SUBJECT_COLOURS[10];
  return SUBJECT_COLOURS[Math.abs(Number(subjectId) * 7) % SUBJECT_COLOURS.length];
}

export const shortTime = (t) => (t ? String(t).slice(0, 5) : '');

/** Unique id for one form submission (double-submit protection). */
export function requestId() {
  const rnd = (globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(36).slice(2)}`);
  return `tt-${rnd}`.replace(/[^A-Za-z0-9_-]/g, '').slice(0, 64);
}

/** Pulls {message, errors, conflicts, data} out of an axios error or API body. */
export function apiError(err) {
  const body = err?.response?.data ?? err ?? {};
  return {
    status: err?.response?.status ?? 0,
    message: body.message || err?.message || 'Something went wrong',
    errors: body.data?.errors || {},
    conflicts: body.data?.conflicts || [],
    data: body.data || null,
  };
}

/** entries -> { 'Monday|3': entry } for every period an entry covers (start cell flagged). */
export function indexEntries(entries) {
  const map = {};
  for (const e of entries) {
    const span = Math.max(1, Number(e.spans_periods) || 1);
    for (let i = 0; i < span; i++) {
      const key = `${e.day_of_week}|${Number(e.period_number) + i}`;
      (map[key] ||= []).push({ entry: e, isStart: i === 0, offset: i, span });
    }
  }
  return map;
}

export function entryTitle(e) {
  return e.entry_type === 'event' ? (e.event_name || 'Event') : (e.subject_name || 'Lesson');
}

function gridRows(entries, periods, days, describe) {
  const index = indexEntries(entries);
  return periods.map((p) => [
    `${p.label}\n${shortTime(p.start_time)}–${shortTime(p.end_time)}`,
    ...days.map((d) => {
      if (p.period_type !== 'lesson') return p.label;
      return (index[`${d}|${p.period_number}`] || []).map((c) => describe(c.entry)).join('\n---\n');
    }),
  ]);
}

/** CSV of the visible timetable as a flat list (one row per lesson). */
export function exportCsv(filename, entries) {
  const header = ['Day', 'Start period', 'Periods', 'Start', 'End', 'Class', 'Subject', 'Teacher', 'Room', 'Type', 'Locked'];
  const rows = entries.map((e) => [
    e.day_of_week, e.period_number, e.spans_periods, shortTime(e.start_time), shortTime(e.end_time),
    e.full_class_name || '', entryTitle(e), e.teacher_name || '', e.room_name || e.room_code || '',
    e.entry_type, e.is_locked ? 'yes' : 'no',
  ]);
  const csv = [header, ...rows].map((r) => r.map((v) => `"${String(v ?? '').replace(/"/g, '""')}"`).join(',')).join('\r\n');
  const blob = new Blob([`﻿${csv}`], { type: 'text/csv;charset=utf-8' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = `${filename}.csv`;
  a.click();
  setTimeout(() => URL.revokeObjectURL(a.href), 1000);
}

/** Landscape PDF of a weekly grid for one class/teacher/room. */
export function exportGridPdf({ title, subtitle, filename, entries, periods, days, mode }) {
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(15);
  doc.setTextColor(20, 83, 45);
  doc.text(title, 14, 15);
  doc.setFont('helvetica', 'normal');
  doc.setFontSize(9);
  doc.setTextColor(100, 116, 139);
  doc.text(subtitle, 14, 21);

  const describe = (e) => {
    const parts = [entryTitle(e)];
    if (mode !== 'class' && e.full_class_name) parts.push(e.full_class_name);
    if (mode !== 'teacher' && e.teacher_name) parts.push(e.teacher_name);
    if (mode !== 'room' && (e.room_code || e.room_name)) parts.push(e.room_code || e.room_name);
    return parts.join('\n');
  };

  autoTable(doc, {
    startY: 26,
    head: [['Period', ...days]],
    body: gridRows(entries, periods, days, describe),
    theme: 'grid',
    styles: { fontSize: 7.5, cellPadding: 1.8, valign: 'middle', textColor: [15, 23, 42], lineColor: [226, 232, 240] },
    headStyles: { fillColor: [21, 128, 61], textColor: 255, fontStyle: 'bold', halign: 'center' },
    columnStyles: { 0: { cellWidth: 26, fontStyle: 'bold', fillColor: [248, 250, 252] } },
    didParseCell: (data) => {
      const p = periods[data.row.index];
      if (data.section === 'body' && p && p.period_type !== 'lesson' && data.column.index > 0) {
        data.cell.styles.fillColor = [241, 245, 249];
        data.cell.styles.textColor = [100, 116, 139];
        data.cell.styles.halign = 'center';
        data.cell.styles.fontStyle = 'italic';
      }
    },
  });
  doc.setFontSize(8);
  doc.setTextColor(148, 163, 184);
  doc.text(`Generated ${new Date().toLocaleString()}`, 14, doc.internal.pageSize.getHeight() - 8);
  doc.save(`${filename}.pdf`);
}
