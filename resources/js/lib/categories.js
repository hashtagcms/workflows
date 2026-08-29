// Colour system for directive categories. Each category maps to a small palette
// used for chips, card accents, and the palette groups. Kept vivid but readable
// in the admin's light theme.
export const CATEGORY_COLORS = {
  feedback:   { bg: '#fef3c7', fg: '#92400e', accent: '#f59e0b' }, // amber
  navigation: { bg: '#dbeafe', fg: '#1e40af', accent: '#3b82f6' }, // blue
  cart:       { bg: '#dcfce7', fg: '#166534', accent: '#22c55e' }, // green
  content:    { bg: '#ede9fe', fg: '#5b21b6', accent: '#8b5cf6' }, // violet
  state:      { bg: '#e2e8f0', fg: '#334155', accent: '#64748b' }, // slate
  auth:       { bg: '#fee2e2', fg: '#991b1b', accent: '#ef4444' }, // red
  device:     { bg: '#ccfbf1', fg: '#115e59', accent: '#14b8a6' }, // teal
  analytics:  { bg: '#fce7f3', fg: '#9d174d', accent: '#ec4899' }, // pink
  payments:   { bg: '#e0f2fe', fg: '#075985', accent: '#0ea5e9' }, // sky
  flow:       { bg: '#e0e7ff', fg: '#3730a3', accent: '#6366f1' }, // indigo
  growth:     { bg: '#ffedd5', fg: '#9a3412', accent: '#f97316' }, // orange
};

const FALLBACK = { bg: '#f1f5f9', fg: '#475569', accent: '#94a3b8' };

export function colorFor(category) {
  return CATEGORY_COLORS[category] || FALLBACK;
}
