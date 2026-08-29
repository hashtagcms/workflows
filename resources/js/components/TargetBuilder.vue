<template>
  <div class="tb">
    <label class="tb-field">
      <span>Target type</span>
      <select v-model="type" @change="emitTarget">
        <option value="none">None — return directives directly</option>
        <option value="http">HTTP request</option>
        <option value="service">Service call (PHP)</option>
        <option value="event">Event dispatch</option>
      </select>
    </label>

    <!-- HTTP -->
    <div v-if="type === 'http'" class="tb-block">
      <div class="tb-grid">
        <label class="tb-field">
          <span>Method</span>
          <select v-model="http.method" @change="emitTarget">
            <option>GET</option><option>POST</option><option>PUT</option><option>PATCH</option><option>DELETE</option>
          </select>
        </label>
        <label class="tb-field">
          <span>Timeout (s)</span>
          <input type="number" v-model.number="http.timeout" @input="emitTarget" />
        </label>
      </div>
      <label class="tb-field">
        <span>URL</span>
        <input type="text" class="mono" v-model="http.url" @input="emitTarget" placeholder="https://api.example.com/v1/..." />
      </label>
      <div class="tb-grid">
        <label class="tb-field">
          <span>Auth</span>
          <select v-model="auth.type" @change="emitTarget">
            <option value="none">None</option>
            <option value="bearer">Bearer token</option>
          </select>
        </label>
        <label class="tb-field" v-if="auth.type === 'bearer'">
          <span>Token</span>
          <input type="text" class="mono" v-model="auth.token" @input="emitTarget" placeholder="{{ env.API_TOKEN }}" />
        </label>
      </div>

      <KvEditor label="Headers" :rows="headerRows" @change="emitTarget" />
      <KvEditor label="Query params" :rows="queryRows" @change="emitTarget" />

      <label class="tb-field">
        <span>Body (JSON)</span>
        <textarea rows="3" class="mono" v-model="bodyText" @input="emitTarget" :class="{ bad: bodyErr }"
                  placeholder='{ "sku": "{{ payload.id }}" }' spellcheck="false"></textarea>
      </label>
    </div>

    <!-- Service -->
    <div v-else-if="type === 'service'" class="tb-block">
      <label class="tb-field"><span>Class</span>
        <input type="text" class="mono" v-model="service.class" @input="emitTarget" placeholder="App\\Services\\InventoryService" /></label>
      <label class="tb-field"><span>Method</span>
        <input type="text" class="mono" v-model="service.method" @input="emitTarget" placeholder="check" /></label>
      <label class="tb-field"><span>Arguments (JSON)</span>
        <textarea rows="3" class="mono" v-model="argsText" @input="emitTarget" :class="{ bad: argsErr }" spellcheck="false"></textarea></label>
    </div>

    <!-- Event -->
    <div v-else-if="type === 'event'" class="tb-block">
      <label class="tb-field"><span>Event class</span>
        <input type="text" class="mono" v-model="event.class" @input="emitTarget" placeholder="App\\Events\\OrderPlaced" /></label>
      <label class="tb-field"><span>Payload (JSON)</span>
        <textarea rows="3" class="mono" v-model="payloadText" @input="emitTarget" :class="{ bad: payloadErr }" spellcheck="false"></textarea></label>
    </div>

    <p v-else class="tb-hint">No external call — the workflow returns its directives directly.</p>
  </div>
</template>

<script>
import KvEditor from './KvEditor.vue';

function objToText(o) { return o && Object.keys(o).length ? JSON.stringify(o, null, 2) : ''; }
function toRows(o) { return Object.entries(o || {}).map(([k, v]) => ({ k, v: typeof v === 'string' ? v : JSON.stringify(v) })); }
function rowsToObj(rows) { const o = {}; rows.forEach((r) => { if ((r.k || '').trim()) o[r.k.trim()] = r.v; }); return o; }

export default {
  name: 'TargetBuilder',
  components: { KvEditor },
  props: { target: { type: Object, default: null } },
  emits: ['update:target'],
  data() {
    const t = this.target || {};
    const http = t.type === 'http' || t.type === 'http_request' ? t : {};
    return {
      type: t.type === 'http_request' ? 'http' : (t.type || 'none'),
      http: { method: http.method || 'GET', url: http.url || '', timeout: http.timeout || 10 },
      auth: { type: (http.auth && http.auth.type) || 'none', token: (http.auth && http.auth.token) || '' },
      headerRows: toRows(http.headers),
      queryRows: toRows(http.query),
      bodyText: objToText(http.body), bodyErr: false,
      service: { class: t.class || '', method: t.method || '' },
      argsText: objToText(t.arguments), argsErr: false,
      event: { class: t.class || '' },
      payloadText: objToText(t.payload), payloadErr: false,
    };
  },
  methods: {
    parseJson(text, flag) {
      if (!text || !text.trim()) { this[flag] = false; return undefined; }
      try { const v = JSON.parse(text); this[flag] = false; return v; }
      catch (e) { this[flag] = true; return undefined; }
    },
    emitTarget() {
      if (this.type === 'none') { this.$emit('update:target', null); return; }

      let target;
      if (this.type === 'http') {
        target = { type: 'http', method: this.http.method, url: this.http.url, timeout: Number(this.http.timeout) || 10 };
        const headers = rowsToObj(this.headerRows); if (Object.keys(headers).length) target.headers = headers;
        const query = rowsToObj(this.queryRows); if (Object.keys(query).length) target.query = query;
        const body = this.parseJson(this.bodyText, 'bodyErr'); if (body !== undefined) target.body = body;
        if (this.auth.type === 'bearer') target.auth = { type: 'bearer', token: this.auth.token };
      } else if (this.type === 'service') {
        target = { type: 'service', class: this.service.class, method: this.service.method };
        const args = this.parseJson(this.argsText, 'argsErr'); if (args !== undefined) target.arguments = args;
      } else if (this.type === 'event') {
        target = { type: 'event', class: this.event.class };
        const payload = this.parseJson(this.payloadText, 'payloadErr'); if (payload !== undefined) target.payload = payload;
      }
      this.$emit('update:target', target);
    },
  },
};
</script>

<style>
.tb { display: flex; flex-direction: column; gap: 12px; }
.tb-block { display: flex; flex-direction: column; gap: 12px; border: 1px solid #eef2f7; border-radius: 12px; padding: 14px; background: #fbfcfe; }
.tb-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.tb-field { display: flex; flex-direction: column; gap: 4px; }
.tb-field > span { font-size: 11px; font-weight: 600; color: #475569; }
.tb-field input, .tb-field select, .tb-field textarea { border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 10px; font-size: 12px; outline: none; }
.tb-field input:focus, .tb-field select:focus, .tb-field textarea:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.1); }
.tb-hint { font-size: 11px; color: #94a3b8; margin: 0; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
textarea.bad { border-color: #dc2626; }
</style>
