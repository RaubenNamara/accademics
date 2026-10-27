import { jsPDF } from 'jspdf';
import autoTable from 'jspdf-autotable';
import { PLAN_SECTIONS, SELF_ASSESSMENT, durationText, longDate, timeRange } from '../components/lessonPlans/planMeta.js';

const M = 20; // margins (mm), like the paper template
const BLACK = [0, 0, 0];
const GRID = { lineColor: BLACK, lineWidth: 0.25, textColor: BLACK, font: 'times', fontSize: 11, cellPadding: { top: 1, bottom: 1, left: 1.6, right: 1.6 } };

const lines = (v) => String(v || '').split(/\r?\n/).map((l) => l.replace(/^[•\-*]\s*/, '').trim()).filter(Boolean);

/** The lesson plan as the school's paper template (Times, ruled grids). */
export function buildLessonPlanPdf(plan, settings = {}) {
  const doc = new jsPDF({ unit: 'mm', format: 'a4' });
  const W = doc.internal.pageSize.getWidth();
  const H = doc.internal.pageSize.getHeight();
  const width = W - 2 * M;
  let y = 22;

  const ensure = (need) => {
    if (y + need > H - 18) {
      doc.addPage();
      y = 22;
    }
  };
  const heading = (label, size = 11) => {
    ensure(12);
    doc.setFont('times', 'bold');
    doc.setFontSize(size);
    doc.setTextColor(...BLACK);
    doc.text(label, M, y);
    y += 5;
  };
  const paragraph = (text) => {
    doc.setFont('times', 'normal');
    doc.setFontSize(11);
    const wrapped = doc.splitTextToSize(String(text), width);
    wrapped.forEach((l) => {
      ensure(6);
      doc.text(l, M, y);
      y += 5;
    });
    y += 3;
  };
  const bullets = (items) => {
    doc.setFont('times', 'normal');
    doc.setFontSize(11);
    items.forEach((item) => {
      // "Label: text" keeps the label bold, as in the template.
      const m = /^([^:]{2,60}):\s*(.*)$/.exec(item);
      const wrapped = doc.splitTextToSize(m ? `${m[1]}: ${m[2]}` : item, width - 10);
      wrapped.forEach((l, i) => {
        ensure(6);
        if (i === 0) doc.text('•', M + 4, y);
        if (i === 0 && m) {
          doc.setFont('times', 'bold');
          doc.text(`${m[1]}:`, M + 10, y);
          const lw = doc.getTextWidth(`${m[1]}: `);
          doc.setFont('times', 'normal');
          doc.text(l.slice(m[1].length + 2), M + 10 + lw, y);
        } else {
          doc.text(l, M + 10, y);
        }
        y += 5;
      });
    });
    y += 3;
  };
  const rule = () => {
    ensure(8);
    doc.setDrawColor(160, 160, 160);
    doc.setLineWidth(0.5);
    doc.line(M, y, W - M, y);
    y += 8;
  };

  // Title
  doc.setFont('times', 'bold');
  doc.setFontSize(12);
  doc.text(String(settings.school_name || "St. Mark's College Namagoma").toUpperCase(), M, y);
  y += 5;
  doc.text('LESSON PLAN', M, y);
  y += 4;

  // Header grid
  const p = plan;
  const bold = (v) => ({ content: v || '', styles: { fontStyle: 'bold' } });
  autoTable(doc, {
    startY: y,
    theme: 'grid',
    margin: { left: M, right: M },
    styles: GRID,
    columnStyles: { 0: { fontStyle: 'bold', cellWidth: 21 }, 1: { cellWidth: 46 }, 2: { fontStyle: 'bold', cellWidth: 35 } },
    body: [
      ['Week:', bold(p.week_number ? `Week ${p.week_number}` : ''), 'Date:', bold(longDate(p.lesson_date))],
      ['Subject:', p.subject_name || '', 'Time:', timeRange(p.start_time, p.duration_minutes)],
      ['Teacher:', bold(p.teacher_name), 'Duration:', durationText(p.duration_minutes)],
      ['Class:', bold(p.full_class_name), 'Number of students:', p.learners ? `${p.learners} Learners` : ''],
      ['Term:', p.term ? `Term ${p.term}` : '', 'Theme:', p.theme || ''],
      ['Topic:', bold(p.topic), 'Sub-Topic:', bold(p.subtopic)],
    ],
  });
  y = doc.lastAutoTable.finalY + 8;

  for (const s of PLAN_SECTIONS) {
    if (!p[s.key]) continue;
    if (s.divider) rule();
    heading(`${s.label}:`);
    if (s.list) bullets(lines(p[s.key]));
    else paragraph(p[s.key]);
  }

  rule();
  ensure(70); // keep the heading, table header and first phase together
  heading('Learning Procedure', 13);
  y += 1;
  const procedure = p.procedure?.length
    ? p.procedure.map((r) => [`${r.minutes}\nmin`, r.teacher, r.learners])
    : [['', [p.introduction, p.development, p.conclusion].filter(Boolean).join('\n\n'), '']];
  autoTable(doc, {
    startY: y,
    theme: 'grid',
    margin: { left: M, right: M, top: 20 },
    styles: GRID,
    headStyles: { fillColor: false, fontStyle: 'bold', textColor: BLACK, lineColor: BLACK, lineWidth: 0.25 },
    columnStyles: { 0: { cellWidth: 14, fontStyle: 'bold' }, 1: { cellWidth: (width - 14) * 0.53 } },
    head: [['Time per phase', 'Teacher Activity: Observation, Conversation, Product', "Learners' Activity: Discovery, Explanatory, Analysis and Application"]],
    body: procedure,
    rowPageBreak: 'avoid',
    // "Group Presentations: Facilitates …", the phase name goes on its own bold line, as in the template.
    didParseCell: (d) => {
      if (d.section !== 'body' || d.column.index === 0) return;
      const m = /^([^:\n]{2,60}):\s*([\s\S]*)$/.exec(String(d.cell.raw || ''));
      if (!m) return;
      d.cell.text = [`${m[1]}:`, ...(m[2] ? [m[2]] : [])];
      d.cell.boldLead = true;
      d.cell.styles.textColor = [255, 255, 255]; // drawn again below with the bold first line
    },
    didDrawCell: (d) => {
      if (!d.cell.boldLead) return;
      const pos = d.cell.getTextPos();
      const lh = (d.cell.styles.fontSize * doc.getLineHeightFactor()) / doc.internal.scaleFactor;
      doc.setTextColor(...BLACK);
      doc.setFontSize(d.cell.styles.fontSize);
      d.cell.text.forEach((line, i) => {
        doc.setFont('times', i === 0 ? 'bold' : 'normal');
        doc.text(line, pos.x, pos.y + i * lh, { baseline: 'top' });
      });
      doc.setFont('times', 'normal');
    },
  });
  y = doc.lastAutoTable.finalY + 8;

  if (p.assessment) {
    rule();
    heading('Assessment Strategies:');
    paragraph(p.assessment);
  }

  rule();
  heading(`${SELF_ASSESSMENT.label}:`);
  doc.setFont('times', p.reflection ? 'normal' : 'italic');
  doc.setFontSize(11);
  doc.splitTextToSize(p.reflection || '(To be completed after the lesson.)', width).forEach((l) => {
    ensure(6);
    doc.text(l, M, y);
    y += 5;
  });

  const pages = doc.internal.getNumberOfPages();
  for (let i = 1; i <= pages; i++) {
    doc.setPage(i);
    doc.setFont('times', 'normal');
    doc.setFontSize(8.5);
    doc.setTextColor(110, 110, 110);
    doc.text(`Page ${i} of ${pages}`, W - M, H - 10, { align: 'right' });
  }
  return doc;
}

export function lessonPlanFileName(p) {
  return `${p.full_class_name || ''} ${p.subject_name || ''} lesson plan ${String(p.lesson_date || '').slice(0, 10)}.pdf`.replace(/[\\/:*?"<>|]+/g, '-').replace(/\s+/g, ' ').trim();
}
