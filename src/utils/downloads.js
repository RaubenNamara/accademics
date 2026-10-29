import * as XLSX from 'xlsx';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import { schoolSettingsAPI } from '../services/api.js';

/**
 * Downloads used across modules: one Excel layout and one PDF layout, so every export looks the same.
 *   columns: [{ key, label, width?, align?: 'right' }]   rows: [{ [key]: value }]
 */

let branding = null;
async function schoolBranding() {
  if (branding) return branding;
  try {
    branding = (await schoolSettingsAPI.get()).data || {};
  } catch {
    branding = {};
  }
  return branding;
}

const stamp = () => new Date().toISOString().slice(0, 10);
const safeName = (s) => String(s).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
const cell = (v) => (v === null || v === undefined ? '' : v);

/** sheets: [{ name, columns, rows }] -> one .xlsx with a tab per sheet. */
export function downloadExcel(filename, sheets) {
  const wb = XLSX.utils.book_new();
  for (const s of sheets) {
    const data = [s.columns.map((c) => c.label), ...s.rows.map((r) => s.columns.map((c) => cell(r[c.key])))];
    const ws = XLSX.utils.aoa_to_sheet(data);
    ws['!cols'] = s.columns.map((c) => ({ wch: c.width || Math.min(40, Math.max(c.label.length + 2, ...s.rows.slice(0, 200).map((r) => String(cell(r[c.key])).length + 2))) }));
    ws['!autofilter'] = { ref: XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: data.length - 1, c: s.columns.length - 1 } }) };
    XLSX.utils.book_append_sheet(wb, ws, String(s.name).slice(0, 31).replace(/[\\/?*[\]:]/g, ' '));
  }
  XLSX.writeFile(wb, `${safeName(filename)}-${stamp()}.xlsx`);
}

/**
 * A branded A4 report: school logo and name, title, optional summary figures, then the table.
 * opts: { title, subtitle, columns, rows, filename, summary?: [{label, value}], landscape? }
 */
export async function downloadPdf(opts) {
  const { title, subtitle = '', columns, rows, filename, summary = [] } = opts;
  const landscape = opts.landscape ?? columns.length > 6;
  const school = await schoolBranding();
  const schoolName = school.school_name || "St. Mark's College Namagoma";
  const doc = new jsPDF({ unit: 'mm', format: 'a4', orientation: landscape ? 'landscape' : 'portrait' });
  const W = doc.internal.pageSize.getWidth();
  const H = doc.internal.pageSize.getHeight();
  const M = 12;
  const brand = [5, 150, 105];
  const ink = [15, 23, 42];
  const muted = [100, 116, 139];
  const line = [226, 232, 240];

  doc.setFillColor(...brand);
  doc.rect(0, 0, W, 2.5, 'F');
  let x = M;
  if (school.logo) {
    try {
      const p = doc.getImageProperties(school.logo);
      const h = 12;
      const w = Math.min(24, (p.width / p.height) * h);
      doc.addImage(school.logo, p.fileType || 'PNG', M, 8, w, h);
      x = M + w + 4;
    } catch {
      /* an unreadable logo should not stop the download */
    }
  }
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(10.5);
  doc.setTextColor(...ink);
  doc.text(schoolName.toUpperCase(), x, 13);
  doc.setFont('helvetica', 'normal');
  doc.setFontSize(8);
  doc.setTextColor(...muted);
  doc.text('Skool Monitor', x, 18);
  doc.text(`Generated ${new Date().toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}`, W - M, 13, { align: 'right' });
  doc.setDrawColor(...line);
  doc.line(M, 24, W - M, 24);

  doc.setFillColor(...brand);
  doc.roundedRect(M, 29, 1.5, 10, 0.7, 0.7, 'F');
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(15);
  doc.setTextColor(...ink);
  doc.text(title, M + 4.5, 34);
  doc.setFont('helvetica', 'normal');
  doc.setFontSize(8.5);
  doc.setTextColor(...muted);
  doc.text(`${subtitle}${subtitle ? '  ·  ' : ''}${rows.length} record${rows.length === 1 ? '' : 's'}`, M + 4.5, 39.5);

  let y = 45;
  if (summary.length) {
    const gap = 3;
    const n = Math.min(summary.length, landscape ? 6 : 4);
    const w = (W - M * 2 - gap * (n - 1)) / n;
    summary.slice(0, n).forEach((s, i) => {
      const cx = M + i * (w + gap);
      doc.setFillColor(255, 255, 255);
      doc.setDrawColor(...line);
      doc.roundedRect(cx, y, w, 15, 2, 2, 'FD');
      doc.setFontSize(7);
      doc.setTextColor(...muted);
      doc.text(String(s.label), cx + 3.5, y + 5.5);
      doc.setFont('helvetica', 'bold');
      doc.setFontSize(11.5);
      doc.setTextColor(...ink);
      doc.text(String(s.value), cx + 3.5, y + 12);
      doc.setFont('helvetica', 'normal');
    });
    y += 20;
  }

  autoTable(doc, {
    startY: y,
    margin: { left: M, right: M, top: 14, bottom: 14 },
    head: [columns.map((c) => c.label)],
    body: rows.map((r) => columns.map((c) => String(cell(r[c.key])))),
    theme: 'plain',
    styles: { font: 'helvetica', fontSize: rows.length > 300 ? 7 : 8, textColor: ink, cellPadding: { top: 2.2, bottom: 2.2, left: 2, right: 2 }, overflow: 'linebreak' },
    headStyles: { fillColor: [236, 253, 245], textColor: [6, 95, 70], fontStyle: 'bold', fontSize: 7.5 },
    alternateRowStyles: { fillColor: [248, 250, 252] },
    columnStyles: Object.fromEntries(columns.map((c, i) => [i, c.align === 'right' ? { halign: 'right' } : {}])),
    didDrawPage: () => {
      doc.setFillColor(...brand);
      doc.rect(0, 0, W, 2.5, 'F');
    },
  });

  const pages = doc.internal.getNumberOfPages();
  for (let p = 1; p <= pages; p++) {
    doc.setPage(p);
    doc.setDrawColor(...line);
    doc.line(M, H - 9, W - M, H - 9);
    doc.setFontSize(7.5);
    doc.setTextColor(...muted);
    doc.text(`${title} · ${schoolName}`, M, H - 5);
    doc.text(`Page ${p} of ${pages}`, W - M, H - 5, { align: 'right' });
  }
  doc.save(`${safeName(filename || title)}-${stamp()}.pdf`);
}
