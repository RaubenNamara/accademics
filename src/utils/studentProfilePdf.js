import { jsPDF } from 'jspdf';
import autoTable from 'jspdf-autotable';

const GREEN = [21, 135, 74];
const GREEN_TINT = [239, 251, 243];
const DARK = [17, 24, 39];
const MUTED = [90, 99, 112];
const LINE = [221, 226, 232];
const M = 14; // page margin (mm)

const text = (v, fallback = '-') => {
  const s = v === null || v === undefined ? '' : String(v).trim();
  return s && s !== '0000-00-00' ? s : fallback;
};
const date = (v) => {
  const s = text(v, '');
  if (!s) return '-';
  const d = new Date(`${s.slice(0, 10)}T00:00:00`);
  return Number.isNaN(d.getTime()) ? s : d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
};
const cap = (v) => text(v).replace(/^\w/, (c) => c.toUpperCase());
const STATUS = { active: 'In school', completed_o_level: 'Completed S.4', completed_a_level: 'Completed S.6', left: 'Left' };

/** Fetches an image as a data URL (null when it cannot be read, e.g. missing file). */
export async function imageAsDataUrl(url) {
  if (!url) return null;
  try {
    const res = await fetch(url);
    if (!res.ok) return null;
    const blob = await res.blob();
    if (!/^image\/(png|jpe?g)$/.test(blob.type)) return null;
    return await new Promise((resolve) => {
      const r = new FileReader();
      r.onload = () => resolve(r.result);
      r.onerror = () => resolve(null);
      r.readAsDataURL(blob);
    });
  } catch {
    return null;
  }
}

function addImage(doc, dataUrl, x, y, maxW, maxH) {
  try {
    const p = doc.getImageProperties(dataUrl);
    const scale = Math.min(maxW / p.width, maxH / p.height);
    const w = p.width * scale;
    const h = p.height * scale;
    doc.addImage(dataUrl, p.fileType || 'PNG', x + (maxW - w) / 2, y + (maxH - h) / 2, w, h);
    return true;
  } catch {
    return false;
  }
}

function header(doc, settings) {
  const W = doc.internal.pageSize.getWidth();
  if (settings.logo) addImage(doc, settings.logo, M, 10, 22, 22);
  doc.setTextColor(...DARK);
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(14.5);
  doc.text(text(settings.school_name, 'School').toUpperCase(), W / 2, 16, { align: 'center', maxWidth: W - 2 * M - 52 });
  doc.setFont('helvetica', 'italic');
  doc.setFontSize(8.5);
  doc.setTextColor(...MUTED);
  if (settings.motto) doc.text(`“${settings.motto}”`, W / 2, 21, { align: 'center' });
  doc.setFont('helvetica', 'normal');
  const lines = [settings.address?.replace(/\s*\n\s*/g, ', '), [settings.phone, settings.email, settings.website].filter(Boolean).join('  ·  ')].filter(Boolean);
  lines.forEach((l, i) => doc.text(l, W / 2, 25.5 + i * 4, { align: 'center', maxWidth: W - 2 * M - 52 }));
  doc.setDrawColor(...GREEN);
  doc.setLineWidth(0.8);
  doc.line(M, 35, W - M, 35);
  return 35;
}

function sectionTitle(doc, y, title) {
  const W = doc.internal.pageSize.getWidth();
  if (y > doc.internal.pageSize.getHeight() - 40) {
    doc.addPage();
    y = 20;
  }
  doc.setFillColor(...GREEN_TINT);
  doc.rect(M, y, W - 2 * M, 7, 'F');
  doc.setFillColor(...GREEN);
  doc.rect(M, y, 1.2, 7, 'F');
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(9.5);
  doc.setTextColor(...GREEN);
  doc.text(title.toUpperCase(), M + 4, y + 4.8);
  return y + 9;
}

const tableDefaults = {
  theme: 'grid',
  margin: { left: M, right: M, top: 20 },
  styles: { font: 'helvetica', fontSize: 8.8, cellPadding: 1.8, textColor: DARK, lineColor: LINE, lineWidth: 0.2, valign: 'middle' },
  headStyles: { fillColor: GREEN, textColor: 255, fontStyle: 'bold', fontSize: 8.3 },
  alternateRowStyles: { fillColor: [249, 251, 250] },
};

/** Two label/value pairs per row. */
function detailsTable(doc, y, pairs) {
  const rows = [];
  for (let i = 0; i < pairs.length; i += 2) rows.push([pairs[i][0], pairs[i][1], pairs[i + 1]?.[0] ?? '', pairs[i + 1]?.[1] ?? '']);
  autoTable(doc, {
    ...tableDefaults,
    startY: y,
    theme: 'plain',
    body: rows,
    styles: { ...tableDefaults.styles, cellPadding: { top: 1.4, bottom: 1.4, left: 2, right: 2 } },
    columnStyles: { 0: { textColor: MUTED, cellWidth: 30 }, 1: { fontStyle: 'bold', cellWidth: 61 }, 2: { textColor: MUTED, cellWidth: 30 }, 3: { fontStyle: 'bold' } },
    tableLineColor: LINE,
    tableLineWidth: 0.2,
  });
  return doc.lastAutoTable.finalY + 5;
}

/**
 * A one-file student record: school header, photo, personal and school details, subjects offered,
 * academic summary, parents/guardians, welfare notes, documents and recent results.
 */
export function buildStudentProfilePdf({ student, parents = [], results = [], subjects, settings = {}, photo = null }) {
  const doc = new jsPDF({ unit: 'mm', format: 'a4' });
  const W = doc.internal.pageSize.getWidth();
  let y = header(doc, settings) + 7;

  // Title band with name and photo
  const photoW = 30;
  const photoH = 36;
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(10);
  doc.setTextColor(...GREEN);
  doc.text('STUDENT PROFILE', M, y + 3);
  doc.setFontSize(17);
  doc.setTextColor(...DARK);
  doc.text(text(student.full_name), M, y + 11, { maxWidth: W - 2 * M - photoW - 6 });
  doc.setFont('helvetica', 'normal');
  doc.setFontSize(9.5);
  doc.setTextColor(...MUTED);
  doc.text(`Admission No. ${text(student.admission_number)}   ·   ${text(student.class)} ${text(student.stream, '')}   ·   ${text(student.level)}`, M, y + 17);
  const status = STATUS[student.status] || 'In school';
  doc.setFillColor(...(student.status && student.status !== 'active' ? [243, 244, 246] : GREEN_TINT));
  doc.roundedRect(M, y + 20, doc.getTextWidth(status) + 8, 6, 3, 3, 'F');
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(8);
  doc.setTextColor(...(student.status && student.status !== 'active' ? MUTED : GREEN));
  doc.text(status, M + 4, y + 24.2);

  const px = W - M - photoW;
  doc.setDrawColor(...LINE);
  doc.setLineWidth(0.3);
  doc.rect(px, y - 2, photoW, photoH);
  if (!(photo && addImage(doc, photo, px + 0.5, y - 1.5, photoW - 1, photoH - 1))) {
    doc.setFillColor(243, 244, 246);
    doc.rect(px + 0.3, y - 1.7, photoW - 0.6, photoH - 0.6, 'F');
    const initials = text(student.full_name, '?').split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(18);
    doc.setTextColor(156, 163, 175);
    doc.text(initials, px + photoW / 2, y + photoH / 2, { align: 'center' });
    doc.setFontSize(6.5);
    doc.text('PHOTO', px + photoW / 2, y + photoH / 2 + 6, { align: 'center' });
  }
  y += photoH + 4;

  y = sectionTitle(doc, y, 'Personal details');
  y = detailsTable(doc, y, [
    ['Full name', text(student.full_name)], ['Gender', cap(student.gender)],
    ['Date of birth', date(student.date_of_birth)], ['LIN', text(student.lin)],
    ['Admission no.', text(student.admission_number)], ['Enrolled', date(student.enrollment_date)],
    ['Class', `${text(student.class)} ${text(student.stream, '')}`.trim()], ['Level', text(student.level)],
    ['Former school', text(student.former_school)], ['Status', status],
  ]);

  // Subjects offered
  y = sectionTitle(doc, y, `Subjects offered${subjects?.level ? ` (${subjects.level})` : ''}`);
  const subjectRows = [
    ...(subjects?.compulsory || []).map((s) => [s.subject_name, s.subject_code, 'Compulsory']),
    ...(subjects?.options || []).map((s) => [s.subject_name, s.subject_code, subjects.level === 'A-Level' ? 'Combination' : 'Option']),
  ].map((r, i) => [i + 1, ...r]);
  if (subjectRows.length) {
    autoTable(doc, {
      ...tableDefaults,
      startY: y,
      head: [['#', 'Subject', 'Code', 'Type']],
      body: subjectRows,
      columnStyles: { 0: { cellWidth: 9, halign: 'center' }, 2: { cellWidth: 34 }, 3: { cellWidth: 34 } },
      didParseCell: (d) => {
        if (d.section === 'body' && d.column.index === 3) d.cell.styles.textColor = d.cell.raw === 'Compulsory' ? GREEN : MUTED;
      },
    });
    y = doc.lastAutoTable.finalY + 2;
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(8);
    doc.setTextColor(...MUTED);
    doc.text(`${subjectRows.length} subject(s): ${(subjects?.compulsory || []).length} compulsory, ${(subjects?.options || []).length} ${subjects?.level === 'A-Level' ? 'in the combination' : 'optional'}.`, M, y + 3);
    y += 8;
  } else {
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(9);
    doc.setTextColor(...MUTED);
    doc.text('No subjects recorded.', M, y + 4);
    y += 10;
  }

  if (student.aggregate || student.division) {
    y = sectionTitle(doc, y, 'Academic summary');
    y = detailsTable(doc, y, student.level === 'A-Level'
      ? [['Grade', text(student.division)], ['', '']]
      : [['Aggregate', text(student.aggregate)], ['Division', text(student.division)]]);
  }

  y = sectionTitle(doc, y, 'Parents / guardians');
  if (parents.length) {
    autoTable(doc, {
      ...tableDefaults,
      startY: y,
      head: [['Name', 'Relationship', 'Phone', 'Email', 'Address', 'Primary']],
      body: parents.map((p) => [text(p.full_name), cap(p.relationship), text(p.phone), text(p.email), text(p.address), Number(p.is_primary_contact) ? 'Yes' : '']),
      columnStyles: { 0: { fontStyle: 'bold' }, 5: { halign: 'center', cellWidth: 16 } },
    });
    y = doc.lastAutoTable.finalY + 5;
  } else {
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(9);
    doc.setTextColor(...MUTED);
    doc.text('No parent or guardian recorded.', M, y + 4);
    y += 10;
  }

  const notes = [['Medical notes', student.medical_notes], ['Special needs', student.special_needs], ['Behaviour notes', student.behaviour_notes]].filter(([, v]) => text(v, ''));
  if (notes.length) {
    y = sectionTitle(doc, y, 'Welfare');
    autoTable(doc, {
      ...tableDefaults,
      startY: y,
      theme: 'plain',
      body: notes.map(([k, v]) => [k, text(v)]),
      columnStyles: { 0: { textColor: MUTED, cellWidth: 34 } },
      tableLineColor: LINE,
      tableLineWidth: 0.2,
    });
    y = doc.lastAutoTable.finalY + 5;
  }

  const docs = [
    ...(student.former_school_documents || []).map((d) => ['Former school', text(d.filename || d.file_name || d.original_name)]),
    ...(student.behaviour_documents || []).map((d) => ['Discipline', text(d.filename || d.file_name || d.original_name)]),
  ];
  if (docs.length) {
    y = sectionTitle(doc, y, 'Documents on file');
    autoTable(doc, { ...tableDefaults, startY: y, head: [['Type', 'File']], body: docs, columnStyles: { 0: { cellWidth: 34 } } });
    y = doc.lastAutoTable.finalY + 5;
  }

  if (results.length) {
    y = sectionTitle(doc, y, 'Recorded results');
    autoTable(doc, {
      ...tableDefaults,
      startY: y,
      head: [['Year', 'Term', 'Exam', 'Subject', 'Marks', 'Grade']],
      body: results.slice(0, 60).map((r) => [r.year, r.term, r.exam_type, text(r.subject_name), text(r.marks), text(r.grade)]),
      columnStyles: { 0: { cellWidth: 16 }, 1: { cellWidth: 14 }, 4: { halign: 'center', cellWidth: 18 }, 5: { halign: 'center', cellWidth: 16 } },
    });
  }

  // Footer on every page
  const pages = doc.internal.getNumberOfPages();
  const H = doc.internal.pageSize.getHeight();
  const printed = new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
  for (let i = 1; i <= pages; i++) {
    doc.setPage(i);
    doc.setDrawColor(...LINE);
    doc.setLineWidth(0.3);
    doc.line(M, H - 13, W - M, H - 13);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(7.5);
    doc.setTextColor(...MUTED);
    doc.text(`Confidential student record · ${text(student.full_name)} (${text(student.admission_number)})`, M, H - 8.5);
    doc.text(`Printed ${printed} · Skool Monitor · Page ${i} of ${pages}`, W - M, H - 8.5, { align: 'right' });
  }
  return doc;
}
