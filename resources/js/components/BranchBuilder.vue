<template>
  <div class="branch">
    <div class="branch-head">
      <h4 :style="{ color: accent }">{{ title }}</h4>
      <span class="branch-count">{{ directives.length }} directive(s)</span>
    </div>

    <label class="branch-msg">
      <span>Message (optional)</span>
      <input type="text" :value="branch.message || ''" @input="setMessage($event.target.value)"
             placeholder="e.g. {{ response.body.message }}" />
    </label>

    <label class="branch-msg">
      <span>Response data (JSON, optional)</span>
      <textarea rows="2" class="mono" v-model="dataBuffer" @input="onData" :class="{ bad: dataErr }"
                :placeholder="dataPlaceholder" spellcheck="false"></textarea>
      <small class="branch-datahint">Returned in the response <code>data</code> field — interpolation tokens work here (e.g. the target response).</small>
      <span v-if="dataErr" class="branch-dataerr">Invalid JSON</span>
    </label>

    <draggable v-if="directives.length" :list="directives" item-key="_k" handle=".dcard-grip"
               class="branch-list" @change="onReorder">
      <template #item="{ element, index }">
        <DirectiveCard :directive="element" :def="defFor(element.type)" :has-target="hasTarget"
                       @update:directive="updateDirective(index, $event)"
                       @remove="removeDirective(index)" />
      </template>
    </draggable>
    <p v-if="directives.length > 1" class="branch-reorder">Drag the ⠿ handle to reorder.</p>

    <!-- Add a directive: a single compact, grouped dropdown. -->
    <select class="branch-add" :style="{ color: accent, borderColor: accent }"
            @change="onPick($event.target.value); $event.target.value = ''">
      <option value="">+ Add directive…</option>
      <optgroup v-for="g in groups" :key="g.category" :label="g.category">
        <option v-for="d in g.items" :key="d.type" :value="d.type" :title="d.description || ''">{{ d.label }}</option>
      </optgroup>
    </select>
  </div>
</template>

<script>
import draggable from 'vuedraggable';
import DirectiveCard from './DirectiveCard.vue';
import { mergeBranch } from '../lib/branch';

export default {
  name: 'BranchBuilder',
  components: { draggable, DirectiveCard },
  props: {
    title: { type: String, required: true },
    accent: { type: String, default: '#6366f1' },
    branch: { type: Object, default: () => ({ message: '', directives: [] }) },
    manifest: { type: Array, default: () => [] },
    hasTarget: { type: Boolean, default: false },
  },
  emits: ['update:branch'],
  data() {
    return {
      dataBuffer: this.branch && this.branch.data ? JSON.stringify(this.branch.data, null, 2) : '',
      dataErr: false,
      dataPlaceholder: '{ "items": "{{ response.body }}" }',
    };
  },
  computed: {
    directives() {
      return Array.isArray(this.branch.directives) ? this.branch.directives : [];
    },
    groups() {
      const byCat = {};
      this.manifest.forEach((d) => {
        const cat = d.category || 'other';
        (byCat[cat] = byCat[cat] || []).push(d);
      });
      return Object.keys(byCat).sort().map((category) => ({
        category,
        items: byCat[category].sort((a, b) => a.label.localeCompare(b.label)),
      }));
    },
  },
  methods: {
    defFor(type) {
      return this.manifest.find((d) => d.type === type) || null;
    },
    // Merge a patch into the branch so keys the visual builder doesn't model
    // (e.g. `data`, or anything custom) are preserved on every edit.
    emitPatch(patch) {
      this.$emit('update:branch', mergeBranch(this.branch, patch));
    },
    setMessage(msg) {
      this.emitPatch({ message: msg });
    },
    onPick(type) {
      if (!type) return;
      this.emitPatch({ directives: this.directives.concat([{ type }]) });
    },
    updateDirective(index, next) {
      const list = this.directives.slice();
      list[index] = next;
      this.emitPatch({ directives: list });
    },
    removeDirective(index) {
      const list = this.directives.slice();
      list.splice(index, 1);
      this.emitPatch({ directives: list });
    },
    onReorder() {
      this.emitPatch({ directives: this.directives.slice() });
    },
    onData() {
      const text = (this.dataBuffer || '').trim();
      if (text === '') {
        this.dataErr = false;
        const next = Object.assign({}, this.branch);
        delete next.data;
        this.$emit('update:branch', next);
        return;
      }
      try {
        const parsed = JSON.parse(text);
        this.dataErr = false;
        this.emitPatch({ data: parsed });
      } catch (e) {
        this.dataErr = true; // keep buffer, don't emit invalid JSON
      }
    },
  },
};
</script>

<style>
.branch { border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px; background: #fafbff; display: flex; flex-direction: column; gap: 12px; }
.branch-head { display: flex; align-items: baseline; justify-content: space-between; }
.branch-head h4 { margin: 0; font-size: 13px; font-weight: 800; }
.branch-count { font-size: 11px; color: #94a3b8; }
.branch-msg { display: flex; flex-direction: column; gap: 4px; }
.branch-msg > span { font-size: 11px; font-weight: 600; color: #475569; }
.branch-msg input { border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 10px; font-size: 12px; outline: none; }
.branch-msg input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.1); }
.branch-msg textarea { border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 10px; font-size: 12px; outline: none; width: 100%; box-sizing: border-box; }
.branch-msg textarea:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.1); }
.branch-msg textarea.bad { border-color: #dc2626; }
.branch-msg .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
.branch-datahint { font-size: 10px; color: #94a3b8; }
.branch-datahint code { background: #f1f5f9; border-radius: 4px; padding: 0 4px; }
.branch-dataerr { font-size: 10px; color: #dc2626; }
.branch-list { display: flex; flex-direction: column; gap: 10px; }
.branch-reorder { font-size: 10px; color: #cbd5e1; margin: 0; }
.branch-add { width: 100%; border: 1.5px dashed; background: #fff; border-radius: 10px; padding: 10px; font-size: 12px; font-weight: 800; cursor: pointer; outline: none; }
.branch-add:hover { background: #f8fafc; }
.branch-add:focus { box-shadow: 0 0 0 3px rgba(99,102,241,.12); }
</style>
