import { mergeBranch, branchIsEmpty } from '../../resources/js/lib/branch';

describe('branch editing helpers (builder round-trip fidelity)', () => {
  test('mergeBranch preserves `data` when editing directives', () => {
    const branch = {
      message: 'Applied',
      directives: [{ type: 'toast', message: 'hi' }],
      data: { photos: '{{ response.body }}' },
    };

    const next = mergeBranch(branch, { directives: [{ type: 'haptic', intensity: 'success' }] });

    // the edit applied…
    expect(next.directives).toEqual([{ type: 'haptic', intensity: 'success' }]);
    // …but data (and message) survived
    expect(next.data).toEqual({ photos: '{{ response.body }}' });
    expect(next.message).toBe('Applied');
  });

  test('mergeBranch keeps any unmodeled custom key', () => {
    const branch = { directives: [], somethingCustom: 42 };
    const next = mergeBranch(branch, { message: 'x' });
    expect(next.somethingCustom).toBe(42);
    expect(next.message).toBe('x');
  });

  test('mergeBranch does not mutate the original branch', () => {
    const branch = { data: { a: 1 } };
    const next = mergeBranch(branch, { message: 'x' });
    expect(next).not.toBe(branch);
    expect(branch.message).toBeUndefined();
  });

  test('branchIsEmpty treats a data-only branch as non-empty', () => {
    expect(branchIsEmpty({ data: { x: 1 } })).toBe(false);
  });

  test('branchIsEmpty is true only with no message, directives, or data', () => {
    expect(branchIsEmpty({ message: '', directives: [], data: {} })).toBe(true);
    expect(branchIsEmpty({})).toBe(true);
    expect(branchIsEmpty(null)).toBe(true);
    expect(branchIsEmpty({ message: 'hi' })).toBe(false);
    expect(branchIsEmpty({ directives: [{ type: 'toast' }] })).toBe(false);
  });
});
