// Turn a directive `schema` map (from the manifest) into a list of editable
// fields. Schema values look like: 'string', 'string?', 'int', 'array',
// 'object?', 'any', or 'enum:success,error,info'.
export function fieldsFromSchema(schema) {
  if (!schema || typeof schema !== 'object') return [];

  return Object.entries(schema).map(([key, spec]) => {
    const raw = String(spec || 'string');
    const optional = raw.endsWith('?');
    const base = optional ? raw.slice(0, -1) : raw;

    let type = 'text';
    let options = null;

    if (base.startsWith('enum:')) {
      type = 'enum';
      options = base.slice(5).split(',').map((s) => s.trim()).filter(Boolean);
    } else if (base === 'int' || base === 'number') {
      type = 'number';
    } else if (base === 'object' || base === 'array' || base === 'any') {
      type = 'json';
    } else {
      type = 'text';
    }

    return { key, type, options, optional, raw: base };
  });
}

// Default empty value for a field type.
export function defaultValue(field) {
  if (field.type === 'number') return 0;
  if (field.type === 'json') return field.raw === 'array' ? [] : {};
  if (field.type === 'enum') return field.options && field.options.length ? field.options[0] : '';
  return '';
}
