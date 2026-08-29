<template>
  <div class="vb">
    <div v-for="(row, i) in rows" :key="i" class="vb-row">
      <input type="text" v-model="row.field" placeholder="field (e.g. code)" @change="emitRules" />
      <input type="text" v-model="row.rule" class="mono" placeholder="required|string|min:1" @change="emitRules" />
      <button type="button" class="vb-x" @click="removeRow(i)" title="Remove">×</button>
    </div>
    <button type="button" class="vb-add" @click="addRow">+ Add rule</button>
    <p class="vb-hint" v-if="!rows.length">No validation — the workflow runs for any payload.</p>
  </div>
</template>

<script>
export default {
  name: 'ValidationBuilder',
  props: {
    validation: { type: Object, default: () => ({ rules: {}, messages: {} }) },
  },
  emits: ['update:validation'],
  data() {
    const rules = (this.validation && this.validation.rules) || {};
    return {
      rows: Object.keys(rules).map((field) => ({ field, rule: rules[field] })),
    };
  },
  methods: {
    addRow() {
      this.rows.push({ field: '', rule: '' });
    },
    removeRow(i) {
      this.rows.splice(i, 1);
      this.emitRules();
    },
    emitRules() {
      const rules = {};
      this.rows.forEach((r) => {
        const f = (r.field || '').trim();
        if (f) rules[f] = r.rule || '';
      });
      // Preserve the whole validation object (messages, on_error, and any other
      // keys the visual builder doesn't model); only `rules` is edited here.
      const validation = Object.assign({}, this.validation || {});
      if (Object.keys(rules).length) {
        validation.rules = rules;
      } else {
        delete validation.rules;
      }
      this.$emit('update:validation', validation);
    },
  },
};
</script>

<style>
.vb { display: flex; flex-direction: column; gap: 8px; }
.vb-row { display: flex; gap: 8px; align-items: center; }
.vb-row input { border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 10px; font-size: 12px; outline: none; }
.vb-row input:first-child { flex: 0 0 34%; }
.vb-row input:nth-child(2) { flex: 1; }
.vb-row input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.1); }
.vb-x { border: 0; background: transparent; color: #64748b; font-size: 18px; cursor: pointer; }
.vb-x:hover { color: #dc2626; }
.vb-add { align-self: flex-start; border: 1px dashed #cbd5e1; background: #fff; color: #6366f1; border-radius: 8px; padding: 6px 12px; font-size: 11px; font-weight: 700; cursor: pointer; }
.vb-hint { font-size: 11px; color: #94a3b8; margin: 0; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
</style>
