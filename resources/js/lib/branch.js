// Pure helpers for editing a workflow branch (on_success / on_failure) in the
// builder. Kept out of the component so the round-trip guarantee is unit-testable.

// Merge an edit into a branch WITHOUT dropping keys the visual builder doesn't
// model (e.g. `data`, or anything custom). This is what preserves fidelity when
// a branch is edited in the visual view.
export function mergeBranch(branch, patch) {
  return Object.assign({}, branch || {}, patch);
}

// A branch is "empty" (and can be removed from the config) only when it has no
// message, no directives, AND no data.
export function branchIsEmpty(b) {
  if (!b) return true;
  const hasMsg = !!(b.message && b.message.length);
  const hasDir = Array.isArray(b.directives) && b.directives.length > 0;
  const hasData = !!(b.data && typeof b.data === 'object' && Object.keys(b.data).length > 0);
  return !(hasMsg || hasDir || hasData);
}
