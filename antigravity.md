# AI MASTER SYSTEM RULES & CLEAN ARCHITECTURE GUIDELINES

## 1. General Principles
- **Architecture**: Strictly adhere to **Clean Architecture** and **SOLID Principles**.
- **Tech Stack**: Backend Laravel (PHP 8.2+) + Frontend Vue.js 3 (Composition API / `<script setup lang="ts">`).
- **Separation of Concerns (SoC)**: Maintain absolute separation between Backend business logic, API Presentation layer, and Frontend UI components.
- **Strict Response Format**: When answering coding/technical tasks, ALWAYS structure responses into:
  1. **Algorithm & Execution Steps**
  2. **Target File(s) to Modify/Create**
  3. **Location of Code (Line/Section)**
  4. **Code Content (New/Updated)**
  5. **Expected Output & Result**

---

## 2. Architecture & Layering Rules (Laravel)

Follow the strict unidirectional flow:
`Route -> Controller -> FormRequest -> DTO -> UseCase/Action -> Domain/Repository Interface -> Infrastructure/Eloquent -> Database`

### Layer Specifics:
1. **Presentation Layer (Controllers):**
   - Controllers MUST be lean (Thin Controllers).
   - Responsible ONLY for receiving requests, invoking UseCases, and returning JSON/Inertia responses.
   - NO direct Eloquent queries, business logic, or authorization code inside Controller methods.

2. **Validation & Security Layer (FormRequests):**
   - All input validation and permission authorization (`$request->user()->can(...)`) MUST reside in dedicated `FormRequest` classes.

3. **Application Layer (Use Cases & DTOs):**
   - Each business capability must be encapsulated in a single UseCase/Action class (e.g., `AuthenticateUserUseCase`, `UpdateUserStatusUseCase`).
   - Use DTOs (`Data Transfer Objects`) to transfer data safely into UseCases.
   - Use Cases coordinate domain logic, trigger events, and manage security flows (e.g., immediate token revocation upon user lock).

4. **Domain Layer (Entities & Interfaces):**
   - Place Repository Interfaces in the Domain layer (`App\Domain\{Module}\Repositories`).
   - Domain code MUST NOT depend on Laravel frameworks, DB engines, or Eloquent.

5. **Infrastructure Layer (Repositories & Drivers):**
   - Concrete implementations of Repositories reside in `App\Infrastructure\{Module}\Repositories`.
   - All DB operations, JWT/Sanctum interactions, and external API calls stay here.

---

## 3. Architecture Rules (Vue.js 3 + TypeScript)

1. **Layer Breakdown:**
   - **Pages/Views**: Orchestrate layouts and invoke Composables.
   - **Components**: Pure UI presentation components with explicit `defineProps` and `defineEmits`.
   - **Composables**: Centralize reactive states, authentication logic, and API calls (e.g., `useAuth.ts`, `useUserAdmin.ts`).
   - **Services/API**: Centralize Axios/Fetch endpoints. Never hardcode API paths inside `.vue` files.

2. **Security & State Management:**
   - Access Tokens MUST be stored in memory/Pinia state (not `localStorage`).
   - Refresh Tokens MUST be handled via Secure, HTTP-Only Cookies with rotation logic.

---

## 4. Centralized Authentication & Admin Domain Standards

- **Centralized Auth Flow**: Enforce short-lived Access Tokens (15 mins) and long-lived Refresh Tokens.
- **User Admin & IAM**: Any status change locking a user (`suspended`, `locked`) MUST immediately trigger `revokeAllTokens($userId)` in the UseCase to invalidate active sessions instantly.
- **Audit Trace**: Write immutable audit logs for critical admin actions (e.g., role changes, status updates, token revocations).

---

## 5. Output Standards for AI
- DO NOT produce monolithic code snippets or mix layers.
- Output complete, fully runnable code blocks without omitting lines using `// ... rest of code`.
- Include full type hints, PHP return types, and TypeScript type interfaces.