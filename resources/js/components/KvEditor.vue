<template>
  <div class="kv">
    <span class="kv-label">{{ label }}</span>
    <div v-for="(row, i) in rows" :key="i" class="kv-row">
      <input type="text" v-model="row.k" placeholder="key" @input="$emit('change')" />
      <input type="text" class="mono" v-model="row.v" placeholder="value" @input="$emit('change')" />
      <button type="button" class="kv-x" @click="remove(i)" title="Remove">×</button>
    </div>
    <button type="button" class="kv-add" @click="add">+ {{ label }}</button>
  </div>
</template>

<script>
export default {
  name: 'KvEditor',
  props: {
    label: { type: String, default: 'Item' },
    rows: { type: Array, required: true },
  },
  emits: ['change'],
  methods: {
    add() { this.rows.push({ k: '', v: '' }); },
    remove(i) { this.rows.splice(i, 1); this.$emit('change'); },
  },
};
</script>

<style>
.kv { display: flex; flex-direction: column; gap: 6px; }
.kv-label { font-size: 11px; font-weight: 600; color: #475569; }
.kv-row { display: flex; gap: 6px; align-items: center; }
.kv-row input { border: 1px solid #cbd5e1; border-radius: 8px; padding: 7px 9px; font-size: 12px; outline: none; }
.kv-row input:first-child { flex: 0 0 34%; }
.kv-row input:nth-child(2) { flex: 1; }
.kv-row input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.1); }
.kv-x { border: 0; background: transparent; color: #64748b; font-size: 16px; cursor: pointer; }
.kv-x:hover { color: #dc2626; }
.kv-add { align-self: flex-start; border: 1px dashed #cbd5e1; background: #fff; color: #6366f1; border-radius: 8px; padding: 5px 10px; font-size: 11px; font-weight: 700; cursor: pointer; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
</style>
