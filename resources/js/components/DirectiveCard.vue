<template>
  <div class="dcard" :style="{ borderLeftColor: color.accent }">
    <div class="dcard-head" :style="{ background: color.bg }" @dblclick="collapsed = !collapsed"
         :title="collapsed ? 'Double-click to expand' : 'Double-click to collapse'">
      <span class="dcard-grip" title="Drag to reorder" @click.stop @dblclick.stop>⠿</span>
      <span class="dcard-caret" :style="{ color: color.fg }" @click.stop="collapsed = !collapsed"
            :title="collapsed ? 'Expand' : 'Collapse'">{{ collapsed ? '▸' : '▾' }}</span>
      <span class="dcard-type" :style="{ color: color.fg }">{{ def ? def.label : directive.type }}</span>
      <code class="dcard-key" :style="{ color: color.fg }">{{ directive.type }}</code>
      <button type="button" class="dcard-x" @click.stop="$emit('remove')" @dblclick.stop title="Remove">×</button>
    </div>

    <div v-show="!collapsed">
    <p v-if="def && def.description" class="dcard-desc"><span class="dcard-desc-i">ⓘ</span> {{ def.description }}</p>

    <div class="dcard-body" v-if="fields.length">
      <div v-for="f in fields" :key="f.key" class="dcard-field">
        <label>{{ labelFor(f) }}<span v-if="!f.optional" class="req">*</span>
          <span v-else class="opt">optional</span>
          <span v-if="f.type === 'json'" class="hint">JSON</span></label>
        <span v-if="hintFor(f.key).help" class="field-help">{{ hintFor(f.key).help }}</span>

        <select v-if="f.type === 'enum'" :value="valueOf(f.key)" @change="setField(f.key, $event.target.value)">
          <option v-for="opt in f.options" :key="opt" :value="opt">{{ opt }}</option>
        </select>

        <input v-else-if="f.type === 'number'" type="number" :value="valueOf(f.key)"
               :placeholder="hintFor(f.key).placeholder || ''"
               @input="setField(f.key, numberOrRaw($event.target.value))" />

        <div v-else-if="f.type === 'json'">
          <textarea rows="2" class="mono" v-model="jsonBuffers[f.key]" @input="onJson(f.key)"
                    :class="{ bad: jsonErrors[f.key] }" spellcheck="false"
                    :placeholder="hintFor(f.key).placeholder || (f.raw === 'array' ? '[ ... ]' : '{ ... }')"></textarea>
          <span v-if="jsonErrors[f.key]" class="json-err">Invalid JSON</span>
        </div>

        <div v-else class="dcard-textrow">
          <input type="text" :value="valueOf(f.key)" :placeholder="hintFor(f.key).placeholder || ''"
                 @input="setField(f.key, $event.target.value)" />
          <select class="dcard-token" @change="insertToken(f.key, $event.target.value); $event.target.value=''" title="Insert a dynamic value">
            <option value="">+ value</option>
            <option v-for="t in availableTokens" :key="t.token" :value="t.token">{{ t.label }}</option>
          </select>
        </div>
      </div>

      <p v-if="hasTextField" class="dcard-tip">
        Type text, or use <b>+ value</b> to insert live data — e.g. the coupon the user entered.
      </p>
    </div>
    <div v-else class="dcard-body dcard-empty">No fields to fill — this directive is emitted as-is.</div>
    </div>
  </div>
</template>

<script>
import { colorFor } from '../lib/categories';
import { fieldsFromSchema, defaultValue } from '../lib/schema';
import { TOKENS } from '../lib/tokens';
import { hintFor } from '../lib/fieldHints';

export default {
  name: 'DirectiveCard',
  props: {
    directive: { type: Object, required: true },
    def: { type: Object, default: null },
    hasTarget: { type: Boolean, default: false },
  },
  emits: ['update:directive', 'remove'],
  data() {
    return { jsonBuffers: {}, jsonErrors: {}, collapsed: false };
  },
  computed: {
    color() {
      return colorFor(this.def ? this.def.category : null);
    },
    fields() {
      return this.def ? fieldsFromSchema(this.def.schema) : [];
    },
    // Hide response.* tokens when the workflow has no target to respond with.
    availableTokens() {
      return TOKENS.filter((t) => this.hasTarget || !t.needsTarget);
    },
    hasTextField() {
      return this.fields.some((f) => f.type === 'text');
    },
  },
  methods: {
    hintFor,
    labelFor(f) {
      // Humanise the field key: couponCode -> "Coupon code".
      const spaced = f.key.replace(/([A-Z])/g, ' $1').replace(/[_-]+/g, ' ').trim().toLowerCase();
      return spaced.charAt(0).toUpperCase() + spaced.slice(1);
    },
    valueOf(key) {
      const v = this.directive[key];
      return v === undefined || v === null ? '' : v;
    },
    numberOrRaw(v) {
      if (v === '') return '';
      const n = Number(v);
      return Number.isNaN(n) ? v : n;
    },
    emit(next) {
      this.$emit('update:directive', next);
    },
    setField(key, value) {
      this.emit(Object.assign({}, this.directive, { [key]: value }));
    },
    emitWithout(key) {
      const next = Object.assign({}, this.directive);
      delete next[key];
      this.emit(next);
    },
    // JSON fields use a local buffer so typing never gets reformatted /
    // cursor-jumped by re-stringifying the parsed value.
    onJson(key) {
      const text = this.jsonBuffers[key] || '';
      if (text.trim() === '') {
        this.jsonErrors[key] = false;
        this.emitWithout(key);
        return;
      }
      try {
        const parsed = JSON.parse(text);
        this.jsonErrors[key] = false;
        this.setField(key, parsed);
      } catch (e) {
        // Keep the raw text in the buffer; don't emit invalid JSON.
        this.jsonErrors[key] = true;
      }
    },
    insertToken(key, token) {
      if (!token) return;
      const current = this.valueOf(key);
      this.setField(key, (current ? current + ' ' : '') + token);
    },
  },
  created() {
    let dir = this.directive;

    // Seed missing required fields so the card renders complete.
    if (this.def) {
      const seeded = Object.assign({}, dir);
      let changed = false;
      this.fields.forEach((f) => {
        if (!f.optional && seeded[f.key] === undefined) {
          seeded[f.key] = defaultValue(f);
          changed = true;
        }
      });
      if (changed) { this.emit(seeded); dir = seeded; }
    }

    // Initialise JSON buffers once, from the (seeded) directive.
    const buffers = {};
    this.fields.forEach((f) => {
      if (f.type === 'json') {
        const v = dir[f.key];
        buffers[f.key] = v === undefined || v === '' ? '' : (typeof v === 'string' ? v : JSON.stringify(v, null, 2));
      }
    });
    this.jsonBuffers = buffers;
  },
};
</script>

<style>
.dcard { border: 1px solid #e2e8f0; border-left-width: 4px; border-radius: 12px; background: #fff; overflow: hidden; }
.dcard-head { display: flex; align-items: center; gap: 10px; padding: 8px 12px; user-select: none; }
.dcard-grip { cursor: grab; color: #94a3b8; font-size: 14px; user-select: none; }
.dcard-caret { font-size: 10px; opacity: .7; cursor: pointer; padding: 2px 4px; border-radius: 4px; }
.dcard-caret:hover { opacity: 1; background: rgba(0,0,0,.06); }
.dcard-type { font-size: 12px; font-weight: 800; }
.dcard-key { font-size: 10px; margin-left: auto; opacity: .8; }
.dcard-x { border: 0; background: transparent; color: #64748b; font-size: 18px; line-height: 1; cursor: pointer; padding: 0 2px; }
.dcard-x:hover { color: #dc2626; }
.dcard-desc { margin: 10px 12px 0; padding: 7px 10px; font-size: 11px; color: #475569; background: #f8fafc; border: 1px solid #eef2f7; border-radius: 8px; }
.dcard-desc-i { color: #6366f1; font-weight: 700; margin-right: 3px; }
.dcard-body { padding: 12px; display: flex; flex-direction: column; gap: 12px; }
.dcard-empty { color: #94a3b8; font-size: 11px; }
.dcard-field { display: flex; flex-direction: column; gap: 3px; }
.dcard-field > label { font-size: 11px; font-weight: 700; color: #334155; display: flex; align-items: center; gap: 6px; }
.dcard-field .req { color: #dc2626; }
.dcard-field .opt { font-size: 9px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: .04em; }
.dcard-field .field-help { font-size: 10px; color: #94a3b8; margin-bottom: 2px; }
.dcard-field .hint { font-size: 9px; font-weight: 700; color: #94a3b8; background: #f1f5f9; border-radius: 4px; padding: 1px 5px; letter-spacing: .04em; }
.dcard-field input, .dcard-field select, .dcard-field textarea { width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 8px; padding: 7px 9px; font-size: 12px; outline: none; }
.dcard-field input:focus, .dcard-field select:focus, .dcard-field textarea:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.1); }
.dcard-textrow { display: flex; gap: 6px; align-items: center; }
.dcard-textrow input { flex: 1 1 auto; min-width: 0; }
.dcard-token { flex: 0 0 84px; width: 84px; font-size: 11px; color: #6366f1; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
textarea.bad { border-color: #dc2626; }
.json-err { font-size: 10px; color: #dc2626; }
.dcard-tip { margin: 2px 0 0; font-size: 10px; color: #94a3b8; line-height: 1.5; }
.dcard-tip b { color: #6366f1; }
</style>
