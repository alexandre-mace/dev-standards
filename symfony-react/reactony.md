# Reactony: the Symfony + React convention

> One source of truth, no duplication, one pattern.
>
> **Last watch: 29 September 2026** (`/gap-sota`), start from this date on the next run. Reference versions verified: React 19.3.0 · React Compiler 1.0 (native plugin-react 6.1 path, see §7) · @vitejs/plugin-react 6.1 / Vite 8.3 (Rolldown) · Symfony Reprise 1.3 (see §6) · symfony/ux 3.5 · TanStack Query 5.104 · RHF 7.89 (v8 still in beta) · Zod 4.6 · @hey-api/openapi-ts 0.99 (exact pin) · Tailwind 4.3 · shadcn (`Field` family; CLI 4.21, `cn` package) · Vitest 5.0 (see §9) · MSW 3.0 (see §9) · Playwright 1.63 · eslint-plugin-react-hooks 7.1 · TypeScript 7 (native, GA, see §8).

**React itself is documented once**, in `react/react-guidelines.md`: React 19, the
compiler, shadcn and Base UI, the test doctrine. This file carries the Symfony seam.

## Routing: what to read for which task

Read the **Principles** (below) and the **anti-patterns (§10)** for every task; then only the sections that apply, not the whole file.

| Task | Sections |
|---|---|
| Display data (props, useQuery) | §1 Reading · §5 Pipeline |
| Form (create/edit) | §4 Form · §3 422 errors · §2 Writing |
| File upload | §2 (+ symfony-guidelines §4) |
| New endpoint consumed by React | §5 Pipeline · §2 Writing |
| New component / Twig mount | §6 Infra · §7 Conventions |
| "React or Stimulus/Turbo?" | §6 (When React, when Stimulus) |
| Perf / memoization / React Compiler | §7 (Performance) |
| Frontend tests | §9 Tests · §8 QA |
| Unsure about a pattern | §11 Summary |

## Principles

1. **PHP is the source of truth**: types and validation live on the entity
2. **Generated types and Zod v4**: from the PHP `#[Assert\...]`, never written by hand
3. **One form pattern**: RHF `Controller` + shadcn `Field` + Zod + `useMutation` (simple actions: `useMutation` + toast, see section 4)
4. **Entity or DTO**: the entity directly when the payload maps 1:1; a DTO when it is a subset of a large entity (security: allowlist, mapping through `ObjectMapper`) or when the payload has no matching entity
5. **Auth and security in Twig**: login, registration and password stay classic Symfony forms
6. **React means Reactony, Twig means Symfony Form**: if the page is React and dynamic, the form follows Reactony. If the page is Twig without React and the form is simple (nothing dynamic), a classic Symfony Form is enough
7. **SDK everywhere**: always use the generated SDK functions, uploads included (the SDK handles multipart through `formDataBodySerializer`)
8. **pnpm**: the frontend package manager
9. **React for interactivity, Stimulus for mounting only**: no new custom Stimulus controller, Turbo Drive disabled (see section 6)

---

## 1. Reading: Symfony → React

### At mount: Twig props

```twig
<div {{ react_component('MonComposant', {
    farm: farm|serialize('json', { groups: ['farm:read'] }),
    departments: getDepartments(),
}) }}></div>
```

### Dynamic (filters, pagination): TanStack Query

`queryOptions()` are **auto-generated** by hey-api's `@tanstack/react-query` plugin (see section 5). No need to write them by hand:

```tsx
// Imported straight from the generated code
import { getResourceListOptions } from "@/lib/api";

const { data, isLoading } = useQuery({
  ...getResourceListOptions({ query: filters }),
});
```

On the Symfony side, filters are typed with `#[MapQueryString]` on a **DTO** (the one case where a DTO is justified: GET filters are not an entity):

```php
/** @return array<int, Farm> */
#[Route('/api/farms', methods: ['GET'], format: 'json')]
#[Serialize(context: ['groups' => ['farm:read']])]
public function list(#[MapQueryString] FarmFilterDto $filters = new FarmFilterDto()): array
{
    return $this->farmRepository->findByFilters($filters);
}
```

### Serialization (API → React)

By default, use the **Symfony Serializer + `#[Groups]`**, through `#[Serialize]` on the action (`symfony-guidelines.md` §3):

```php
#[Serialize(context: ['groups' => ['advert:read']])]
public function list(): array
{
    return $this->advertRepository->findPublished();
}
```

The `#[Groups]` on the entity control what gets exposed:

```php
#[ORM\Column]
#[Groups(['advert:read'])]
private string $title;

#[ORM\ManyToOne]
private ?User $user = null;  // no Group, so never exposed
```

For complex cases (computed S3 URLs, data spanning several entities), use a **Formatter** in `Service/` (see symfony-guidelines.md).

---
## 2. Writing: React → Symfony

### Symfony: `#[MapRequestPayload]` on the entity

> Detailed conventions for DTOs, `#[MapRequestPayload]`, `#[MapUploadedFile]` and `ObjectMapper`: see `symfony-guidelines.md` section 4.

When the payload maps 1:1 to the entity, use the entity directly. `#[Groups]` are only needed if the entity has fields you don't want exposed (relations, internal flags).

```php
class SearchFarmNotification
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;  // private with no Group, so the Serializer ignores it

    #[Assert\NotBlank]
    #[Assert\Count(min: 1)]
    public array $canals = [];

    public bool $hasNoLocation = false;

    public array $departements = [];

    #[Assert\PositiveOrZero]
    public ?int $priceMin = null;
}
```

```php
#[IsGranted('ROLE_USER')]
#[Route('/api/search-farm/alert', methods: ['POST'], format: 'json')]
public function create(#[MapRequestPayload] SearchFarmNotification $notification): JsonResponse
{
    $notification->setUser($this->getUser());
    $this->entityManager->persist($notification);
    $this->entityManager->flush();
    return $this->json(['ok' => true], 201);
}
```

`format: 'json'` is **mandatory**: without it, 422 errors come back as HTML.

`#[IsGranted('ROLE_USER')]` is **mandatory** on `/api/` routes: no protection through URL-pattern `access_control`.

### Update (PUT)

```php
#[IsGranted('ROLE_USER')]
#[Route('/api/search-farm/alert', methods: ['PUT'], format: 'json')]
public function update(#[MapRequestPayload] SearchFarmNotification $updated): JsonResponse
{
    $existing = $this->getUser()->getSearchFarmNotification();
    $existing->setCanals($updated->canals);
    $existing->setDepartements($updated->departements);
    $existing->setPriceMin($updated->priceMin);
    // ...
    $this->entityManager->flush();
    return $this->json(['ok' => true]);
}
```

On the React side, same pattern as POST, only the method changes:

```tsx
const mutation = useMutation({
  mutationFn: async (values: FormValues) => {
    const result = await putAlert({ body: values });
    const errors = handleSdkError(result);
    if (errors) {
      Object.entries(errors).forEach(([field, msg]) => form.setError(field as any, { message: msg }));
      throw new Error("Validation failed");
    }
  },
  onSuccess: () => queryClient.invalidateQueries({ ...getAlertsOptions() }),
});
```

> **Note**: `field as any` works around a React Hook Form typing limitation, `Object.entries()` returns `string[]` instead of the field union type. It is the only `as any` accepted in the pattern.

### Heavy update (partial update, many fields)

> The allowlist DTO + `ObjectMapper` pattern is detailed in `symfony-guidelines.md` section 4.

When the payload updates many fields on an existing entity (a User profile with 14 fields, say), `#[MapRequestPayload]` is not enough because it builds a **new instance**.

The DTO acts as an **allowlist of accepted fields**: without it, a direct mapping would let someone send `{ "roles": ["ROLE_ADMIN"] }`. The ObjectMapper only maps the DTO properties that are **initialized** (fields absent from the JSON stay uninitialized, so they are ignored).

```php
#[IsGranted('ROLE_USER')]
#[Route('/api/profile/save', methods: ['POST'], format: 'json')]
public function save(
    #[MapRequestPayload] SaveProfilePayload $payload,
    ObjectMapperInterface $objectMapper,
    EntityManagerInterface $entityManager,
    ValidatorInterface $validator,
): Response {
    $currentUser = $this->getUser();

    $objectMapper->map($payload, $currentUser);

    $errors = $validator->validate($currentUser);
    if (count($errors) > 0) {
        return $this->json($errors, 422);
    }

    $entityManager->flush();
    return new Response();
}
```

> **Which one when?**
> - Few fields / dedicated entity → `#[MapRequestPayload]` on the entity + manual copy (see PUT above)
> - Many fields / existing entity → allowlist DTO + `ObjectMapper`
> - Payload ≠ entity (computed fields, aggregates, no matching entity) → DTO in `src/Dto/`

### File uploads: `UploadedFile` in the DTO (SF 8.1)
> Backend upload conventions are detailed in `symfony-guidelines.md` section 4.

Backend: a flat `#[MapRequestPayload]` DTO holding `?UploadedFile` plus the text fields, identifiers in the route (rules in `symfony-guidelines.md` §4). `#[MapUploadedFile]` remains the fallback for a lone file:

```php
#[IsGranted('ROLE_USER')]
#[Route('/api/parcours/upload-image/{fieldId}', methods: ['POST'], format: 'json')]
public function uploadProjectImage(
    string $fieldId,
    #[MapUploadedFile(name: 'image', constraints: [new Assert\NotNull(), new Assert\Image()])]
    UploadedFile $file,
): Response {
    // ...
}
```

On the React side the SDK handles multipart uploads through `formDataBodySerializer`. Use it like any other call:

```tsx
const mutation = useMutation({
  mutationFn: async (file: File) => {
    const result = await postAvatarUpload({ body: { avatar: file } });
    const errors = handleSdkError(result);
    if (errors) throw new Error(Object.values(errors)[0]);
    return result.data;
  },
  onSuccess: () => toast.success("Avatar mis à jour"),
  onError: (error: Error) => toast.error(error.message),
});
```

#### ⚠️ Convention: guard the file size client-side

Always check `file.size` **before** the network call and show an explicit toast. Reason: PHP silently drops uploads over `upload_max_filesize` / `post_max_size` (SAPI) **before** Symfony ever runs the `Assert\File(maxSize: …)` constraint. When that happens, `RequestPayloadValueResolver` sees a `null` payload and throws `HttpException(422)` **with an empty message**, the frontend gets a 422 with no `violations`, and the toast stays mute. We hit this in production with iPhones uploading photos over 5 MB.

Standard pattern, frontend limit mirroring the backend one:

```tsx
const AVATAR_MAX_SIZE_MB = 5; // keep in sync with Assert\File(maxSize) backend

setInput={(file) => {
  const f = file as File | null;
  if (!f) return;
  if (f.size > AVATAR_MAX_SIZE_MB * 1024 * 1024) {
    toast.error(
      `Photo trop grosse (${(f.size / 1024 / 1024).toFixed(1)} Mo). Maximum ${AVATAR_MAX_SIZE_MB} Mo.`
    );
    return;
  }
  uploadMutation.mutate(f);
}}
```

The backend keeps its `Assert\File(maxSize)` constraint as the last line of defence (the frontend can be bypassed on purpose). The two limits must stay aligned.

### Delete (DELETE)

```php
#[IsGranted('ROLE_USER')]
#[Route('/api/search-farm/alert', methods: ['DELETE'], format: 'json')]
public function delete(): JsonResponse
{
    $notification = $this->getUser()->getSearchFarmNotification();
    if ($notification) {
        $this->entityManager->remove($notification);
        $this->entityManager->flush();
    }
    return $this->json(['ok' => true]);
}
```

On the React side:

```tsx
const deleteMutation = useMutation({
  mutationFn: async () => {
    const result = await deleteAlert();
    handleSdkError(result);
  },
  onSuccess: () => queryClient.invalidateQueries({ ...getAlertsOptions() }),
});
```

### `#[Groups]` convention

Format: `entity:action`, lowercase.

| Use | Group name | Example |
|-------|-------------|---------|
| Read (serialization) | `entity:read` | `advert:read`, `farm:read` |
| Create (deserialization) | `entity:create` | `alert:create` |
| Update (deserialization) | `entity:update` | `alert:update` |

Add Groups **only when needed**: when the entity has fields to exclude (relations, internal flags). A simple entity doesn't need any.

```php
// Only when needed
#[MapRequestPayload(serializationContext: ['groups' => ['alert:create']])]
```

---

## 3. 422 errors
Symfony returns this automatically:

```json
{
  "type": "https://symfony.com/errors/validation",
  "title": "Validation Failed",
  "violations": [
    { "propertyPath": "canals", "title": "Choisis au moins un canal." }
  ]
}
```

`handleSdkError` (`lib/parseViolations.ts`) covers both cases:
- **422** → returns `Record<string, string>` (per-field errors, parsed from `violations`)
- **Any other error (403, 500…)** → `throw new Error(...)` (caught by `onError`)
- **No error** → returns `null`

> **Nullable enum gotcha**: react-hook-form defaults enum selects to `""` when left empty. On the backend, `Enum::from('')` throws a `ValueError`, so a 500. Either the controller coerces `'' → null` before denormalizing (see `symfony-guidelines.md` section 4), or the frontend omits the key. Do both, to be safe.

### Choosing the form library (re-validated June 2026)

`react-hook-form` + `zod` + `@hookform/resolvers` is the confirmed stack. TanStack Form v1 is mature but its server-error mapping is still less clean than `setError`, and `useActionState` has no tooling outside Next:

- **No migration to TanStack Form.** Non-trivial cost (rewriting `handleSdkError`, porting every `setError`), marginal gain given that openapi-ts + Zod already cover end-to-end type safety. RHF stays.
- **No migration to React 19 Actions** (`useActionState`) for forms with structured server validation. Mapping `violations[].propertyPath` to per-field errors isn't native to Actions, and Actions wants to own the `pending`/`error` state that TanStack Query already owns. Awkward double ownership.
- **Yes to `useOptimistic`** for instant-UI mutations (toggle favourite, add to list, reorder). It composes cleanly with RHF + TanStack Query.
- **`useFormStatus`: no, not in this pattern.** It only reports `pending` for a `<form action={...}>` (React Actions). With RHF + `useMutation` (submit through `onSubmit`) it would stay `false` forever. Submission state comes from `mutation.isPending`, or from `useFormState({ control }).isSubmitting` for a deeply nested button.
- **RHF floor: ≥ 7.85** (official `<Activity/>` support, indispensable if a form lives inside a `mode="hidden"` panel); 7.86 adds the type-safe `getErrors` method. v8 (the compiler-first rewrite) is still in beta (`8.0.0-beta.4`, September 2026): don't adopt it before stable.

```tsx
// useOptimistic: instant UI while a TanStack Query mutation is in flight
const [optimisticFavs, addOptimisticFav] = useOptimistic(
  favorites,
  (state, newId: number) => [...state, newId],
);

const mutation = useMutation({
  mutationFn: (id: number) => postFavorite({ body: { id } }),
  onError: () => toast.error('Échec'),
});

const toggle = (id: number) => {
  addOptimisticFav(id);
  mutation.mutate(id);
};
```

The mutation is the one in §4's `FarmAlertForm`: per-field `setError` on a 422, `setError("root")` otherwise. Render the root error through `useFormState`, not by reading the `form.formState` proxy at render time (React Compiler rule, see section 7):

```tsx
const { errors } = useFormState({ control: form.control });

{errors.root && (
  <p className="text-sm text-destructive">{errors.root.message}</p>
)}
```

---

## 4. React form
### Multi-field form: RHF + Zod + shadcn `Field`

For a form with several fields and client-side validation: **RHF `Controller` + shadcn `Field` family + generated Zod + `useMutation`**.

Use shadcn's agnostic **`Field`** components (`npx shadcn@latest add field`), not the RHF-coupled `<Form>/<FormField>/<FormMessage>` wrapper. Canonical shape:

```tsx
import { useForm, Controller } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation } from "@tanstack/react-query";
import { z } from "zod";
import { zSearchFarmNotification } from "@/lib/api/zod.gen"; // generated (see section 5)
import { postAlert } from "@/lib/api";
import { handleSdkError } from "@/lib/parseViolations";
import { Field, FieldLabel, FieldDescription, FieldError } from "@/components/ui/field";

type FormValues = z.infer<typeof zSearchFarmNotification>;

export function FarmAlertForm() {
  const form = useForm<FormValues>({
    resolver: zodResolver(zSearchFarmNotification),
    defaultValues: { canals: [], hasNoLocation: false },
  });

  const mutation = useMutation({
    mutationFn: async (values: FormValues) => {
      const result = await postAlert({ body: values });
      const errors = handleSdkError(result);
      if (errors) {
        Object.entries(errors).forEach(([field, msg]) => form.setError(field as any, { message: msg }));
        throw new Error("Validation failed");
      }
    },
    onError: (error: Error) => {
      if (error.message !== "Validation failed") {
        form.setError("root", { message: "Une erreur est survenue." });
      }
    },
  });

  return (
    <form onSubmit={form.handleSubmit((v) => mutation.mutate(v))} className="space-y-6">
      <Controller
        name="canals"
        control={form.control}
        render={({ field, fieldState }) => (
          <Field data-invalid={fieldState.invalid}>
            <FieldLabel htmlFor={field.name}>Canaux</FieldLabel>
            {/* shadcn component, with id={field.name} and aria-invalid={fieldState.invalid} */}
            {fieldState.invalid && <FieldError errors={[fieldState.error]} />}
          </Field>
        )}
      />
      <Button type="submit" disabled={mutation.isPending}>Enregistrer</Button>
    </form>
  );
}
```

The two keys: `data-invalid` on `<Field>` (flips the whole block into its error state) and `aria-invalid` on the control. The older `<Form>/<FormField>/<FormMessage>` pattern is tolerated in existing code but not for new code; migrate opportunistically when you touch the file.

**Flow**: Zod validates on the client → SDK → Symfony validates on the server → the 422 is rendered per field through `form.setError` + `<FieldError>`.

### Simple action / inline edit: `useMutation` + SDK + toast

For a single action (date picker, toggle, one field), RHF is overkill. `useMutation` + SDK + `handleSdkError` + toast is enough:

```tsx
import { useMutation } from "@tanstack/react-query";
import { handleSdkError } from "@/lib/parseViolations";
import { toast } from "sonner";
import { postFieldUpdate } from "@/lib/api";

const mutation = useMutation({
  mutationFn: async (data: { id: string; value: string }) => {
    const result = await postFieldUpdate({ body: data });
    const errors = handleSdkError(result);
    if (errors) throw new Error(Object.values(errors)[0]);
  },
  onSuccess: () => toast.success("Enregistré"),
  onError: (error: Error) => toast.error(error.message),
});
```

> **Which one when?**
> - Multi-field form with client validation → RHF + Zod + shadcn `Field`
> - Simple action, inline edit, toggle → `useMutation` + SDK + `handleSdkError` + toast

### Invalidating the cache after a mutation

Since TanStack Query 5.82, `mutationOptions()` is the counterpart of `queryOptions()`, and hey-api generates those too (`addPetMutation()` and friends): use them to factor out a mutation shared between components.

Floor: **`^5.102`**. That version drops the experimental APIs (render-time prefetching, the `promise` property on results, `experimental_beforeQuery`/`afterQuery`), fixes `queryOptions` type emission in the `.d.ts` (which directly benefits hey-api's generated options), and introduces `queryClient.query()`/`infiniteQuery()` in place of the older imperative methods, now deprecated.

Use the auto-generated queryOptions to invalidate with type safety:

```tsx
import { getResourceListOptions } from "@/lib/api";

const queryClient = useQueryClient();

const mutation = useMutation({
  mutationFn: postAlert,
  onSuccess: () => queryClient.invalidateQueries({ ...getResourceListOptions({ query: filters }) }),
});
```

---

## 5. Type pipeline

```
PHP entity + #[Assert\...] + DTO
    ↓  NelmioApiDocBundle
OpenAPI YAML
    ↓  @hey-api/openapi-ts
TS types + Zod v4 + SDK + queryOptions + mutationOptions (generated into assets/lib/api/)
```

### Setup
**Backend**:

```bash
composer require nelmio/api-doc-bundle
```

**Frontend**: a single dev package (plugins and clients are **bundled**, no separate npm package), at an **exact version** (`-E`: pre-1.0 project, the maintainers ask for a pin); `zod` as a runtime dependency:

```bash
pnpm add -D -E @hey-api/openapi-ts
pnpm add zod
```

```typescript
// openapi-ts.config.ts
import { defineConfig } from "@hey-api/openapi-ts";

export default defineConfig({
  input: "./openapi.yaml",
  output: "assets/lib/api",
  plugins: [
    "@hey-api/typescript",
    "@hey-api/client-fetch",
    { name: "@hey-api/sdk", validator: { response: "zod" } }, // runtime response validation: perf cost, opt-in
    "zod",                   // Zod 4 by default ({ name: "zod", compatibilityVersion: 3 | "mini" } otherwise)
    "@tanstack/react-query", // name of the bundled plugin, NOT an npm package
  ],
});
```

When bumping (the exact pin forces you to), read the [Migrating page](https://heyapi.dev/openapi-ts/migrating) for every version crossed. Zod 4.4 is deliberately stricter, so re-run the Vitest suite when bumping. Use `z.codec()` (typed bidirectional transforms, e.g. ISO string ↔ `Date`) and `z.invertCodec()` for hand-written API ↔ domain conversions. Put `z.compile(schema)` (same API, parses 3 to 9 times faster) on the generated schemas you validate at runtime (SDK responses, large form objects). **Floor `^4.6`**: 4.5 runs out of memory on some recursive schemas, and 4.6 fixes it. `schema.validate(value)` is a boolean guard up to 35 times faster than `.safeParse().success` on a compiled schema: use it when only the verdict matters, not the errors.

### Generation

```makefile
types:
    php -d memory_limit=512M bin/console nelmio:apidoc:dump --format=yaml > openapi.yaml
    pnpm openapi-ts
```

`openapi.yaml` and `assets/lib/api/` are **committed**: the drift gate compares what was generated against what is checked in, and `git diff --exit-code` would see nothing if they were gitignored.

In CI: `make types && git diff --exit-code openapi.yaml assets/lib/api/` catches the drift. On top of that, `oasdiff` classifies the `openapi.yaml` diff as breaking or non-breaking (see symfony-guidelines.md §13).

---

## 6. Infra: Vite + Symfony UX

React is mounted in Twig through **Symfony UX React** + **Symfony Reprise**. Link the JS package as `file:vendor/symfony/ux-react/assets` (the default wiring) so it follows the composer version: the npm `latest` dist-tag of `@symfony/ux-react` still points at 2.36, so a registry install does not give you 3.x.

### Layout

```
assets/
├── app.ts                    # Main entry point
├── react/controllers/        # React components mounted from Twig
│   └── mon_domaine/          # Organised by business domain
├── components/
│   ├── ui/                   # shadcn/ui (primitives)
│   └── mon_domaine/          # Reusable domain components
├── lib/
│   ├── api/                  # Generated by hey-api (types, zod v4, sdk, queryOptions, mutationOptions)
│   ├── parseViolations.ts    # 422 errors
│   └── queryClient.ts        # Shared QueryClient instance
```

- **`react/controllers/`** = page components mounted from Twig (entry points)
- **`components/`** = reusable components (UI primitives, domain components)
- **`lib/`** = utilities, API client, helpers
- **`lib/api/`** = everything hey-api generates (types, Zod v4, SDK, queryOptions, mutationOptions)

### Mounting a component

```twig
{# The component assets/react/controllers/mon_domaine/MonComposant.tsx #}
<div {{ react_component('mon_domaine/MonComposant', { ... }) }}></div>
```

Every component mounted from Twig is an **isolated React app**. If it uses TanStack Query (`useQuery`, `useMutation`), it has to wrap itself in a `<QueryClientProvider>`:

```tsx
import { QueryClientProvider } from "@tanstack/react-query";
import { queryClient } from "@/lib/queryClient";

const MonComposantApp = (props: MonComposantProps) => { /* ... useQuery, useMutation ... */ };

// Wrapper for the Twig mount
const MonComposant = (props: MonComposantProps) => (
  <QueryClientProvider client={queryClient}>
    <MonComposantApp {...props} />
  </QueryClientProvider>
);

export default MonComposant;
```

The `queryClient` is shared globally (`assets/lib/queryClient.ts`), never recreated per component.

**The split into two components is the point, not a style choice.** Calling a TanStack
Query hook in the same component that returns the provider puts the hook **above** the
provider in the tree: `No QueryClient set, use QueryClientProvider to set one`. With no
error boundary, React unmounts the root, so the page is **blank, usually with nothing in
the console** beyond a React warning naming the component. On one project that shipped an
entire tool blank in production for four months, reported by a user, invisible in the
server logs since the crash is fully client-side.

Inner component holds the hooks, exported wrapper holds the provider. And one provider
only: a nested second one costs no crash but renders a second `<Toaster />`.

To recover the real error behind a blank island, wrap the inner component in a temporary
class error boundary whose `componentDidCatch` logs `error.stack`, read the console, then
revert.
### Vite

The Symfony integration is **Symfony Reprise** (`composer require symfony/reprise` + `pnpm add -D @symfony/reprise`), Webpack Encore's official heir for Vite and Rsbuild, under the Symfony backward-compatibility promise. It replaces `pentatrion/vite-bundle` and `vite-plugin-symfony`, whose whole scope it covers. A project still on pentatrion is a gap **to close**, and not urgent, pentatrion not being deprecated.

**The migration is only mechanical** (Twig prefix `vite_` → `reprise_`, swap the plugin, import `startStimulusApp` from `@symfony/reprise/stimulus`) **when the project registers its React controllers with an eager glob from a single entry.** Attempted on a real repo (31 August 2026), two ux-react 3.4 limits block the rename-only path, verified in `vendor/symfony/ux-react/assets/dist/register_controller.js`:

- `registerReactControllerComponents` **hard-throws on a lazy glob** (`{ eager: false }`). Pentatrion's helper awaits the lazy import at mount, so each island ships as its own on-demand chunk; ux-react has no counterpart, and forcing `eager: true` pulls every controller (leaflet, maplibre…) into the main entry's graph on every page.
- Called from **several entries** (a page loading `app` plus a dedicated `leaflet_app`), it **rebuilds the registry from scratch instead of merging**: the last entry wins and the other's islands throw "does not exist" at mount. Its `normalizeGlobKeys` also strips the deepest common prefix of each registered set, so a scoped glob in a secondary entry resolves to names that no longer match the Twig `react_component()` calls.

**The proven path for such a repo** (migrated for real on 31 August 2026, full E2E suite green, lazy chunks verified in the build manifest): keep `react_component()` on the Twig side but bypass ux-react's JS controller entirely. Vendor pentatrion's MIT react helper (~60 lines: a module-level registry merged across `registerReactControllerComponents()` calls, and a Stimulus controller whose `connect()` awaits the module when the glob was lazy), disable the upstream controller in `controllers.json` (`@symfony/ux-react → react → enabled: false`), and register the vendored one under the identifier `react_component()` emits: `app.register('symfony--ux-react--react', ReactController)` after `startStimulusApp()`. Lazy per-page chunks and multi-entry pages survive unchanged. Header the vendored file with a pointer to `symfony/ux#1177` and remove it the day upstream supports lazy and merging. Everything else is the mechanical part: swap the Vite plugin, `vite_entry_*` → `reprise_entry_*`, `reprise.cache: true` in prod, and mind the Flex trap twice (installing Reprise stubs an `assets/app.js` and duplicates the entry tags in the base layout; removing pentatrion deletes files its recipe owns, `git status` right after).

**One prod-only trap survives the migration**: in the built output, the entry's own CSS chunk loads **before** the shared chunk that carries the Tailwind preflight, while the dev server follows JS import order. Any base-layer rule that relied on coming after the preflight (the classic `border-color: var(--color-border)` reset, JS-imported because `@tailwindcss/vite` strips base layers from CSS it processes) silently loses at equal specificity: every bare `border` goes `currentColor`, black. Neither dev, nor Vitest, nor an E2E run against the dev server sees it. Make such resets order-proof instead of order-dependent: `:root *` (specificity 0,1,0) beats the preflight's `*` whatever the stylesheet order, and staying in `@layer base` keeps explicit `border-*` utilities winning by layer order.

**While still on pentatrion**, one trap costs an hour every time. `pnpm build` rewrites
`public/build/.vite/entrypoints.json` in production mode, and Symfony then serves the
built bundles **even with `pnpm dev` running**: React changes stop appearing, HMR goes
quiet, and nothing errors. The same file carries both modes and the last writer wins. The
fix is to restart `pnpm dev`, which rewrites it in dev-server mode. The network tab
confirms the diagnosis: the page loads `/build/assets/Xxx-<hash>.js` from the Symfony port
instead of modules from `:5173`. Reflex: after any `pnpm build` in a dev session, restart
Vite before believing anything you see in the browser.

At least 1 entry point: `app` (the main one). Add more for heavy bundles loaded conditionally (maps, editors), or for the admin.

```ts
// vite.config.ts
import Symfony from "@symfony/reprise/vite";

export default defineConfig({
  input: { app: "./assets/app.ts", admin: "./assets/admin.ts" }, // Vite ≤ 8.1: build.rollupOptions.input
  plugins: [react(), Symfony({ stimulus: "assets/controllers.json" })],
});
```

```ts
// assets/app.ts
import { startStimulusApp } from "@symfony/reprise/stimulus";
import { registerReactControllerComponents } from "@symfony/ux-react";

registerReactControllerComponents(import.meta.glob("./react/controllers/**/*.{jsx,tsx}", { eager: true }));
startStimulusApp();
```

```twig
{{ reprise_entry_link_tags('app') }}
{{ reprise_entry_script_tags('app') }}
```

- **Nothing to pass for React**: in dev, Reprise injects Vite's HMR client and the React Fast Refresh preamble itself, which is why there is no equivalent to pentatrion's `{ dependency: 'react' }`.
- **In production, turn on `reprise.cache: true`** (`config/packages/reprise.yaml`): `entrypoints.json` is compiled to PHP at `cache:warmup` instead of being decoded on every request. Run `cache:clear` after each build.
- Other useful options: `integrity` (SRI), `copy` (files referenced by `asset()` from Twig), `builds` (several bundles), and the `RenderAssetTagEvent` to stamp a CSP nonce on every tag.
- **Commands**: `pnpm dev` (dev + HMR), `pnpm build` (production).

#### EasyAdmin

`Assets::addRepriseEntry()` is native since EasyAdmin 5.3, the exact counterpart of `addWebpackEncoreEntry()`. No layout override:

```php
public function configureAssets(): Assets
{
    return Assets::new()->addRepriseEntry('admin');
}
```

#### ⚠️ Migrating Webpack Encore → Vite: the Flex trap that deletes files

Removing `symfony/webpack-encore-bundle` deletes the files its Flex recipe owned and the `/node_modules/` + `/public/build/` lines of `.gitignore`: the procedure is in `symfony-guidelines.md` §14 (Removing a bundle). Afterwards, re-apply the lost Vite edits. Deployment stays transparent as long as the build hook runs `pnpm build`.

### When React, when Stimulus, when Turbo

One interactivity model:

- **A page is static Twig by default. Interactivity is a React island** (`react_component()`), however small: the pipeline (types, SDK, shadcn) makes an island cheaper to maintain than a Stimulus controller living outside that ecosystem.
- **Stimulus is mounting infrastructure only.** The `symfony/ux-react` bridge is itself a Stimulus controller, invisible, and untouched. **Do not write new custom Stimulus controllers**: no state, no fetch, no business logic in Stimulus. Tolerance: stateless micro DOM behaviour (under ~30 lines, copy-to-clipboard say) where an island would be disproportionate. Existing custom controllers are legacy, not a model to copy.
- **Special case, enriching a classic Symfony Form field** (rich editor, datepicker, autocomplete on a server-rendered `<input>`/`<textarea>`): that is a *legitimate* Stimulus use in itself (progressive enhancement, the Symfony UX model). BUT if the React equivalent already exists (a `Wysiwyg` component, say), **reuse it as an island** rather than maintaining a parallel Stimulus controller that duplicates it: mount the React component and have it **sync into the hidden field** (`document.getElementById(targetId).value = ...` on update) so it goes out with the POST. One editor for the whole app, the Symfony field stays the submitted source.
- **Turbo Drive: disabled globally (`<body data-turbo="false">`), on purpose.** Turbo navigation remounts React islands (state lost, double mount). Do not re-enable it without an explicit decision; for navigation polish, the route is native View Transitions (cross-document CSS). `ux-turbo` stays installed for possible Turbo Streams / Mercure use, not for the drive.

  Note that `<body data-turbo="false">` only covers the templates that carry it. An admin
  layout that does not inherit it leaves Turbo intercepting clicks towards EasyAdmin,
  which swaps the `<body>` without a real document load: TomSelect never initialises and
  the EA assets load partially. The symptom reads as a cache problem, since a hard reload
  "fixes" it, which is exactly what a forced full load would do. Disable `turbo-core` in
  `assets/controllers.json` rather than sprinkling the attribute.

  On a project that deliberately keeps Turbo Drive on, one combination bites: a Twig form
  sitting low in a long page, plus `html { scroll-behavior: smooth }`. On submit Turbo
  swaps the whole `<body>` and resets the scroll, then animates back down to the anchor,
  so the user watches the page fly to the top and scroll back down. Wrapping the form
  block in a `<turbo-frame id="…" class="block">` fixes it, with three traps.
  `class="block"` is mandatory: an unknown element defaults to `display: inline` and Turbo
  injects no CSS. `data-turbo-action="advance"` must be left out, since promoting the
  frame navigation to a visit restores the very scroll reset being fixed, at the price of
  the URL no longer changing on submit. And the status code decides: Turbo keeps a 2xx
  inside the frame but renders any 4xx or 5xx full page, which matters because Symfony
  answers **422** on an invalid form, so the frame only covers the "valid form, processing
  failed" case. Give the success page the same frame id so it renders in place instead of
  as a bare page.
- **RSC / React Server Components: we don't do them, on purpose.** Symfony + Twig **is** the server layer already, and the React islands are the intentional client leaves. Bolting RSC on would impose a Node rendering server next to PHP (dual role, broken CleverCloud deployment, extra RSC vulnerability surface) for a problem PHP already solves. Re-open the question only if we dropped server-rendered HTML for a 100% JS frontend.

---

## 7. React conventions

### Files and imports

- **Files**: PascalCase (`SearchFarmAlert.tsx`)
- **Folders**: snake_case (`search_farm/`, `skills_assessment/`)
- **`packageManager` pinned** in `package.json`, with the lockfile committed. A
  `package-lock.json` appearing means someone installed with the wrong tool.
- **Imports**: always the `@/` alias, never relative `../../`
- **No barrel files**, except for variant sets (`index.ts` exporting a set)

```tsx
// Good
import { Button } from "@/components/ui/button";
import { postProfileSave } from "@/lib/api";

// Bad
import InputWithLabel from "../../ui/composites/InputWithLabel";
```

### Typing

- **No `any`**: use the generated types from `@/lib/api` for API payloads, and interfaces for props
- **Typed props** through an `interface` in the same file as the component

```tsx
// Good
interface EditProfileProps {
  firstName: string;
  lastName: string;
  types: Record<string, string>;
}

const EditProfile = ({ firstName, lastName, types }: EditProfileProps) => { ... };

// Bad
const EditProfile = ({ firstName, lastName, types }) => { ... };
const result = await postProfileSave({ body: data as any });
```

For payloads sent to the SDK, cast to the generated type (`SaveProfilePayload`) or shape the form state to match the type directly.

### Data fetching: `useQuery` and `useMutation`

**Reading**: `useQuery` + the generated queryOptions (§1), never `useEffect` + `fetch()` + `useState` (no cache, no retry, no invalidation). **Writing**: `useMutation` + SDK + `handleSdkError` (§4), invalidating the affected queries in `onSuccess`.

### CSS classes

Use shadcn's `cn()` for conditional classes, no ternaries inside strings.

```tsx
// Good
<div className={cn("rounded-md border p-4", isActive && "bg-primary text-white")} />

// Bad
<div className={`rounded-md border p-4 ${isActive ? "bg-primary text-white" : ""}`} />
```

### shadcn and Base UI

`react/react-guidelines.md` §3: the base, picking the right element, the update workflow,
the upstream traps.

### Performance: React Compiler

What the compiler changes in the code you write is in `react/react-guidelines.md` §2.
Here, how it is enabled on a Vite build.

⚠️ Since `@vitejs/plugin-react` v6 (Vite 8), `react({ babel: {...} })` no longer exists
(Oxc transforms) and is **silently ignored**. Two configurations actually run it.

**Native path** (plugin-react ≥ 6.1), the compiler's Rust port, more than ten times
faster than Babel, with `oxc-transform-react` as an optional peer:

```js
plugins: [react({ compiler: true })]
```

Still marked experimental by the plugin: the target, to switch to during a maintenance
window, checking memoization holds in the Profiler.

**Babel path**, the stable one, with `babel-plugin-react-compiler`,
`@rolldown/plugin-babel` and `@babel/core`:

```js
plugins: [react(), babel({ presets: [reactCompilerPreset()] })]
```

**React Compiler × react-hook-form (v7)**: the `formState` proxy and `watch()` rely on internal mutability that the compiler memoizes wrongly (the lint's `incompatible-library` rule flags them). With the compiler on:

- ❌ `watch('field')` and reading `form.formState.X` at render; don't pass `formState` as a prop
- ✅ `useWatch({ control, name })`, `useFormState({ control })`, `useController` / `<Controller>` (explicit subscriptions); `getValues()` reserved for handlers and effects
- Transitional escape hatch: a `'use no memo'` directive on a problematic form component

---

## 8. Quality Assurance: frontend

### ESLint

```bash
pnpm lint          # check
pnpm lint:fix      # auto-fix
```

Flat config (`eslint.config.js`) with:
- `@eslint/js` + `typescript-eslint`: TS rules
- `eslint-plugin-react-hooks` ≥ 7 through `configs.flat.recommended`: hook rules and React Compiler rules (see `react/react-guidelines.md` §2). `set-state-in-effect` is an **error** from 7.1's recommended (inert in 7.0, so violations surface at that bump): fix them as derivations or event-scoped code rather than disabling
- `eslint-config-prettier`: disables the rules that conflict with Prettier

### Prettier

```bash
pnpm format        # format
pnpm format:check  # check without writing
```

Config (`.prettierrc`) with `prettier-plugin-tailwindcss` (≥ 0.8, which requires Prettier ≥ 3.7) for automatic class sorting, turned on for `templates/` as well: it sorts classes in Twig templates, function calls included.

### TypeScript strict

```bash
pnpm tsc --noEmit
```

**TypeScript 7 has been GA since July 2026**: the native Go port, published under the standard `typescript` npm package, `tsc` binary unchanged, checks 7 to 12 times faster, language server moved to LSP. Migrate from `^5.9` in two steps: **5.9 → 6.0** (adopt the new defaults and purge the deprecated flags, which 7.0 turns into hard errors) **→ 7.0**. Caveat: no programmatic API before TS 7.1; typescript-eslint (≥ 8.67) goes through the `@typescript/typescript6` shim, which doesn't block pure React/TSX. The concrete setup, run for real: `"typescript": "npm:@typescript/typescript6@^6"` (what every `import 'typescript'` resolves, eslint and hey-api included) plus `"@typescript/native": "npm:typescript@^7"` (which owns the `tsc` binary); remove the alias at TS 7.1. What the 6.0 step surfaces in practice: `baseUrl` is deprecated (anchor `paths` on `./`), and side-effect imports get type-checked (TS2882), so an extensionless CSS subpath import needs its own `.d.ts`.

> All of these are bundled in the global `/quality` skill, which auto-detects the project type. For the backend quality tools (PHPStan, PHP-CS-Fixer, Doctrine, Psalm), see `docs/symfony-guidelines.md` section 14.

### Pre-commit: husky + lint-staged

The universal frontend guardrail: ESLint + Prettier run automatically on staged `*.{ts,tsx}` files before every commit. `tsc --noEmit` and drift detection on `openapi.yaml` / `assets/lib/api/` run on top, at project level. The setup is shared with the backend (PHP-CS-Fixer, PHPStan, `lint:container`, `schema:validate`) in a single config. Details and timings in `docs/symfony-guidelines.md`, Quality Assurance section.

In an AI-assisted dev session, run `/quality` before calling a task done whenever code changed: the pre-commit hook is the final net, not the first resort.

---

## 9. Tests
The standard stack, the same on every Symfony + React project, with no "light" or "heavy" variant.

| Layer | Tool | Role |
|---|---|---|
| Unit / component | **Vitest 5** + `@testing-library/react` + `@testing-library/user-event` (jsdom) | Pure logic and isolated components |
| SDK / API mocking | **MSW 3** (Mock Service Worker) | Intercepts the generated SDK's network calls; the mocks are reusable in Storybook and dev |
| E2E / journeys | **Playwright** | Covers multi-page flows (funnel, payment, signature) in a real browser |
| Accessibility | **`@axe-core/playwright`** | WCAG audit inside every E2E spec |

```bash
pnpm test                # Vitest, full run
pnpm test --watch        # watch mode
pnpm test:e2e            # Playwright
pnpm test:e2e:ui         # Playwright in interactive UI mode
```

> **Vitest 5** (stable since September 2026) requires Node ≥ 22.12 and takes `vite` as a peer dependency. What bites when migrating from 4:
> - `clearMocks: true` is the default: call counts reset before every test, so a test that counted calls made by a previous one now fails.
> - An un-`await`ed async assertion (`resolves`, `rejects`) fails the test instead of passing silently.
> - `vi.mock`, `vi.unmock` and `vi.hoisted` throw when they are not at the top level of the file.
> - `test.sequential` and `describe.sequential` are gone: `concurrent: false`.
> - The config is no longer looked up in parent directories, and reports move to `.vitest/` (add it to `.gitignore`).
> - In Browser Mode only, `toHaveTextContent` becomes a strict equality (`toMatchTextContent` for partial or regex matching) and locators are exact by default. The jest-dom matcher under jsdom does not change.
>
> A project staying on 4 for now needs **≥ 4.1.11**: earlier versions let the `@vitest/mocker` redirect read arbitrary files (GHSA-82fw-gwwq-j7x9).

### What to test, by return on investment

The order is in `react/react-guidelines.md` §5. On this stack, forms are tested with the SDK mocked through MSW (setup below), and "fragile" means a multi-step wizard or a component over 500 lines.

### Vitest setup

`vitest.config.ts` at the root, sharing the Vite config:

```ts
import {defineConfig, mergeConfig} from 'vitest/config';
import viteConfig from './vite.config';

export default mergeConfig(viteConfig, defineConfig({
    test: {
        environment: 'jsdom',
        globals: true,                    // describe / it / expect available without imports
        setupFiles: ['./assets/test-setup.ts'],
        css: false,                       // no need to parse the CSS
    },
}));
```

`assets/test-setup.ts`:

```ts
import '@testing-library/jest-dom/vitest';
import {cleanup} from '@testing-library/react';
import {afterEach, beforeAll, afterAll} from 'vitest';
import {server} from './test-mocks/server';

afterEach(() => cleanup());

// MSW: intercepts every SDK request during the tests
beforeAll(() => server.listen({onUnhandledFrame: 'error'}));
afterEach(() => server.resetHandlers());
afterAll(() => server.close());

// Clean override of navigator.language without breaking userAgent
Object.defineProperty(window.navigator, 'language', {value: 'fr-FR', configurable: true});

// window.location.reload / assign are non-writable in jsdom: patch through the prototype
beforeAll(() => {
    const proto = Object.getPrototypeOf(window.location);
    proto.reload = vi.fn();
    proto.assign = vi.fn();
});
```

### MSW setup: mocking the generated SDK

MSW intercepts at the network level, so the hey-api functions are still called for real. The big advantage over `vi.mock('@/lib/api')`: the mocks are defined once and shared between tests, Storybook and dev.

**MSW 3** (September 2026) is ESM-only and requires Node ≥ 22.12 and TypeScript ≥ 5.9. `http`, `HttpResponse` and `setupServer` keep their names; what changes in a Vitest suite:
- `onUnhandledRequest` becomes **`onUnhandledFrame`**, with no alias: TypeScript rejects the old key, and at runtime it is ignored, so unhandled requests fall back to `'warn'`.
- In a handler, `request.headers.get('cookie')` returns `null`: read the `cookies` argument of the resolver.
- MSW no longer patches `setTimeout` under fake timers: a `delay()` response needs the timers advanced (`vi.advanceTimersByTimeAsync`).

`assets/test-mocks/server.ts`:

```ts
import {setupServer} from 'msw/node';
import {handlers} from './handlers';

export const server = setupServer(...handlers);
```

`assets/test-mocks/handlers.ts`: default handlers, overridden per test when needed:

```ts
import {http, HttpResponse} from 'msw';

export const handlers = [
    http.post('/api/tunnel/validate/shares', () =>
        HttpResponse.json({status: 'identity'}),
    ),
    http.post('/api/beneficiary', async ({request}) => {
        const body = await request.json();
        return HttpResponse.json({id: 1, ...body}, {status: 201});
    }),
];
```

Per-test override:

```tsx
import {server} from '@/test-mocks/server';
import {http, HttpResponse} from 'msw';

it('shows 422 violations', async () => {
    server.use(http.post('/api/beneficiary', () =>
        HttpResponse.json({violations: [{propertyPath: 'firstName', title: 'Required'}]}, {status: 422}),
    ));
    // … render and assert
});
```

### TanStack Query wrapper: a throwaway `QueryClient`

Every component using `useMutation` / `useQuery` has to be rendered inside a `QueryClientProvider`. Write a helper:
```tsx
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {render} from '@testing-library/react';

export function renderWithQueryClient(ui: ReactElement) {
    const qc = new QueryClient({
        defaultOptions: {
            mutations: {retry: false},
            queries: {retry: false, gcTime: 0},
        },
    });
    return render(<QueryClientProvider client={qc}>{ui}</QueryClientProvider>);
}
```

`retry: false` + `gcTime: 0` give you immediate errors and no cache bleeding between tests.

### Vitest + React 19 + shadcn gotchas

**Portal-based primitives (Select, Dialog, Popover) are fragile in jsdom.** Portals, pointer events and focus traps all misbehave without a real layout engine. Two options:

1. **Mock locally**, turning the shadcn Select into a native `<select>` when the test only needs the `onValueChange` contract:
    ```tsx
    vi.mock('@/components/ui/select', () => {
        const React = require('react');
        const Ctx = React.createContext({});
        return {
            Select: ({value, onValueChange, children}: any) => (
                <Ctx.Provider value={{value, onValueChange}}>{children}</Ctx.Provider>
            ),
            SelectTrigger: () => {
                const {value, onValueChange} = React.useContext(Ctx);
                return <select role="combobox" value={value ?? ''}
                               onChange={(e) => onValueChange?.(e.target.value)} />;
            },
            SelectValue: () => null,
            SelectContent: ({children}: any) => <div style={{display: 'none'}}>{children}</div>,
            SelectItem: ({value, children}: any) => /* injects an <option> */,
        };
    });
    ```

2. **Switch to Vitest Browser Mode** (stable since Vitest 4) for the specs that genuinely interact with Select / Dialog / Popover. The provider is a separate package (`pnpm add -D @vitest/browser-playwright`) and is passed as a **function**:
    ```ts
    import {playwright} from '@vitest/browser-playwright';

    test: {
        browser: {
            enabled: true,
            provider: playwright(),
            instances: [{browser: 'chromium'}],
        },
    },
    ```
    Faster to write than a complex mock, slower to run (a real browser). Use it case by case. jsdom stays the default for light unit tests.

**The shadcn `<Label>` is not wired through `htmlFor`.** `getByLabelText(/Nom/)` won't resolve. Helper:

```ts
function inputByLabel(labelText: RegExp): HTMLInputElement {
    for (const label of screen.getAllByText(labelText)) {
        const input = label.closest('div')?.querySelector('input, textarea');
        if (input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement) {
            return input as HTMLInputElement;
        }
    }
    throw new Error(`No input found for label ${labelText}`);
}
```

### E2E with Playwright

Full details in `symfony-guidelines.md` §13 (Playwright shares the backend's DB infrastructure: the `app:e2e:seed` command, a pre-logged `storageState`, and so on). On the React side, what to know:

1. **One spec per user journey**, not per page. The granularity is "what a user is trying to do". So `checkout.spec.ts`, not `step-amount.spec.ts` + `step-identity.spec.ts`.
2. **Semantic locators**: `page.getByRole('button', {name: /valider/i})` rather than `page.locator('.btn-submit')`. It survives Tailwind refactors.
3. **shadcn forms**: `await page.getByLabel(/Nom/i).fill('Dupont')`. If the `Label` isn't wired, fall back to `getByPlaceholder` or `getByRole('textbox', {name: ...})`.
4. **Inline a11y in every spec**: the `AxeBuilder` check shown in `symfony-guidelines.md` §13, plus structure with `await expect(page).toMatchAriaSnapshot()` (full-page aria snapshots since Playwright 1.61).

### What is NOT worth testing on the frontend

The list is in `react/react-guidelines.md` §5; the SDK-and-display case is covered by the functional PHPUnit contract test. Assert with `toHaveTextContent` + `toBeVisible` rather than a snapshot, and add a test because the component's complexity justifies it, not by reflex.

---

## 10. Forbidden anti-patterns

Hard rules on the frontend. If you find them in existing code, that code is to refactor, not to copy.

**Data fetching / mutations**
- `useEffect(() => { fetch('/api/...') })` + `useState`: use `useQuery` with the queryOptions hey-api generates
- A bare `fetch()`: use the generated SDK functions (`postX`, `getY`…)
- A mutation without `handleSdkError`: the 422s are silently lost for the user
- `new QueryClient()` inside a component: import the shared `queryClient`
- A component mounted from Twig that uses `useQuery` / `useMutation` without a `<QueryClientProvider>` wrapper

**Forms**
- `useState` for the state of a multi-field form with validation: use RHF + Zod
- The shadcn `<Form>/<FormField>/<FormMessage>` for **new** code: legacy pattern, use `Controller` + the `Field` family (`data-invalid`, `<FieldError>`)
- A hand-written Zod schema for an API payload: import it from `zod.gen`
- A bespoke 422 catch: use `handleSdkError` + a per-field `form.setError`
- An auth form (login, registration, password) in React: keep it in Twig + Symfony Form
- A React form that manipulates the entity directly instead of a derived payload: go through a backend DTO when the form edits a subset of fields

**Upload**
- A file upload with no `file.size` guard on the frontend: PHP's SAPI drops silently past `upload_max_filesize`, the resolver (`#[MapRequestPayload]` DTO or `MapUploadedFile`) returns an empty 422 and the toast stays mute. Frontend limit mirrors the backend one (`Assert\File(maxSize)`)
- A hand-rolled `FormData` upload: the SDK handles multipart through `formDataBodySerializer`

**Typing**
- `any`, with one tolerated exception: `form.setError(field as any, ...)` (the documented RHF workaround for `Object.entries()` typing)
- Props typed inline without an `interface`: declare an `interface Props` in the same file
- `as any` on a payload sent to the SDK: shape the form state to match the generated type, or cast to it

**React 19 / React Compiler**
- `useMemo` / `useCallback` / `React.memo` without concrete profiling: the React Compiler places them, adding them by hand is noise (and they become no-ops)
- `forwardRef`: React 19 takes `ref` as a plain prop
- `useEffect` to derive state from other state: compute during render
- `watch()` or reading the `formState` proxy at render with the compiler on: use `useWatch` / `useFormState({ control })` / `useController` (the `incompatible-library` lint rule)

**Styling / UI kit**
- A raw `<button>` for an **action** or a **link**: `<Button>` for the action, `<ButtonLink>` for the link. _NB: a raw `<button>` stays correct for bespoke cases (tile, clickable card, absolute micro-icon, dropzone), and toggles go to `Toggle`/`ToggleGroup`, see `react/react-guidelines.md` §3._
- `className={...ternary...}` inside a template literal: use shadcn's `cn()` for conditional classes
- `alert()` or `window.confirm()`: use sonner's `toast` and shadcn's `Dialog` / `AlertDialog`
- A `lucide-react` icon hand-mounted into a button with a loading state: use the loading state the shadcn component provides

**Imports / structure**
- Relative imports `../../components/...`: use the `@/` alias
- A barrel `index.ts` re-exporting N unrelated components: only for coherent variant sets

**Twig integration**
- A new custom Stimulus controller (state, fetch, logic): that is a React island; Stimulus is only the UX mounting bridge (see section 6)
- Re-enabling Turbo Drive without an explicit decision: it remounts React islands (state lost); the site is deliberately on `data-turbo="false"`

---

## 11. Summary

| What | How |
|------|---------|
| Data at mount | Twig props (`react_component`) + Serializer `#[Groups]` |
| Dynamic data | `useQuery` + auto-generated queryOptions (hey-api + TanStack Query) |
| API → React serialization | Serializer + `#[Groups]` (simple) or a Formatter (complex) |
| Multi-field form | RHF `Controller` + shadcn `Field` + generated Zod + `useMutation` |
| Simple action / inline edit | `useMutation` + SDK + `handleSdkError` + toast |
| Create (POST) | `#[MapRequestPayload]` on the entity, `format: 'json'`, `#[IsGranted]` |
| Update (PUT, few fields) | `#[MapRequestPayload]`, same pattern as POST |
| Update (POST, many fields) | Allowlist DTO + `ObjectMapper` |
| File upload | Flat `#[MapRequestPayload]` DTO + `UploadedFile` + Assert (SF 8.1) + SDK (multipart handled) |
| Delete (DELETE) | `useMutation` + `DELETE` + `invalidateQueries` |
| Filtered read (GET) | `#[MapQueryString]` on a filter DTO |
| 422 errors | `handleSdkError` + per-field `form.setError()` |
| 403/404/500 errors | `form.setError("root", ...)` + a global message |
| Group naming | `entity:read`, `entity:create`, `entity:update` |
| TS types + Zod v4 + SDK + queryOptions/mutationOptions | Generated by `make types` → `assets/lib/api/` |
| Auth / security | Twig + Symfony Form (not React) |
| API route security | `#[IsGranted('ROLE_USER')]` on the method or class |
| Frontend infra | Vite + Symfony Reprise + Symfony UX React |
| Mounting a component | `react_component()` in Twig |
| Package manager | pnpm |
