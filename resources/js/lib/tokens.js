// Interpolation tokens the engine understands (VariableInterpolator). Offered as
// quick-insert chips on string fields.
// `needsTarget` tokens only make sense when the workflow calls a Target
// (HTTP/service/event) — there's no response to reference otherwise, so the
// builder hides them when no target is set.
export const TOKENS = [
  { label: 'payload value', token: '{{ payload.key }}' },
  { label: 'response body', token: '{{ response.body.key }}', needsTarget: true },
  { label: 'response status', token: '{{ response.status }}', needsTarget: true },
  { label: 'user id', token: '{{ user.id }}' },
  { label: 'user email', token: '{{ user.email }}' },
  { label: 'site id', token: '{{ site.id }}' },
  { label: 'env var', token: '{{ env.VAR_NAME }}' },
  { label: 'with default', token: "{{ payload.key | default: 'value' }}" },
];
