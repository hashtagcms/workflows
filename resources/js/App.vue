<template>
  <div class="wfb">
    <div class="wfb-head">
      <div>
        <h3 class="wfb-kicker">Interactive Workflow Manager</h3>
        <p class="wfb-sub">Compose validation, a target, and client directives visually — no hand-written JSON required.</p>
      </div>
      <div class="wfb-modes">
        <button type="button" :class="['wfb-tab', { on: mode === 'visual' }]" @click="switchMode('visual')">Visual</button>
        <button type="button" :class="['wfb-tab', { on: mode === 'json' }]" @click="switchMode('json')">JSON</button>
      </div>
    </div>

    <div class="wfb-body">
      <!-- Identity -->
      <div class="wfb-grid">
        <label class="wfb-field"><span>Workflow name</span>
          <input type="text" v-model="form.name" placeholder="e.g. Apply Coupon Flow" /></label>
        <label class="wfb-field"><span>Alias</span>
          <input type="text" v-model="form.alias" class="mono" placeholder="WORKFLOW_APPLY_COUPON" /></label>
      </div>
      <label class="wfb-field"><span>Description</span>
        <textarea v-model="form.description" rows="2" placeholder="What this workflow does..."></textarea></label>

      <label class="wfb-field"><span>Custom PHP handler <em>(advanced, optional)</em></span>
        <input type="text" v-model="form.handler" class="mono"
               placeholder="App\Workflows\ApplyCoupon" />
        <small class="wfb-help">Leave blank to use the visual config below. Set it to a class implementing <code>WorkflowHandlerInterface</code> to run code instead.</small>
      </label>

      <div class="wfb-toggles">
        <label class="wfb-check"><input type="checkbox" v-model="publishBool" /> Published</label>
        <label class="wfb-check"><input type="checkbox" v-model="authBool" /> Requires login (Sanctum)</label>
      </div>

      <!-- Identity provider (SSO) — which provider verifies the caller's token -->
      <div v-if="ssoModuleActive" class="wfb-field">
        <span>Identity provider <em>(SSO)</em></span>
        <select v-model="form.sso_provider_alias" class="mono">
          <option :value="ssoNoneValue">None — ignore SSO (local login only)</option>
          <option v-for="p in ssoProviders" :key="p.alias" :value="p.alias">
            {{ p.name }} ({{ p.alias }} · {{ p.driver }}){{ p.is_master ? ' — master site' : '' }}
          </option>
        </select>
        <small class="wfb-help" :class="{ 'wfb-help-warn': identityWarn }">{{ identityIndicator }}</small>
      </div>

      <!-- Visual builder -->
      <div v-show="mode === 'visual'" :key="visualKey" class="wfb-sections">
        <section class="wfb-sec">
          <div class="wfb-sec-h"><span class="dot" style="background:#f59e0b"></span> Validation</div>
          <ValidationBuilder :validation="validationForBuilder" @update:validation="setValidation" />
        </section>

        <section class="wfb-sec">
          <div class="wfb-sec-h"><span class="dot" style="background:#3b82f6"></span> Target</div>
          <TargetBuilder :target="config.target || null" @update:target="setTarget" />
        </section>

        <div class="wfb-branches">
          <BranchBuilder title="On success" accent="#16a34a" :manifest="manifest" :has-target="hasTarget"
                         :branch="branchOf('on_success')" @update:branch="setBranch('on_success', $event)" />
          <BranchBuilder title="On failure" accent="#dc2626" :manifest="manifest" :has-target="hasTarget"
                         :branch="branchOf('on_failure')" @update:branch="setBranch('on_failure', $event)" />
        </div>
      </div>

      <!-- JSON escape hatch -->
      <div v-show="mode === 'json'" class="wfb-sec">
        <label class="wfb-field"><span>Workflow config (JSON)</span>
          <textarea v-model="jsonText" rows="18" class="mono" spellcheck="false"></textarea></label>
        <p v-if="jsonError" class="wfb-err">{{ jsonError }}</p>
      </div>

      <!-- Live preview -->
      <PreviewPanel :preview-url="previewUrl" :execute-url="executeUrl" :alias="form.alias"
                    :auth-required="!!form.auth_required" :csrf="csrf" :config="config" :manifest="manifest" />
    </div>

    <div class="wfb-foot">
      <a :href="backUrl" class="wfb-btn ghost">Cancel</a>
      <div class="wfb-foot-right">
        <span v-if="message" :class="['wfb-msg', message.type]">{{ message.text }}</span>
        <button type="button" class="wfb-btn primary" :disabled="saving" @click="save">
          {{ saving ? 'Saving…' : 'Save workflow' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import ValidationBuilder from './components/ValidationBuilder.vue';
import TargetBuilder from './components/TargetBuilder.vue';
import BranchBuilder from './components/BranchBuilder.vue';
import PreviewPanel from './components/PreviewPanel.vue';
import { branchIsEmpty } from './lib/branch';

export default {
  name: 'WorkflowBuilder',
  components: { ValidationBuilder, TargetBuilder, BranchBuilder, PreviewPanel },
  props: { initial: { type: Object, default: () => ({}) } },
  data() {
    const i = this.initial || {};
    let viewType = 'visual';
    try {
      if (new URLSearchParams(window.location.search).get('viewType') === 'json') viewType = 'json';
    } catch (e) { /* default to visual */ }
    return {
      storeUrl: i.storeUrl || '',
      previewUrl: i.previewUrl || '',
      executeUrl: i.executeUrl || '',
      directivesUrl: i.directivesUrl || '',
      backUrl: i.backUrl || '',
      csrf: i.csrf || '',
      siteId: i.site_id || null,
      ssoModuleActive: !!i.sso_module_active,
      ssoProviders: Array.isArray(i.sso_providers) ? i.sso_providers : [],
      ssoDefaultAlias: i.sso_default_alias || '',
      ssoNoneValue: i.sso_none_value || '@none',
      form: {
        id: i.id || 0,
        name: i.name || '',
        alias: i.alias || '',
        description: i.description || '',
        handler: i.handler || '',
        auth_required: i.auth_required ? 1 : 0,
        // No "Auto" option: an unpinned workflow defaults to the site's default
        // provider (concrete), so which provider runs is always explicit. An
        // explicit `@none` or a specific alias is preserved as-is.
        sso_provider_alias: i.sso_provider_alias || i.sso_default_alias || '',
        publish_status: i.publish_status === 0 ? 0 : 1,
      },
      config: this.cloneConfig(i.config),
      manifest: [],
      // Safety net: ?viewType=json forces the raw JSON editor on load (handy if
      // the visual builder ever chokes on an unusual config).
      mode: viewType,
      visualKey: 0,
      jsonText: '',
      jsonError: '',
      saving: false,
      message: null,
    };
  },
  computed: {
    publishBool: {
      get() { return !!this.form.publish_status; },
      set(v) { this.form.publish_status = v ? 1 : 0; },
    },
    authBool: {
      get() { return !!this.form.auth_required; },
      set(v) { this.form.auth_required = v ? 1 : 0; },
    },
    ssoIsNone() {
      return this.form.sso_provider_alias === this.ssoNoneValue;
    },
    effectiveProvider() {
      if (this.ssoIsNone) return '';
      return this.form.sso_provider_alias || this.ssoDefaultAlias || '';
    },
    identityIndicator() {
      if (!this.ssoModuleActive) return '';
      if (this.ssoIsNone) {
        return this.form.auth_required
          ? 'SSO is ignored for this workflow — identity comes from local login only, so external (token) callers get a 401.'
          : 'SSO is ignored for this workflow — identity comes from local login only (no provider is used).';
      }
      if (this.form.sso_provider_alias) {
        let s = 'Identity is resolved by provider “' + this.form.sso_provider_alias + '”.';
        if (this.form.sso_provider_alias === this.ssoDefaultAlias) s += ' (this site’s default)';
        return s;
      }
      // No provider applies to this site (none selectable).
      return this.form.auth_required
        ? 'No SSO provider applies to this site — local login only, so external (token) callers get a 401.'
        : 'No SSO provider applies to this site — identity falls back to local login.';
    },
    identityWarn() {
      if (!this.ssoModuleActive) return false;
      if (this.ssoIsNone) return !!this.form.auth_required;
      if (!this.effectiveProvider && this.form.auth_required) return true;
      return false;
    },
    hasTarget() {
      const t = this.config && this.config.target;
      return !!(t && t.type && t.type !== 'none');
    },
    // Rules can live at config.validation.rules OR top-level config.rules — the
    // builder presents whichever exists and normalises to validation.rules on save.
    validationForBuilder() {
      if (this.config.validation && typeof this.config.validation === 'object') {
        return this.config.validation;
      }
      if (this.config.rules && typeof this.config.rules === 'object') {
        return { rules: this.config.rules };
      }
      return {};
    },
  },
  created() {
    this.syncToJson();
    this.loadManifest();
  },
  methods: {
    cloneConfig(cfg) {
      if (cfg && typeof cfg === 'object') return JSON.parse(JSON.stringify(cfg));
      return { version: '1.0' };
    },
    async loadManifest() {
      if (!this.directivesUrl) return;
      try {
        const res = await fetch(this.directivesUrl, { headers: { Accept: 'application/json' } });
        const data = await res.json();
        this.manifest = Array.isArray(data.directives) ? data.directives : [];
      } catch (e) { /* palette will show an empty-state hint */ }
    },
    branchOf(name) {
      const b = this.config[name];
      return b && typeof b === 'object' ? b : { message: '', directives: [] };
    },
    setValidation(v) {
      const cfg = Object.assign({}, this.config);
      // Keep validation if it has anything (rules, messages, on_error, …).
      if (v && Object.keys(v).length) cfg.validation = v; else delete cfg.validation;
      // Normalise: rules now live under validation, so drop any top-level `rules`.
      delete cfg.rules;
      this.config = cfg;
    },
    setTarget(t) {
      const cfg = Object.assign({}, this.config);
      if (t) cfg.target = t; else delete cfg.target;
      this.config = cfg;
    },
    setBranch(name, b) {
      const cfg = Object.assign({}, this.config);
      if (branchIsEmpty(b)) delete cfg[name]; else cfg[name] = b;
      this.config = cfg;
    },
    syncToJson() { this.jsonText = JSON.stringify(this.config, null, 2); this.jsonError = ''; },
    applyJson() {
      try {
        const parsed = JSON.parse(this.jsonText || '{}');
        if (parsed === null || typeof parsed !== 'object' || Array.isArray(parsed)) { this.jsonError = 'Config must be a JSON object.'; return false; }
        this.config = parsed; this.jsonError = ''; return true;
      } catch (e) { this.jsonError = 'Invalid JSON: ' + e.message; return false; }
    },
    switchMode(target) {
      if (target === this.mode) return;
      if (this.mode === 'json') { if (!this.applyJson()) return; }
      if (target === 'json') this.syncToJson();
      if (target === 'visual') this.visualKey++; // remount sections from fresh config
      this.mode = target;
    },
    async save() {
      if (this.mode === 'json' && !this.applyJson()) return;
      if (!this.form.name || !this.form.alias) { this.message = { type: 'err', text: 'Name and alias are required.' }; return; }

      this.saving = true; this.message = null;
      const body = new URLSearchParams();
      body.set('id', this.form.id || '');
      body.set('actionPerformed', this.form.id ? 'edit' : 'add');
      body.set('name', this.form.name);
      body.set('alias', this.form.alias);
      body.set('description', this.form.description || '');
      body.set('handler', this.form.handler || '');
      body.set('auth_required', this.form.auth_required ? '1' : '0');
      body.set('sso_provider_alias', this.form.sso_provider_alias || '');
      body.set('publish_status', this.form.publish_status ? '1' : '0');
      body.set('config', JSON.stringify(this.config));
      if (this.siteId) body.set('site_id', this.siteId);

      try {
        const res = await fetch(this.storeUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-CSRF-TOKEN': this.csrf, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
          body: body.toString(),
        });
        const data = await res.json().catch(() => ({}));
        if (res.ok && data.success) { if (data.id) this.form.id = data.id; this.message = { type: 'ok', text: data.message || 'Saved.' }; }
        else if (res.status === 422) { const first = data.errors ? Object.values(data.errors)[0] : null; this.message = { type: 'err', text: (first && first[0]) || data.message || 'Validation failed.' }; }
        else { this.message = { type: 'err', text: data.message || ('Save failed (' + res.status + ').') }; }
      } catch (e) { this.message = { type: 'err', text: 'Network error: ' + e.message }; }
      finally { this.saving = false; }
    },
  },
};
</script>

<style>
.wfb { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; max-width: 1040px; margin: 0 auto; color: #0f172a; font-size: 13px; }
.wfb-head { display: flex; align-items: center; justify-content: space-between; padding: 20px 28px; border-bottom: 1px solid #f1f5f9; background: linear-gradient(90deg, #eef2ff, #faf5ff); }
.wfb-kicker { margin: 0; font-size: 11px; font-weight: 800; letter-spacing: .18em; text-transform: uppercase; color: #6366f1; }
.wfb-sub { margin: 4px 0 0; font-size: 12px; color: #64748b; }
.wfb-modes { display: inline-flex; background: #fff; border: 1px solid #e0e7ff; border-radius: 10px; padding: 3px; }
.wfb-tab { border: 0; background: transparent; padding: 6px 16px; font-size: 12px; font-weight: 700; color: #6366f1; border-radius: 8px; cursor: pointer; }
.wfb-tab.on { background: #6366f1; color: #fff; }
.wfb-body { padding: 24px 28px; display: flex; flex-direction: column; gap: 18px; }
.wfb-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.wfb-field { display: flex; flex-direction: column; gap: 6px; }
.wfb-field > span { font-size: 12px; font-weight: 600; color: #334155; }
.wfb-field input, .wfb-field textarea, .wfb-field select { width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 10px; padding: 10px 12px; font-size: 12px; color: #0f172a; background: #fff; outline: none; }
.wfb-field input:focus, .wfb-field textarea:focus, .wfb-field select:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.12); }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
.wfb-sections { display: flex; flex-direction: column; gap: 18px; }
.wfb-sec { display: flex; flex-direction: column; gap: 12px; }
.wfb-sec-h { display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: .06em; }
.dot { width: 9px; height: 9px; border-radius: 50%; display: inline-block; }
.wfb-branches { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.wfb-toggles { display: flex; gap: 16px; }
.wfb-check { font-size: 12px; color: #334155; display: flex; align-items: center; gap: 6px; }
.wfb-help { font-size: 10px; color: #94a3b8; line-height: 1.5; }
.wfb-help code { background: #f1f5f9; border-radius: 4px; padding: 1px 4px; }
.wfb-help-warn { color: #b45309; font-weight: 600; }
.wfb-field > span em { color: #94a3b8; font-style: normal; font-weight: 400; }
.wfb-err { color: #dc2626; font-size: 12px; margin: 0; }
.wfb-foot { display: flex; align-items: center; justify-content: space-between; padding: 18px 28px; border-top: 1px solid #f1f5f9; background: #f8fafc; }
.wfb-foot-right { display: flex; align-items: center; gap: 14px; }
.wfb-msg { font-size: 12px; font-weight: 600; }
.wfb-msg.ok { color: #16a34a; }
.wfb-msg.err { color: #dc2626; }
.wfb-btn { border: 0; border-radius: 10px; padding: 11px 22px; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; cursor: pointer; text-decoration: none; }
.wfb-btn.ghost { background: #fff; border: 1px solid #e2e8f0; color: #475569; }
.wfb-btn.primary { background: #6366f1; color: #fff; }
.wfb-btn.primary:disabled { opacity: .6; cursor: default; }
@media (max-width: 720px) { .wfb-grid, .wfb-branches { grid-template-columns: 1fr; } }
</style>
