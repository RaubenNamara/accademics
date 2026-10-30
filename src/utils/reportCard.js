import { jsPDF } from 'jspdf';
import autoTable from 'jspdf-autotable';

const GREEN = [5, 150, 105];
const DARK = [17, 24, 39];
const MUTED = [75, 85, 99];
const MARGIN = 14;

const fmt = (n) => (n === null || n === undefined || n === '' ? '' : String(Math.round(Number(n) * 10) / 10));
const initials = (names) => (names || '')
  .split(',')
  .map((n) => n.trim().split(/\s+/).filter(Boolean).map((w) => w[0].toUpperCase()).join(''))
  .filter(Boolean)
  .join(', ');
const ordinal = (n) => {
  const s = ['th', 'st', 'nd', 'rd'];
  const v = n % 100;
  return `${n}${s[(v - 20) % 10] || s[v] || s[0]}`;
};

function header(doc, settings, termLabel) {
  const pageW = doc.internal.pageSize.getWidth();
  let y = MARGIN;
  if (settings.logo) {
    try {
      const props = doc.getImageProperties(settings.logo);
      const h = 24;
      const w = Math.min(30, (props.width / props.height) * h);
      doc.addImage(settings.logo, props.fileType || 'PNG', MARGIN, y, w, h);
    } catch {
      /* an unreadable logo should not stop the report */
    }
  }
  doc.setTextColor(...DARK);
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(15);
  doc.text((settings.school_name || '').toUpperCase(), pageW / 2, y + 6, { align: 'center', maxWidth: pageW - 2 * MARGIN - 64 });
  doc.setFont('helvetica', 'italic');
  doc.setFontSize(9);
  if (settings.motto) doc.text(`“${settings.motto}”`, pageW / 2, y + 11.5, { align: 'center' });
  doc.setFont('helvetica', 'normal');
  doc.setTextColor(...MUTED);
  doc.setFontSize(8.5);
  const contact = [settings.address?.replace(/\s*\n\s*/g, ', '), [settings.phone, settings.email, settings.website].filter(Boolean).join('  ·  ')].filter(Boolean);
  contact.forEach((line, i) => doc.text(line, pageW / 2, y + 16 + i * 4, { align: 'center', maxWidth: pageW - 2 * MARGIN - 64 }));
  y += 28;
  doc.setDrawColor(...GREEN);
  doc.setLineWidth(0.8);
  doc.line(MARGIN, y, pageW - MARGIN, y);
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(11);
  doc.setTextColor(...GREEN);
  doc.text(`STUDENT REPORT CARD  ·  ${termLabel.toUpperCase()}`, pageW / 2, y + 6, { align: 'center' });
  return y + 10;
}

function studentBox(doc, y, card, data) {
  const pageW = doc.internal.pageSize.getWidth();
  const s = card.student;
  const rows = [
    ['Name', s.full_name, 'Class', data.class.full_class_name],
    ['Admission No.', s.admission_number || '-', 'Gender', s.gender || '-'],
    ['LIN', s.lin || '-', 'Position', card.position ? `${ordinal(card.position)} out of ${card.out_of}` : '-'],
  ];
  autoTable(doc, {
    startY: y,
    body: rows,
    theme: 'plain',
    margin: { left: MARGIN, right: MARGIN },
    styles: { fontSize: 9, cellPadding: { top: 1.3, bottom: 1.3, left: 2, right: 2 }, textColor: DARK },
    columnStyles: {
      0: { fontStyle: 'bold', textColor: MUTED, cellWidth: 28 },
      1: { cellWidth: (pageW - 2 * MARGIN) / 2 - 28 },
      2: { fontStyle: 'bold', textColor: MUTED, cellWidth: 22 },
    },
    tableLineColor: [229, 231, 235],
    tableLineWidth: 0.3,
  });
  return doc.lastAutoTable.finalY + 4;
}

// Every assessment the learner did, converted (each AOI out of 10, exams out of 80), then the
// AOI score (best two, out of 20), the exam score (out of 80) and the final mark out of 100.
// Primary schools have no AOIs: the exams make the whole 100, so the AOI and exam-part columns go.
function marksTable(doc, y, card, data) {
  const primary = data.scheme?.school_type === 'primary';
  const head = [['Subject', ...data.exams.map((e) => (e.counts === false ? `${e.name}\n(not counted)` : `${e.name}\n/${fmt(e.out_of)}`)), ...(primary ? [] : ['AOI\n/20', 'Exams\n/80']), 'Final\n/100', 'Grade', 'Descriptor', 'Teacher']];
  const body = card.subjects.map((r) => [
    r.subject,
    ...data.exams.map((e) => fmt(r.scores?.[e.id])),
    ...(primary ? [] : [fmt(r.aoi), fmt(r.exam)]),
    fmt(r.final),
    r.grade || '',
    r.comment || '',
    initials(r.teacher),
  ]);
  if (!body.length) body.push([{ content: 'No marks have been entered for this student yet.', colSpan: head[0].length, styles: { halign: 'center', textColor: MUTED } }]);
  const narrow = data.exams.length > 4;
  const examCols = Object.fromEntries(data.exams.map((_, i) => [i + 1, { halign: 'center', cellWidth: narrow ? 10.5 : 13 }]));
  const n = data.exams.length + (primary ? 0 : 2); // the converted-score columns plus the AOI and exam averages
  if (!primary) {
    Object.assign(examCols, {
      [data.exams.length + 1]: { halign: 'center', cellWidth: 12, fillColor: [240, 249, 255] },
      [data.exams.length + 2]: { halign: 'center', cellWidth: 12, fillColor: [236, 253, 245] },
    });
  }
  autoTable(doc, {
    startY: y,
    head,
    body,
    theme: 'grid',
    margin: { left: MARGIN, right: MARGIN },
    headStyles: { fillColor: GREEN, textColor: 255, fontSize: 7.5, halign: 'center', valign: 'middle' },
    styles: { fontSize: 8.5, cellPadding: 1.6, textColor: DARK, lineColor: [209, 213, 219], lineWidth: 0.2, valign: 'middle' },
    columnStyles: {
      0: { fontStyle: 'bold', halign: 'left' },
      ...examCols,
      [n + 1]: { halign: 'center', fontStyle: 'bold', cellWidth: 15 },
      [n + 2]: { halign: 'center', fontStyle: 'bold', cellWidth: 13 },
      [n + 4]: { halign: 'center', cellWidth: 16 },
    },
    alternateRowStyles: { fillColor: [247, 250, 248] },
  });
  const keyY = doc.lastAutoTable.finalY + 4;
  doc.setFont('helvetica', 'normal');
  doc.setFontSize(7.5);
  doc.setTextColor(...MUTED);
  const basis = { MOT: 'the mid term', EOT: 'the end of term' }[data.scheme?.exam_basis] || 'the mid term and end of term averaged';
  doc.text(primary
    ? `The final mark (out of 100) comes from ${basis}. A blank means not done.`
    : `Each AOI is out of 10 and the best two make 20; the 80 comes from ${basis}. A subject with only AOIs or only exams is scaled to 100. A blank means not done.`, MARGIN, keyY, { maxWidth: doc.internal.pageSize.getWidth() - 2 * MARGIN });
  return keyY + 7;
}

function summary(doc, y, card) {
  const pageW = doc.internal.pageSize.getWidth();
  const w = (pageW - 2 * MARGIN - 8) / 3;
  const boxes = [
    ['Average', card.average !== null ? `${fmt(card.average)}%` : '-'],
    ['Overall grade', card.overall?.grade ? `${card.overall.grade}${card.overall.comment ? ` · ${card.overall.comment}` : ''}` : '-'],
    ['Position', card.position ? `${ordinal(card.position)} of ${card.out_of}` : '-'],
  ];
  boxes.forEach(([label, value], i) => {
    const x = MARGIN + i * (w + 4);
    doc.setDrawColor(...GREEN);
    doc.setFillColor(239, 251, 243);
    doc.setLineWidth(0.3);
    doc.roundedRect(x, y, w, 14, 2, 2, 'FD');
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(7.5);
    doc.setTextColor(...MUTED);
    doc.text(label.toUpperCase(), x + 3, y + 5);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(10.5);
    doc.setTextColor(...DARK);
    doc.text(value, x + 3, y + 11, { maxWidth: w - 6 });
  });
  return y + 19;
}

function comments(doc, y, settings) {
  const pageW = doc.internal.pageSize.getWidth();
  doc.setFont('helvetica', 'normal');
  doc.setFontSize(9);
  doc.setTextColor(...DARK);
  const lines = [
    ["Class teacher's comment", 'Signature'],
    [`Head teacher's comment${settings.head_teacher ? ` (${settings.head_teacher})` : ''}`, 'Signature'],
  ];
  lines.forEach(([label, sig]) => {
    doc.setFont('helvetica', 'bold');
    doc.text(`${label}:`, MARGIN, y);
    doc.setDrawColor(156, 163, 175);
    doc.setLineWidth(0.2);
    doc.line(MARGIN, y + 6, pageW - MARGIN - 50, y + 6);
    doc.line(pageW - MARGIN - 40, y + 6, pageW - MARGIN, y + 6);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(7.5);
    doc.setTextColor(...MUTED);
    doc.text(sig, pageW - MARGIN - 40, y + 9.5);
    doc.setFontSize(9);
    doc.setTextColor(...DARK);
    y += 15;
  });
  return y;
}

function footer(doc, y, settings) {
  const pageW = doc.internal.pageSize.getWidth();
  const pageH = doc.internal.pageSize.getHeight();
  if (settings.next_term_begins) {
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(9);
    doc.setTextColor(...DARK);
    doc.text(`Next term begins: ${settings.next_term_begins}`, MARGIN, y);
    y += 6;
  }
  const scale = settings.grade_scale || [];
  if (scale.length) {
    const sorted = [...scale].sort((a, b) => b.min - a.min);
    const key = sorted.map((g, i) => `${g.grade}: ${fmt(g.min)}–${i === 0 ? 100 : fmt(sorted[i - 1].min - 0.1)}${g.comment ? ` ${g.comment}` : ''}`);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(7.5);
    doc.setTextColor(...MUTED);
    doc.text(`Grading: ${key.join('   ·   ')}`, MARGIN, y, { maxWidth: pageW - 2 * MARGIN });
  }
  doc.setFontSize(7.5);
  doc.setTextColor(...MUTED);
  if (settings.footer_note) doc.text(settings.footer_note, pageW / 2, pageH - 12, { align: 'center', maxWidth: pageW - 2 * MARGIN });
  doc.text(`Printed ${new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}`, MARGIN, pageH - 7);
  doc.text('Skool Monitor', pageW - MARGIN, pageH - 7, { align: 'right' });
}

/**
 * One A4 page per student. `data` is the report_cards.php response; `cards` the students to print
 * (default: everyone in it).
 */
export function buildReportCards(data, cards = data.students) {
  const doc = new jsPDF({ unit: 'mm', format: 'a4' });
  const s = data.session || {};
  const termLabel = `Term ${s.term ?? ''} ${s.academic_year ?? ''}`.trim();
  cards.forEach((card, i) => {
    if (i > 0) doc.addPage();
    let y = header(doc, data.settings, termLabel);
    y = studentBox(doc, y, card, data);
    y = marksTable(doc, y, card, data);
    y = summary(doc, y, card);
    y = comments(doc, y + 2, data.settings);
    footer(doc, y + 2, data.settings);
  });
  return doc;
}

export function reportFileName(data, card) {
  const s = data.session || {};
  const base = card ? `${card.student.full_name}` : `${data.class.full_class_name} report cards`;
  return `${base} - Term ${s.term} ${s.academic_year}.pdf`.replace(/[\\/:*?"<>|]+/g, '-');
}
