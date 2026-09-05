import { jsPDF } from 'jspdf';
import autoTable from 'jspdf-autotable';

export function useExport() {
  const exportToCsv = (filename, columns, rows) => {
    const header = columns.map((c) => c.label).join(',');
    const body = rows
      .map((row) =>
        columns
          .map((c) => {
            const val = String(row[c.key] ?? '').replace(/"/g, '""');
            return `"${val}"`;
          })
          .join(',')
      )
      .join('\n');
    const blob = new Blob([`${header}\n${body}`], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `${filename}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
  };

  const exportToPdf = (title, columns, rows, filename = 'report') => {
    const doc = new jsPDF({ orientation: 'landscape' });
    doc.setFontSize(14);
    doc.text(title, 14, 16);
    autoTable(doc, {
      startY: 22,
      head: [columns.map((c) => c.label)],
      body: rows.map((row) => columns.map((c) => String(row[c.key] ?? ''))),
      styles: { fontSize: 8 },
      headStyles: { fillColor: [30, 58, 138] }
    });
    doc.save(`${filename}.pdf`);
  };

  return { exportToCsv, exportToPdf };
}
