import { createApp } from 'vue';
import App from './App.vue';

/**
 * Boot the Interactive Workflow Manager. Mounts a self-contained Vue 3 app onto
 * the `#wf-builder` element rendered by the admin blade view. Initial data is
 * read from a JSON <script> tag so large config payloads don't fight HTML
 * attribute escaping.
 */
function boot() {
  const el = document.getElementById('wf-builder');
  if (!el) {
    return;
  }

  // Init payload arrives base64-encoded in a data attribute — the admin's Vue
  // app compiles this page and strips <script> tags, but element attributes on
  // this v-pre island survive.
  let initial = {};
  const raw = el.getAttribute('data-init');
  if (raw) {
    try {
      initial = JSON.parse(decodeURIComponent(escape(atob(raw))));
    } catch (e) {
      window.console && console.error('[workflow-builder] bad init payload', e);
    }
  }

  createApp(App, { initial }).mount(el);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
