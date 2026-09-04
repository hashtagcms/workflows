# SSO & External Login

Workflow execution records *who* ran a workflow. By default that identity comes
from the local Laravel guard (`auth()`), but many installs put login behind a
separate service — an SSO/IdP, an auth microservice, or an API gateway. This
package resolves identity through a small, pluggable seam so it never assumes
Laravel login is in use.

## The layers

1. **`WorkflowIdentityResolver`** — the contract the engine calls instead of
   `auth()`. The default binding (`AuthIdentityResolver`) wraps the local guard.
2. **`WorkflowIdentity`** — the normalized result: an integer `id` is a *local*
   user (logged as `workflow_logs.user_id`); a string `id` is an *external*
   subject (logged as `external_user_id`). It carries `claims` and the resolving
   `provider` alias.
3. **SSO provider module** — a data-driven driver on top of the contract,
   configured in the admin (no code).

You can also bypass all of this and pass an identity straight in:

```php
Workflows::execute($alias, $payload, identity: $userOrIdOrWorkflowIdentity);
```

## Managing providers (admin)

**Workflows → SSO Providers** (`admin/workflows/sso`). Each row is per-site (with
master-site fallback, like workflows) and has:

| Field | Meaning |
|---|---|
| `alias` | Stable key (e.g. `example-sso`). |
| `driver` | `opaque` or `jwt`. |
| `enabled` / `publish_status` | Whether the row participates in resolution. |
| `on_failure` | `reject` → surface a failed identity (caller returns 401); `anonymous` → run unauthenticated. |
| `cache_ttl` | Seconds to cache a verified result (per token). |
| `config` | JSON: the verify + identity mapping (below). |

## `opaque` driver — introspect the token

The client's token is meaningless to us, so we call the login service to validate
it and read back the user. The `verify` block is the request formatter (it reuses
the same shape as a workflow HTTP target), `identity` is the response formatter:

```json
{
  "verify": {
    "url": "https://login.example.com/sso/authenticate",
    "method": "POST",
    "headers": { "Accept": "application/json" },
    "body": { "token": "{{request.bearer_token}}" }
  },
  "identity": {
    "user_id": "{{response.body.data.user.id}}",
    "claims": {
      "email": "{{response.body.data.user.email}}",
      "roles": "{{response.body.data.user.roles}}"
    }
  }
}
```

Available interpolation sources: `{{request.bearer_token}}`, `{{request.headers.*}}`,
`{{request.query.*}}`, and (in `identity`) `{{response.body.*}}` / `{{response.status}}`.
Verified results are cached for `cache_ttl` seconds; failures are not.

## `jwt` driver — verify locally

If the login service issues a signed JWT, verify its signature against the
provider's JWKS — no per-request call. Claims are exposed as `{{token.*}}`:

```json
{
  "jwks_url": "https://login.example.com/sso/jwks",
  "issuer": "login.example.com",
  "audience": "workflows",
  "identity": {
    "user_id": "{{token.sub}}",
    "claims": { "email": "{{token.email}}", "roles": "{{token.roles}}" }
  }
}
```

> The `jwt` driver requires the optional `firebase/php-jwt` package:
> `composer require firebase/php-jwt`. The `opaque` driver needs nothing extra.

## Where the token comes from (`credential`)

By default the caller's token is read from the standard `Authorization: Bearer
<token>` header. Many real APIs use a **different header**, sometimes with a
prefix — point the provider at it with a `credential` block (works for both
drivers):

```json
"credential": { "header": "sessiontoken", "strip_prefix": "Bearer " }
```

- `header` — the request header to read the token from (e.g. `sessiontoken`,
  `x-auth-token`). Case-insensitive.
- `strip_prefix` — optional; removed from the front of the value if present
  (e.g. `"Bearer "`). Omit it when the header carries a bare token.

The extracted token becomes `{{request.bearer_token}}` for your `verify` block.
When `credential.header` is set it is authoritative — the driver does **not**
fall back to `Authorization`, and a request without that header is treated as
anonymous (no verify call is made). Omit the whole `credential` block to keep the
`Authorization: Bearer` default.

You can also forward the raw incoming headers to your validate endpoint via
`{{request.headers.*}}` (e.g. `{{request.headers.api_key}}`), which is handy when
the endpoint needs more than just the token.

**In the admin UI**, the SSO provider form exposes this as a **"Where the token
comes from"** control: leave it on *Authorization: Bearer (default)*, or switch to
*Custom header* to set the header name and optional strip-prefix. The control
reads and writes the `credential` key of the config JSON below it, so both stay in
sync.

## Claims — using the identity inside a workflow

**Claims** are the normalized identity attributes carried by a `WorkflowIdentity`.
The name follows the standard JWT/OAuth convention (an ID token is "a set of
claims about the subject"), so it is deliberately kept distinct from a workflow's
own HTTP `{{ response.* }}` (which means *that workflow's* target response) and
from `{{ user.* }}` (the local Eloquent user model, which is empty for external
subjects that have no local row).

The `identity.claims` block in a provider's config is where you *produce* claims —
you map the arbitrary validate response into a stable set of keys:

```json
"identity": {
  "user_id": "{{response.body.data.id}}",
  "claims": { "email": "{{response.body.data.email}}", "roles": "{{response.body.data.roles}}" }
}
```

A workflow then *consumes* them via `{{ claims.* }}`, regardless of which provider
(or driver) resolved the caller:

```json
"on_success": {
  "message": "Hello {{ claims.email | default: 'there' }}",
  "data": { "roles": "{{ claims.roles }}" }
}
```

This is the decoupling point: workflows depend on the **stable claim names you
chose**, never on the raw shape of a specific login provider's response. `user_id`
stays the one canonical field the engine itself relies on (logging, `auth_required`).
See the interpolation reference in [Creating Custom Workflows](04-creating-custom-workflows.md).

### Raw passthrough (`{{ identity.raw }}`)

Sometimes you don't want to enumerate every field. A provider may also map an
**opt-in** raw bucket, carried through verbatim and exposed as `{{ identity.raw.* }}`:

```json
"identity": {
  "user_id": "{{response.body.data.id}}",
  "claims":  { "email": "{{response.body.data.email}}" },
  "raw":     "{{response.body.data}}"
}
```

Now a workflow can read anything the validator returned, e.g.
`{{ identity.raw.profile.mobile }}`. Use this deliberately — it is the one place
that **reintroduces coupling** to the provider's response shape, and it carries
whatever the endpoint returns (mind sensitive fields). It is empty unless you map
it; curated `claims` remains the recommended default. The `{{ identity.* }}`
namespace also exposes `user_id`, `external_user_id`, and `provider`.

## How resolution runs

For a request, `SsoIdentityResolver` picks the provider for the site
(`site_id` input / `X-Site-Id` header, else master), dispatches to the driver,
and applies `on_failure`. The credential is the request's `Authorization: Bearer`
token by default, or the header named in the provider's [`credential`](#where-the-token-comes-from-credential)
block. With no matching provider it falls back to the local guard.

## Which provider a workflow uses

A workflow is **not tied to a provider** by default — identity is resolved by the
site's default provider. When a site has more than one enabled provider, the
default is the site's own row (over the master fallback), then the lowest id;
the pick is deterministic but implicit.

To be explicit — or to use a *non-default* provider for a particular workflow —
**pin it**: set the workflow's `sso_provider_alias` to a provider alias. The
resolver then uses that provider, falling back to the site default only if the
alias no longer resolves (disabled/removed), so a stale pin degrades gracefully
rather than failing.

- **In the Workflow Manager**, the *Identity provider (SSO)* dropdown appears when
  the SSO module is active. Pick the provider that should verify the caller, or
  choose *None — ignore SSO (local login only)*. A new/unpinned workflow defaults
  to the site's default provider (shown selected), so which provider runs is always
  explicit. The line below it states which provider will run, or that SSO is
  ignored.
- **Ignoring SSO per workflow.** Choosing *None* opts the workflow out of SSO
  entirely — identity is resolved by the local guard even while a provider is
  enabled for the site. Use it for a provider you created ahead of use, or for
  workflows that should stay on local login. Stored as the reserved
  `sso_provider_alias` value `@none` (`SsoIdentityResolver::PROVIDER_NONE`). Note a
  `None` + `auth_required` workflow will 401 external token callers, since only a
  local session authenticates.
- **After a run**, `workflow_logs.sso_provider_alias` records the provider that
  actually resolved the identity (see [What gets logged](#what-gets-logged)).

## Enforcement (401)

`Workflows::execute()` blocks a run before the handler when either:

- the credential was **rejected** (a bad/expired token under an `on_failure = reject`
  provider), or
- the workflow is flagged **`auth_required`** and no identity was resolved.

Blocked runs return a `WorkflowResponse` with a 401 status hint and an error
toast; the execution API controller maps that hint to an HTTP **401**. The
attempt is still written to `workflow_logs` (as unsuccessful). An anonymous call
to a workflow that is *not* `auth_required` runs normally.

The SSO-backed resolver is swapped in automatically (over the local guard) only
when the provider module is active — its table exists and at least one provider
is enabled — so installs without SSO are unaffected and pay no per-request
lookup. Server-to-server / queued callers with no request can pass an identity
explicitly into `execute()`.

## `auth_required` and the validation request (no black box)

`'auth_required' => true` on a workflow row means one thing: **the engine will
not run this workflow unless a valid identity was resolved for the caller.** It
does *not* say *how* the token is checked — that is the provider's job. Here is
exactly what happens, end to end, with an `opaque` provider active.

**1. The client calls the workflow with its bearer token:**

```bash
curl -X POST https://app.example.com/api/hashtagcms/public/workflows/v1/execute \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "Authorization: Bearer <token>" \
  -d '{ "workflow": "WORKFLOW_WHOAMI", "payload": {} }'
```

**2. Before running the workflow, the engine makes the validation request your
provider config describes.** With the provider below —

```json
"verify": {
  "url": "https://login.example.com/api/hashtagcms/user/v1/me",
  "method": "GET",
  "headers": { "Accept": "application/json", "Authorization": "Bearer {{request.bearer_token}}" }
}
```

— the engine issues **exactly this** request (this is the "curl" it runs for you;
`{{request.bearer_token}}` is the caller's token from step 1):

```bash
curl -X GET https://login.example.com/api/hashtagcms/user/v1/me \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>"
```

The URL, method, headers and body all come from the provider's `verify` block —
nothing is hidden. (A `jwt` provider makes **no** outbound call: it verifies the
token's signature locally against the cached JWKS instead.)

**3. The outcome decides whether the workflow runs:**

| Validation request result | What the engine does |
|---|---|
| **2xx** | Maps the response into the identity (`user_id` + `claims`) and runs the workflow. |
| **non-2xx** (e.g. 401) | Identity is *rejected*; with `on_failure: reject` the run returns **401** `"Invalid or expired credentials."` and the workflow never executes. |
| **no token sent** | With `auth_required: true`, returns **401** `"Authentication required."` (the validation request is not even made). |

So `auth_required` is the *switch*, and the provider's `verify` block is the
*actual request* — you can copy that curl and run it yourself to see precisely
what the engine sees.

## What gets logged

| Column | Populated when |
|---|---|
| `user_id` | Local login (integer id). |
| `external_user_id` | External subject (SSO/UUID `sub`). |
| `sso_provider_alias` | The provider that resolved the identity. |

See [Audit Logging & Analytics](07-audit-logging-and-analytics.md) for the full
column list.

## Companion: a login workflow (obtaining a token)

SSO providers *verify* a token; a workflow can *obtain* one. The bundled
`WORKFLOW_LOGIN_TEST` seed (`LoginTestWorkflowSeeder`) POSTs `{{payload.email}}` /
`{{payload.password}}` to an external HashtagCMS login API and returns the issued
`access_token` + user via `on_success.data` — credentials are never stored in the
workflow. It is **not** auto-installed (it targets a specific host); seed it on
demand and point it at your environment:

```bash
HASHTAGCMS_WORKFLOWS_TEST_LOGIN_URL="https://auth.example.com/api/hashtagcms/public/user/v1/login" \
  php artisan db:seed --class="HashtagCms\\Workflows\\Database\\Seeders\\LoginTestWorkflowSeeder"
```

Run it through the execution API (the client then stores the returned token and
sends it as `Authorization: Bearer …` on later calls, which an SSO provider
verifies):

```bash
curl -sS -X POST "https://app.example.com/api/hashtagcms/public/workflows/v1/execute" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{ "workflow": "WORKFLOW_LOGIN_TEST", "payload": { "email": "you@example.com", "password": "<your-password>" } }'
```

A success returns `data.token`; bad credentials surface the login API's own
message (e.g. *"Email or password is incorrect."*).
