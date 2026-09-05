// Mirrors the level/span helpers in backend/config/HrHelpers.php (tt_class_level,
// tt_span_options) so the UI offers exactly the lesson lengths the server will accept.

export function getClassLevel(className) {
  const match = /(\d+)/.exec(className || '');
  const num = match ? parseInt(match[1], 10) : 0;
  return num >= 5 ? 'A-Level' : 'O-Level';
}

export function getSpanOptions(level) {
  const options = [
    { value: 1, label: 'Single (40 min)' },
    { value: 2, label: 'Double (80 min)' },
  ];
  if (level === 'A-Level') {
    options.push(
      { value: 3, label: 'Triple (120 min)' },
      { value: 4, label: 'Quadruple (160 min)' }
    );
  }
  return options;
}
