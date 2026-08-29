<template>
  <div class="pv">
    <div class="pv-head">
      <h4>Live preview</h4>
      <span class="pv-note">Runs the current (unsaved) config through the engine.</span>
    </div>

    <div class="pv-grid">
      <div class="pv-field">
        <div class="pv-plabel">
          <span>Payload (JSON)</span>
          <button v-if="expectedFields.length" type="button" class="pv-fill" @click="fillFromFields"
                  title="Reset the payload to the fields declared in Validation">↻ from fields</button>
        </div>
        <textarea rows="4" class="mono" v-model="payloadText" :class="{ bad: payloadErr }" spellcheck="false"></textarea>
        <span v-if="expectedFields.length" class="pv-fieldnote">Auto-filled from Validation: {{ expectedFields.join(', ') }}</span>
      </div>
      <div class="pv-side">
        <label class="pv-field"><span>Platform</span>
          <select v-model="platform"><option value="">(any)</option><option>web</option><option>android</option><option>ios</option></select>
        </label>
        <label class="pv-field"><span>App version</span>
          <input type="text" v-model="appVersion" placeholder="2.3.0" /></label>
        <button type="button" class="pv-run" :disabled="running" @click="run">{{ running ? 'Running…' : '▶ Run' }}</button>
      </div>
    </div>

    <div v-if="error" class="pv-err">{{ error }}</div>

    <div v-if="result" class="pv-result">
      <div class="pv-status" :class="result.success ? 'ok' : 'fail'">
        {{ result.success ? 'success' : 'failed' }}<span v-if="result.message"> — {{ result.message }}</span>
      </div>
      <div class="pv-directives">
        <div v-for="(d, i) in result.directives" :key="i" class="pv-dir" :style="{ borderLeftColor: colorFor(catOf(d.type)).accent }">
          <code>{{ d.type }}</code>
          <span class="pv-dir-json">{{ shortJson(d) }}</span>
        </div>
        <div v-if="!result.directives || !result.directives.length" class="pv-empty">No directives returned.</div>
      </div>
      <details class="pv-raw"><summary>Raw response</summary><pre>{{ pretty(result) }}</pre></details>
    </div>

    <div class="pv-curl">
      <button type="button" class="pv-curlbtn" @click="toggleCurl">
        {{ showCurl ? 'Hide cURL' : 'Get cURL for this workflow' }}
      </button>
      <div v-if="showCurl" class="pv-curlbox">
        <div class="pv-curltop">
          <span v-if="!alias" class="pv-curlwarn">Set an alias (and save the workflow) for a runnable request.</span>
          <span v-else></span>
          <button type="button" class="pv-copy" @click="copyCurl">{{ copied ? 'Copied ✓' : 'Copy' }}</button>
        </div>
        <pre>{{ curlCommand }}</pre>
      </div>
    </div>
  </div>
</template>

<script>
import { colorFor } from '../lib/categories';

export default {
  name: 'PreviewPanel',
  props: {
    previewUrl: { type: String, required: true },
    executeUrl: { type: String, default: '' },
    alias: { type: String, default: '' },
    authRequired: { type: Boolean, default: false },
    csrf: { type: String, default: '' },
    config: { type: Object, default: () => ({}) },
    manifest: { type: Array, default: () => [] },
  },
  data() {
    return { payloadText: '{}', platform: '', appVersion: '', running: false, error: '', result: null, payloadErr: false, showCurl: false, copied: false };
  },
  computed: {
    validationRules() {
      const cfg = this.config || {};
      return (cfg.validation && cfg.validation.rules) || cfg.rules || {};
    },
    // Fields the workflow expects: validation rule names + any {{ payload.x }}
    // referenced anywhere in the config.
    expectedFields() {
      const set = new Set(Object.keys(this.validationRules));
      const json = JSON.stringify(this.config || {});
      const re = /\{\{\s*payload\.([a-zA-Z0-9_]+)/g;
      let m;
      while ((m = re.exec(json)) !== null) set.add(m[1]);
      return Array.from(set);
    },
    curlCommand() {
      let payload = {};
      try { payload = this.payloadText.trim() ? JSON.parse(this.payloadText) : {}; } catch (e) { payload = {}; }

      const body = { workflow: this.alias || 'WORKFLOW_ALIAS', payload };
      if (this.platform) body.client = { platform: this.platform, app_version: this.appVersion || null };

      const json = JSON.stringify(body, null, 2).replace(/'/g, "'\\''"); // shell-safe single quotes
      const lines = [
        `curl -X POST '${this.executeUrl}' \\`,
        `  -H 'Content-Type: application/json' \\`,
        `  -H 'Accept: application/json' \\`,
      ];
      if (this.authRequired) lines.push(`  -H 'Authorization: Bearer <token>' \\`);
      lines.push(`  -d '${json}'`);
      return lines.join('\n');
    },
  },
  watch: {
    // When new fields appear (rule added, or a new payload token used), fold
    // them into the payload — without touching values the user already entered.
    expectedFields: { handler() { this.mergeFields(); }, immediate: true },
  },
  methods: {
    colorFor,
    sampleFor(field) {
      const rule = String(this.validationRules[field] || '');
      if (/\b(integer|numeric)\b/.test(rule)) return 1;
      if (/\bboolean\b/.test(rule)) return true;
      if (/\bemail\b/.test(rule)) return 'user@example.com';
      return '';
    },
    mergeFields() {
      let obj = {};
      try {
        obj = this.payloadText.trim() ? JSON.parse(this.payloadText) : {};
      } catch (e) {
        return; // don't clobber JSON the user is mid-editing
      }
      let changed = false;
      this.expectedFields.forEach((f) => {
        if (!(f in obj)) { obj[f] = this.sampleFor(f); changed = true; }
      });
      if (changed) { this.payloadText = JSON.stringify(obj, null, 2); this.payloadErr = false; }
    },
    fillFromFields() {
      const obj = {};
      this.expectedFields.forEach((f) => { obj[f] = this.sampleFor(f); });
      this.payloadText = JSON.stringify(obj, null, 2);
      this.payloadErr = false;
    },
    catOf(type) { const d = this.manifest.find((m) => m.type === type); return d ? d.category : null; },
    pretty(o) { return JSON.stringify(o, null, 2); },
    shortJson(d) { const c = Object.assign({}, d); delete c.type; const s = JSON.stringify(c); return s.length > 80 ? s.slice(0, 80) + '…' : s; },
    toggleCurl() {
      this.showCurl = !this.showCurl;
      this.copied = false;
    },
    async copyCurl() {
      try {
        await navigator.clipboard.writeText(this.curlCommand);
        this.copied = true;
        setTimeout(() => { this.copied = false; }, 1800);
      } catch (e) {
        this.copied = false;
      }
    },
    async run() {
      let payload = {};
      try { payload = this.payloadText.trim() ? JSON.parse(this.payloadText) : {}; this.payloadErr = false; }
      catch (e) { this.payloadErr = true; this.error = 'Payload is not valid JSON.'; return; }

      this.running = true; this.error = ''; this.result = null;
      try {
        const res = await fetch(this.previewUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
          body: JSON.stringify({ config: this.config, payload, platform: this.platform || null, app_version: this.appVersion || null }),
        });
        const data = await res.json().catch(() => ({}));
        if (res.ok) { this.result = data; } else { this.error = data.message || ('Preview failed (' + res.status + ').'); }
      } catch (e) {
        this.error = 'Network error: ' + e.message;
      } finally { this.running = false; }
    },
  },
};
</script>

<style>
.pv { border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px; background: #0f172a; color: #e2e8f0; display: flex; flex-direction: column; gap: 12px; }
.pv-head { display: flex; align-items: baseline; justify-content: space-between; }
.pv-head h4 { margin: 0; font-size: 13px; font-weight: 800; color: #fff; }
.pv-note { font-size: 11px; color: #94a3b8; }
.pv-grid { display: grid; grid-template-columns: 1fr 160px; gap: 12px; }
.pv-side { display: flex; flex-direction: column; gap: 8px; }
.pv-field { display: flex; flex-direction: column; gap: 4px; }
.pv-field > span { font-size: 11px; font-weight: 600; color: #94a3b8; }
.pv-plabel { display: flex; align-items: center; justify-content: space-between; }
.pv-fill { border: 1px solid #334155; background: #1e293b; color: #a5b4fc; border-radius: 6px; padding: 2px 8px; font-size: 10px; font-weight: 700; cursor: pointer; }
.pv-fill:hover { background: #334155; }
.pv-fieldnote { font-size: 10px; color: #64748b; margin-top: 2px; }
.pv-field textarea, .pv-field input, .pv-field select { border: 1px solid #334155; background: #1e293b; color: #e2e8f0; border-radius: 8px; padding: 7px 9px; font-size: 12px; outline: none; }
.pv-field textarea.bad { border-color: #ef4444; }
.pv-run { margin-top: auto; border: 0; background: #6366f1; color: #fff; border-radius: 8px; padding: 9px; font-size: 12px; font-weight: 800; cursor: pointer; }
.pv-run:disabled { opacity: .6; }
.pv-err { color: #fca5a5; font-size: 12px; }
.pv-result { display: flex; flex-direction: column; gap: 8px; }
.pv-status { font-size: 12px; font-weight: 700; }
.pv-status.ok { color: #4ade80; }
.pv-status.fail { color: #f87171; }
.pv-directives { display: flex; flex-direction: column; gap: 6px; }
.pv-dir { border-left: 3px solid #64748b; background: #1e293b; border-radius: 6px; padding: 7px 10px; display: flex; gap: 10px; align-items: baseline; }
.pv-dir code { color: #a5b4fc; font-size: 12px; font-weight: 700; }
.pv-dir-json { color: #94a3b8; font-size: 11px; font-family: ui-monospace, monospace; }
.pv-empty { color: #64748b; font-size: 11px; }
.pv-raw summary { cursor: pointer; font-size: 11px; color: #94a3b8; }
.pv-raw pre { background: #1e293b; padding: 10px; border-radius: 8px; font-size: 11px; overflow-x: auto; max-height: 220px; }
.pv-curl { border-top: 1px solid #334155; padding-top: 12px; }
.pv-curlbtn { border: 1px solid #6366f1; background: transparent; color: #a5b4fc; border-radius: 8px; padding: 8px 14px; font-size: 12px; font-weight: 700; cursor: pointer; }
.pv-curlbtn:hover { background: #1e293b; }
.pv-curlbox { margin-top: 10px; }
.pv-curltop { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; gap: 10px; }
.pv-curlwarn { font-size: 10px; color: #fbbf24; }
.pv-copy { border: 1px solid #334155; background: #1e293b; color: #e2e8f0; border-radius: 6px; padding: 3px 10px; font-size: 11px; font-weight: 700; cursor: pointer; }
.pv-copy:hover { background: #334155; }
.pv-curlbox pre { background: #1e293b; color: #e2e8f0; padding: 12px; border-radius: 8px; font-size: 11px; overflow-x: auto; white-space: pre; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
</style>
