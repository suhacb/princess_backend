# Princess — Master Implementation Plan

Princess is a PRINCE2-aligned project management platform built for the PM, Project Board, and QA of a complex, high-risk banking CORE system implementation. It ingests data from M365 (email, SharePoint, Teams), applies AI classification, and provides structured support for all PRINCE2 themes and processes.

---

## Stack

| Layer | Technology |
|---|---|
| Backend | Laravel (PHP) |
| Frontend | Angular SPA |
| Database | PostgreSQL |
| Vector store | Qdrant |
| Full-text search | ZincSearch |
| AI inference | Ollama (gemma4:26b for heavy, gemma4:e4b for fast) |
| Auth | Custom gateway → Keycloak (JWT) |
| Integrations | Microsoft 365 (Graph API) — email, SharePoint, Teams |
| Infrastructure | Docker Compose (on-prem) |

---

## Phases

### Phase 1 — Foundation & Infrastructure
Scaffolding, Docker Compose, auth, and base project structure for both backend and frontend.

### Phase 2 — M365 Integration Layer
Polling services for email (dedicated mailbox + CC mailbox) and SharePoint delta sync; Teams channel read access.

### Phase 3 — Document Management
Document registry, SharePoint folder structure enforcement, ZincSearch + Qdrant indexing, AI classification pipeline with a review queue for uncertain items.

### Phase 4 — PRINCE2 Project Structure
Core project entity with stages and tolerances; all seven PRINCE2 themes and all seven processes as navigable, actionable structures; role-based access (PM, Board, QA, Team Manager, Observer).

### Phase 5 — Logs & Registers
Daily Log, Issue Log (with escalation), Risk Log (probability/impact), Change Log, Quality Register, Lessons Log — all fully audited.

### Phase 6 — Planning & Task Tracking
WBS, stage plans, task assignments, M365 calendar sync (meeting detection → expected minutes), full audit trail on every entity.

### Phase 7 — Quality Assurance Module
Requirements (functional, non-functional, constraints), acceptance criteria, test scenarios, test session records, traceability matrix.

### Phase 8 — AI Features
Ollama integration with model routing; auto-classification of emails and documents; AI-assisted log entry suggestions; AI-assisted status report drafting.

### Phase 9 — Status Reporting
Automated event collection, plan-vs-actual comparison, Highlight Report, Checkpoint Report, Exception Report alerting.

### Phase 10 — Dashboards & UI
Project Manager dashboard, Project Board view, QA/requirements dashboard, global search (ZincSearch + Qdrant semantic).

---

## Issue Naming Convention

Issues are prefixed by area:

| Prefix | Area |
|---|---|
| `INFRA` | Infrastructure & scaffolding |
| `AUTH` | Authentication & authorization |
| `M365` | Microsoft 365 integrations |
| `DOC` | Document management |
| `P2` | PRINCE2 project structure |
| `LOG` | Logs & registers |
| `PLAN` | Planning & task tracking |
| `QA` | Quality assurance module |
| `AI` | AI features |
| `RPT` | Reporting |
| `UI` | Frontend dashboards & UX |

---

## Branch Convention

Each issue gets a branch: `feature/<PREFIX>-<number>-short-description`

Example: `feature/INFRA-01-docker-compose`

---

## Definition of Done (per issue)

1. Implementation complete and passing tests
2. OpenAPI spec updated (backend) / Angular service updated (frontend)
3. PR reviewed and merged to `main`
4. Issue closed, milestone progress updated

---

## Architecture Notes

### External Interfaces — Service Provider Rule

**All external system integrations must be implemented as Laravel Service Providers.**

This applies to every system boundary where Princess talks to something outside its own process:

| External System | Service Provider | Contract | Issues |
|---|---|---|---|
| `auth_backend` gateway | `AuthServiceProvider` | `AuthGatewayClientContract` | AUTH-01 |
| Microsoft Graph API (M365) | `GraphServiceProvider` | `GraphClientContract` | M365-01 through M365-06 |
| Ollama inference API | `OllamaServiceProvider` | `OllamaClientContract` | AI-01 through AI-04 |
| Qdrant vector store | `QdrantServiceProvider` | `QdrantClientContract` | DOC-04 |
| ZincSearch | `ZincSearchServiceProvider` | `ZincSearchClientContract` | DOC-03 |

**What each Service Provider must do:**
- Bind a typed contract (interface) into the service container — no class is allowed to `new` a client directly
- Read all connection config from `config/services.php` (populated from env) — no hardcoded URLs or credentials anywhere in service code
- Register a singleton client instance with connection health-check on boot (dev only) or on demand (production)
- Expose a Facade for ergonomic call-site usage where appropriate

**Why:**
- Swap or mock any external system in tests without touching call sites
- Consolidate env/config validation for each integration in one place
- A misconfigured integration fails fast at boot rather than deep in a job

New issues (INFRA-05) establish the base interface/provider scaffolding before the integration phases begin.

---

### Auth Flow

Princess does **not** communicate with Keycloak directly. All auth is delegated to `auth_backend` (GitHub: `suhacb/auth_backend`), a dedicated Laravel service that wraps Keycloak. The reference implementation is `nutrients_backend` (GitHub: `suhacb/nutrients_backend`).

**Token validation flow (per request):**
1. Angular sends `Authorization: Bearer {token}` + `X-Refresh-Token` + `X-Application-Name` + `X-Client-Url` headers
2. `VerifyFrontend` middleware calls `auth_backend` → `GET /api/auth/validate-access-token`
3. `auth_backend` introspects with Keycloak; returns `true` (valid), a new `AccessToken` object (refreshed), or 401
4. If valid/refreshed: `TokenParser` base64-decodes the JWT payload locally to extract claims (`sub`, `email`, `preferred_username`, `name`, etc.) — no local signature validation needed
5. `UserService::handleUserFromToken()` does `firstOrCreate` on `users.external_id = sub`
6. `Auth::login($user)` — request proceeds as authenticated

**Login flow:**
- `GET /api/auth/login` → returns `{ redirect_uri }` pointing to `auth_backend` frontend login page (with `appName` + `appUrl` query params)
- After Keycloak login, auth_backend frontend returns tokens to the Angular app
- Angular stores tokens and sends them on every subsequent request

**Service Provider (`AuthServiceProvider`):**
- Binds `AuthGatewayClientContract` → `AuthBackendClient` (an HTTP client wrapping auth_backend endpoints)
- Config key: `config('princess.auth')` — see `config/princess.php`

**Required env vars:**
```
AUTH_BACKEND_URL=
AUTH_BACKEND_PORT=
APP_NAME=princess
APP_BACKEND_URL=
APP_BACKEND_PORT=
APP_FRONTEND_URL=
APP_FRONTEND_PORT=
```

**Princess must be registered** as an `Application` record in `auth_backend`'s database (name, realm, client_id, client_secret, url, callback_url) before auth will work.

### M365 Integration
Uses Microsoft Graph API with OAuth2 client credentials (for daemon polling) and delegated tokens where needed. Delta queries are used for SharePoint to minimize API calls. Email polling runs on a Laravel scheduled job; SharePoint delta sync runs as a separate queue worker.

### AI Classification Pipeline
1. Ingest item (email / document)
2. Fast model (gemma4:e4b) assigns candidate category + confidence score
3. If confidence ≥ threshold → auto-classify and index
4. If confidence < threshold → add to review queue for human decision
5. Human decision feeds back as training signal (future fine-tuning)
6. All classified items embedded via Qdrant for semantic search

### Document Registry
Every document known to the system has a registry entry: SharePoint URL, local metadata, classification, current version hash, and index status (ZincSearch + Qdrant). Folder location on SharePoint is part of the classification — the folder structure is enforced by the platform, so location carries semantic meaning.

### PRINCE2 Alignment
The platform does not enforce a rigid sequential process flow. Instead, it surfaces the right information and actions for each process/theme combination. A PM can navigate by process (e.g. "Controlling a Stage") or by theme (e.g. "Risk") and see the relevant logs, documents, tasks, and AI summaries.
