# Reactony: the Symfony + React convention

> One source of truth, no duplication, one pattern.
>
> **Last watch: 30 August 2026** (`/gap-sota`), start from this date on the next run. Reference versions verified: React 19.2.8 · React Compiler 1.0 (native plugin-react 6.1 path, see §7) · @vitejs/plugin-react 6.1 / Vite 8.2 (Rolldown) · Symfony Reprise 1.1 (see §6) · symfony/ux 3.4 (2.x line still maintained) · TanStack Query 5.102 · RHF 7.87 (v8 still in beta) · Zod 4.5 · @hey-api/openapi-ts 0.99 (exact pin) · Tailwind 4.3 · shadcn (`Field` family; CLI 4.19) · Vitest 4.1 (v5 in RC, see §9) · MSW 2.15 · Playwright 1.62 · eslint-plugin-react-hooks 7.1 · TypeScript 7 (native, GA, see §8).

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

What `queryOptions()` buys you: the definition is shareable between `useQuery`, `queryClient.invalidateQueries`, `queryClient.prefetchQuery` and the rest, with type safety preserved.

On the Symfony side, filters are typed with `#[MapQueryString]` on a **DTO** (the one case where a DTO is justified: GET filters are not an entity):

```php
#[Route('/api/farms', methods: ['GET'], format: 'json')]
public function list(#[MapQueryString] FarmFilterDto $filters = new FarmFilterDto()): JsonResponse
{
    return $this->json($this->farmRepository->findByFilters($filters));
}
```

### Serialization (API → React)

By default, use the **Symfony Serializer + `#[Groups]`**:

```php
// Simple: the Serializer handles everything
return $this->json($adverts, context: ['groups' => ['advert:read']]);
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
// src/Dto/SaveProfilePayload.php: explicit allowlist
use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: User::class)]
class SaveProfilePayload
{
    public ?string $firstName;          // Uninitialized if absent from the JSON, so ignored
    public ?string $lastName;
    public ?string $phone;
    // ... allowed fields only
}
```

**Important**: no constructor, no `= null`, no `readonly`. The properties stay **uninitialized** when the JSON doesn't carry them, which is what lets the ObjectMapper skip them.

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

Since Symfony 8.1, the default pattern is a **flat DTO** behind `#[MapRequestPayload]` holding both the file and the text fields: one parameter, one validation surface:

```php
class UploadAvatarPayload
{
    public ?string $caption = null;

    #[Assert\NotNull]
    #[Assert\Image(maxSize: '5M')]
    public ?UploadedFile $avatar = null;
}

#[IsGranted('ROLE_USER')]
#[Route('/api/avatar/upload', methods: ['POST'], format: 'json')]
public function uploadAvatar(#[MapRequestPayload] UploadAvatarPayload $payload): Response { /* ... */ }
```

Limits: keep the DTO **flat** (a nested upload payload is a smell, flatten it; the historical bug [#64571](https://github.com/symfony/symfony/issues/64571) that made it actually *break* was **fixed** in June 2026, so the reason is style); identifiers go in the route (`{fieldId}`). `#[MapUploadedFile]` remains the fallback for a lone file:

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

### When to create a DTO

> Full decision tree in `symfony-guidelines.md` section 4.

Auth forms (login, registration, password) stay in **Twig / Symfony Form**: out of scope here.

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

> **Upload gotcha (SAPI drop)**: when PHP drops the upload at the SAPI level (`upload_max_filesize` exceeded), the resolver, `RequestPayloadValueResolver` (flat DTO) as much as `MapUploadedFile`, throws an `HttpException(422)` **with an empty body**, no `violations`. The toast stays mute. Fix: guard `file.size` on the frontend (see the convention above). See also `symfony-guidelines.md` section 4 for the backend.

> **Nullable enum gotcha**: react-hook-form defaults enum selects to `""` when left empty. On the backend, `Enum::from('')` throws a `ValueError`, so a 500. Either the controller coerces `'' → null` before denormalizing (see `symfony-guidelines.md` section 4), or the frontend omits the key. Do both, to be safe.

### Choosing the form library (re-validated June 2026)

`react-hook-form` + `zod` + `@hookform/resolvers` is the confirmed stack. The question comes up often; here is the decision, so it doesn't have to be made twice (re-checked against the web in June 2026: TanStack Form v1 is mature but its server-error mapping is still less clean than `setError`; still no `useActionState` tooling outside Next, so the decision holds):

- **No migration to TanStack Form.** Non-trivial cost (rewriting `handleSdkError`, porting every `setError`), marginal gain given that openapi-ts + Zod already cover end-to-end type safety. RHF stays.
- **No migration to React 19 Actions** (`useActionState`) for forms with structured server validation. Mapping `violations[].propertyPath` to per-field errors isn't native to Actions, and Actions wants to own the `pending`/`error` state that TanStack Query already owns. Awkward double ownership.
- **Yes to `useOptimistic`** for instant-UI mutations (toggle favourite, add to list, reorder). It composes cleanly with RHF + TanStack Query.
- **`useFormStatus`: no, not in this pattern.** It only reports `pending` for a `<form action={...}>` (React Actions). With RHF + `useMutation` (submit through `onSubmit`) it would stay `false` forever. Submission state comes from `mutation.isPending`, or from `useFormState({ control }).isSubmitting` for a deeply nested button.
- **RHF floor: ≥ 7.85** (official `<Activity/>` support, indispensable if a form lives inside a `mode="hidden"` panel); 7.86 adds the type-safe `getErrors` method. v8 (compiler-first rewrite) is still in frozen beta, so "wait for stable" holds (re-checked August 2026).

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

Use it for reversible, non-critical mutations. Not for creating an entity that can visibly fail on the backend.

```tsx
const mutation = useMutation({
  mutationFn: async (values: FormValues) => {
    const result = await postMyEndpoint({ body: values });
    const errors = handleSdkError(result); // null if OK, Record if 422, throws otherwise
    if (errors) {
      Object.entries(errors).forEach(([field, msg]) => form.setError(field as any, { message: msg }));
      throw new Error("Validation failed");
    }
  },
  onError: (error: Error) => {
    if (error.message !== "Validation failed") {
      form.setError("root", { message: "Une erreur est survenue. Réessaie plus tard." });
    }
  },
});
```

In the JSX, render the root error through `useFormState`, not by reading the `form.formState` proxy at render time (React Compiler rule, see section 7):

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

Since October 2025, shadcn **recommends** the agnostic **`Field`** components (`npx shadcn@latest add field`) over the older `<Form>/<FormField>/<FormMessage>` wrapper (an RHF-coupled black box). The old one is **not formally deprecated**, but `Field` is the pattern for anything new. Canonical shape:

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
    { name: "@hey-api/sdk", validator: { response: "zod" } }, // runtime response validation (optional)
    "zod",                   // Zod 4 by default ({ name: "zod", compatibilityVersion: 3 | "mini" } otherwise)
    "@tanstack/react-query", // name of the bundled plugin, NOT an npm package
  ],
});
```

The five plugins:
- `@hey-api/typescript`: TS types from the OpenAPI schema
- `@hey-api/client-fetch`: HTTP client (handles fetch, headers, serialization, **multipart**)
- `@hey-api/sdk`: typed functions per endpoint (`postProfileSave({ body })`); `validator: { response: 'zod' }` also validates responses at runtime using the already-generated schemas (perf cost, opt-in)
- `zod`: Zod 4 schemas for client-side validation
- `@tanstack/react-query`: generates `queryOptions()`, `queryKey` and `mutationOptions()` from the OpenAPI, removing the `lib/queries/` boilerplate

When bumping (the exact pin forces you to), read the [Migrating page](https://heyapi.dev/openapi-ts/migrating). Since 0.93: 0.95 no longer exports composite `Data` schemas (`shouldExtract: true` brings them back), 0.96 requires Node ≥ 22.13, 0.97 actually honours `throwOnError: false`, 0.98 refactors towards a declarative config (mostly affecting custom plugins), 0.99 renames `plugin.symbols` to `plugin.imports` and removes `plugin.external()`/`registerSymbol()` (and merges duplicated plugin configs). As of August 2026: 0.99.0 has been current since June (no 1.0); any project pinned below catches up through the Migrating page. On the backend end of the pipeline, nelmio/api-doc-bundle 5.11 hardens generation for persistent workers and supports the QUERY HTTP method. Zod 4.4 is deliberately stricter, so re-run the Vitest suite when bumping. Zod also ships `z.codec()` (4.1, typed bidirectional transforms, e.g. ISO string ↔ `Date`) and its inverse `z.invertCodec()` (4.4) for hand-written API ↔ domain conversions. Zod 4.5 adds `z.compile(schema)`: same API, parses 3 to 9 times faster, worth putting on the generated schemas you validate at runtime (SDK responses, large form objects).

### SDK: typed API calls

The functions generated into `sdk.gen.ts` give you typed calls per endpoint (`postProfileSave({ body })`), with autocomplete and a TS error when the body is invalid. The SDK also handles **multipart uploads** automatically through `formDataBodySerializer`.

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

React is mounted in Twig through **Symfony UX React** + **Symfony Reprise**. (symfony/ux 3.4 is the active line, with `import.meta.glob()` support in ux-react; it requires PHP 8.4 / Symfony 7.4, and `react_component()` and `registerReactControllerComponents()` are unchanged, so the upgrade from 2.x is mechanical. Gotcha: the npm `latest` dist-tag of `@symfony/ux-react` still points at 2.36, so a default install does not give you 3.x. The 2.x line stays maintained.)

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
### Vite

The Symfony integration is **Symfony Reprise** (`composer require symfony/reprise` + `pnpm add -D @symfony/reprise`), Webpack Encore's official heir for Vite and Rsbuild, under the Symfony backward-compatibility promise. It replaces `pentatrion/vite-bundle` and `vite-plugin-symfony`, whose whole scope it covers. A project still on pentatrion is a gap **to close**: the migration is mechanical (Twig prefix `vite_` → `reprise_`, swap the plugin, import `startStimulusApp` from `@symfony/reprise/stimulus`), and not urgent, pentatrion not being deprecated.

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

`composer remove symfony/webpack-encore-bundle` (or the `update` that drops it) triggers the **unconfigure of its Flex recipe**, and Flex **DELETES the files the recipe owned**: `package.json`, `assets/app.js`, `assets/styles/app.css`, `webpack.config.js`, **and removes `/node_modules/` + `/public/build/` from `.gitignore`** (they live in its `###> … ###` block). **Commit a clean state BEFORE**; **after** the removal: `git status` (look for unexpected `D` plus `grep node_modules public/build .gitignore`), restore with `git checkout <file>` and **re-apply** the lost Vite edits. Deployment stays transparent as long as the build hook runs `pnpm build` (unchanged script, `public/build` output).

### When React, when Stimulus, when Turbo

One interactivity model:

- **A page is static Twig by default. Interactivity is a React island** (`react_component()`), however small: the pipeline (types, SDK, shadcn) makes an island cheaper to maintain than a Stimulus controller living outside that ecosystem.
- **Stimulus is mounting infrastructure only.** The `symfony/ux-react` bridge is itself a Stimulus controller, invisible, and untouched. **Do not write new custom Stimulus controllers**: no state, no fetch, no business logic in Stimulus. Tolerance: stateless micro DOM behaviour (under ~30 lines, copy-to-clipboard say) where an island would be disproportionate. Existing custom controllers are legacy, not a model to copy.
- **Special case, enriching a classic Symfony Form field** (rich editor, datepicker, autocomplete on a server-rendered `<input>`/`<textarea>`): that is a *legitimate* Stimulus use in itself (progressive enhancement, the Symfony UX model). BUT if the React equivalent already exists (a `Wysiwyg` component, say), **reuse it as an island** rather than maintaining a parallel Stimulus controller that duplicates it: mount the React component and have it **sync into the hidden field** (`document.getElementById(targetId).value = ...` on update) so it goes out with the POST. One editor for the whole app, the Symfony field stays the submitted source.
- **Turbo Drive: disabled globally (`<body data-turbo="false">`), on purpose.** Turbo navigation remounts React islands (state lost, double mount). Do not re-enable it without an explicit decision; for navigation polish, the route is native View Transitions (cross-document CSS). `ux-turbo` stays installed for possible Turbo Streams / Mercure use, not for the drive.
- **RSC / React Server Components: we don't do them, on purpose.** Symfony + Twig **is** the server layer already, and the React islands are the intentional client leaves. Bolting RSC on would impose a Node rendering server next to PHP (dual role, broken CleverCloud deployment, extra RSC vulnerability surface) for a problem PHP already solves. `@vitejs/plugin-rsc` exists (2026) but stays experimental and outside Next, irrelevant to the islands-in-Symfony model. Re-open the question only if we dropped server-rendered HTML for a 100% JS frontend (a different architecture, not an evolution of this one).

---

## 7. React conventions

### React 19

React 19 is stable (React 18 is in security support only). Key features:

- **`ref` as a prop**: no more `forwardRef`, pass `ref` directly as a prop (plus cleanup functions on refs)
- **`use()` API**: read promises and context during render
- **`useOptimistic`**: native optimistic updates
- **`<Activity>`**, stable since 19.2: preserve the state of hidden components (`mode="visible|hidden"`)
- **`useEffectEvent`**, stable since 19.2: extract from an Effect the event logic that reads props/state without listing them as dependencies. Never in the deps array (the react-hooks lint enforces it), and declared in the component that owns the Effect
- View Transitions (`<ViewTransition>`): still experimental, not in production. Stabilization is announced for React 19.3 (alongside Fragment refs), but only through a secondary source (a Next.js team AMA, nothing on react.dev), so wait for the official announcement

```tsx
// React 19: ref straight as a prop
const Input = ({ ref, ...props }: { ref?: React.Ref<HTMLInputElement> }) => (
  <input ref={ref} {...props} />
);

// Before React 19: forwardRef required
const Input = forwardRef<HTMLInputElement>((props, ref) => (
  <input ref={ref} {...props} />
));
```

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

**Reading**: `useQuery` + the queryOptions hey-api generates. Never `useEffect` + `fetch()` + `useState`.

```tsx
// Good: queryOptions auto-generated by the @tanstack/react-query plugin (hey-api)
import { getResourceListOptions } from "@/lib/api";
const { data, isLoading } = useQuery({ ...getResourceListOptions({ query: filters }) });

// Bad: no cache, no retry, no invalidation
const [farms, setFarms] = useState([]);
useEffect(() => {
  fetch("/api/farms").then(r => r.json()).then(setFarms);
}, []);
```

**Writing**: `useMutation` + SDK + `handleSdkError`. Invalidate the affected queries in `onSuccess`.

```tsx
const mutation = useMutation({
  mutationFn: async (data: SaveProfilePayload) => {
    const result = await postProfileSave({ body: data });
    const errors = handleSdkError(result);
    if (errors) throw new Error(Object.values(errors)[0]);
  },
  onSuccess: () => {
    toast.success("Enregistré");
    queryClient.invalidateQueries({ ...getProfileOptions() });
  },
  onError: (error: Error) => toast.error(error.message),
});
```

### Forms

| Case | Pattern |
|-----|---------|
| Multi-field form with client validation | RHF `Controller` + shadcn `Field` + generated Zod + `useMutation` |
| Simple action / inline edit / toggle | `useMutation` + SDK + `handleSdkError` + toast |
| Auth form (login, registration, password) | Twig + Symfony Form (not React) |

### CSS classes

Use shadcn's `cn()` for conditional classes, no ternaries inside strings.

```tsx
// Good
<div className={cn("rounded-md border p-4", isActive && "bg-primary text-white")} />

// Bad
<div className={`rounded-md border p-4 ${isActive ? "bg-primary text-white" : ""}`} />
```

### shadcn components

- **Buttons and clickable elements**: pick by intent, the way the shadcn primitives do themselves (their own triggers and closes are styled `<button>` elements, not the `<Button>` component):
  - **Action** (submit, confirm, delete…) → `<Button variant=…>`.
  - **Link** (navigation) → `<Button render={<a href=… />} variant=…>`, so a real `<a>` survives, with the variant choosing the look (`link` for an inline link; `default`/`secondary`/`outline` for a solid button).
  - **Selectable / toggle** (filter pills, multi-select) → `Toggle` / `ToggleGroup`, not `<Button>`.
  - **Bespoke** (image tile, clickable card, absolutely positioned icon micro-control, dropzone) → a raw `<button>` is legitimate: `<Button>` would only add a variant you have to override.
  - Escape hatch `buttonVariants({variant})`: to give the button look to an element you cannot compose through `<Button>` (a third-party `Link` component, say).
- Use the compound components: `Dialog` + `DialogContent` + `DialogHeader`, `Select` + `SelectTrigger` + `SelectContent`, and so on.
- Loading: `<Loader2 className="h-4 w-4 animate-spin" />` from lucide-react
- Notifications: `toast` from sonner (no `alert()`)

### Staying on the latest shadcn (state of the art) and handling updates

shadcn is **not an npm dependency**: the components are **vendored source** in `components/ui/`. So there is no `pnpm update`; you update component by component through the CLI, **preserving local customizations**.

**Base UI, never React Aria or Radix**, whatever the project, in Nova style. Composition goes through the `render` prop and standard DOM handlers. `asChild` exists in neither base, it is a Radix idiom. A project still on `new-york-v4` (Radix) is a gap **to close**, and `/gap-code` should raise it as work to schedule: migrate it in full, never two bases in one project.

> **Migrating a project**: do it cold, on a branch, re-grafting the in-house variants inventoried in the project's `DESIGN-SYSTEM.md`. A stale `style` in `components.json` makes the CLI resolve against the old registry, so new components (the chat primitives `message-scroller`/`message`/`bubble`, for instance) come back **404** even though they exist.

**The inventory of what is customized is PER PROJECT and lives in its `DESIGN-SYSTEM.md`** (provenance section, `variant`/`custom` entries): that file is authoritative, not this one. Reactony is shared across products, so it cannot carry a local inventory without lying to its neighbours. Before updating a component: read the project's inventory, run a full `--diff` to catch whatever it missed, and update it in the same PR.

**Known upstream v4 traps** (library facts, true for every project):
- `PopoverClose` removed from the v4 registry: a project that uses it keeps it locally and marks it `variant`.
- Dialog prop inverted: `hideCloseButton` became `showCloseButton`.
- Button sizes: upstream `xs` moves to `h-6`; `icon-xs`/`icon-sm`/`icon-lg` don't exist in every style.
- **Non-shadcn** components (`multi-select`, `visually-hidden`…): no upstream `--diff`, don't try to "update" them.

Everything else must stay **as close to upstream as possible**: don't edit a `components/ui/*` without a reason, so updates stay clean diffs.

**Update workflow (the CLI IS the update tool)**:
1. `npx shadcn@latest add <component> --diff`: the gap between our local file and upstream for the configured style. **Never fetch the GitHub files by hand.**
2. `npx shadcn@latest add <component> --diff <file>`: the diff file by file.
3. Decide per file: no local change → safe overwrite; local change (our brand variants) → read the local file, apply the upstream updates **re-grafting our additions**.
4. **Never `--overwrite` blindly.** An `add` can pull a registry dependency (`message-scroller` depends on `button`) and try to crush a customized component: decline the overwrite, or re-graft our variants right after.
5. After every `add`, re-read the file and fix the icon imports (the project's own icon library) and the aliases (`@/`). Check the rendering **in the browser** (moving between styles changes shadows, focus rings and sizes).

**CLI / registry news (summer 2026)**: **private** GitHub registries are supported (auth through `gh` credentials or `GH_TOKEN`: if you can read the repo, the CLI can install from it), relevant if a personal kit ever needs to go private. `npx shadcn migrate base-color` switches a project's base color: it rewrites the theme variables in the CSS pointed at by `components.json` plus the `baseColor` value (unrecognized custom tokens are listed at the end of the migration, to handle by hand; reversible by running it the other way or through git). New multi-step `Questionnaire` component.

**Cadence**: at every `/gap-sota` watch, check the current style on ui.shadcn.com and reconcile the components that drifted most (a large `--diff` is a candidate for re-grafting). Target: a near-empty diff outside the documented brand variants.

### QueryClient

The shared `queryClient` (`assets/lib/queryClient.ts`) carries sensible defaults: don't create a `new QueryClient()` inside components.
### Performance: React Compiler

The [React Compiler](https://react.dev/learn/react-compiler) is **enabled**. ⚠️ Since `@vitejs/plugin-react` v6 (Vite 8), the `react({ babel: {...} })` option no longer exists (Oxc transforms) and is **silently ignored**. Two configurations actually run the compiler:

**Native path (plugin-react ≥ 6.1, August 2026)**: the compiler's Rust port, more than 10 times faster than the Babel plugin (~100 ms to ~10 ms per file):

```js
// vite.config.js: pnpm add -D oxc-transform-react (optional peer dep)
import react from "@vitejs/plugin-react";

export default defineConfig({
  plugins: [react({ compiler: true })],
  // options: react({ compiler: { compilationMode: 'annotation' } })
});
```

Still marked **experimental** by the plugin: it is the target, to switch to during a maintenance window, checking that memoization holds (React DevTools Profiler).

**Babel path (the stable one)**:

```js
// vite.config.js
import react, { reactCompilerPreset } from "@vitejs/plugin-react";
import babel from "@rolldown/plugin-babel";

export default defineConfig({
  plugins: [react(), babel({ presets: [reactCompilerPreset()] })],
});
```

(`pnpm add -D -E babel-plugin-react-compiler` + `pnpm add -D @rolldown/plugin-babel @babel/core`). The compiler lint rules ship with `eslint-plugin-react-hooks` ≥ 7 (`configs.flat.recommended`): the `eslint-plugin-react-compiler` package is frozen, don't install it any more.

**What it means for the code you write**:
- Don't add `useMemo` / `useCallback` / `React.memo` "just in case". The compiler places them where they are needed.
- Keep them **only** when:
  - profiling (React DevTools Profiler) shows a specific expensive re-render
  - a compiler rule from the `react-hooks` lint (≥ 7) reports a bail on the component (so the compiler doesn't memoize it, the rare case where an explicit `useMemo` earns its place)
- Existing `useMemo`/`useCallback` in pre-compiler code don't need active removal: they become no-ops (the compiler adds its own on top). Clean them up opportunistically when you touch the file.

**Bailed components (compiler skip)**: the ESLint plugin warns about components that break the Rules of React (side effects in render, writes to `window.*`, refs mutated from an outside callback, `// eslint-disable-next-line react-hooks/exhaustive-deps`). Those components work correctly but get no auto-memoization. Not blocking; fix case by case if profiling calls for it.

**Golden rule**: write the simplest React you can. The compiler optimizes.

**React Compiler × react-hook-form (v7)**: the `formState` proxy and `watch()` rely on internal mutability that the compiler memoizes wrongly (the lint's `incompatible-library` rule flags them). With the compiler on:

- ❌ `watch('field')` and reading `form.formState.X` at render; don't pass `formState` as a prop
- ✅ `useWatch({ control, name })`, `useFormState({ control })`, `useController` / `<Controller>` (explicit subscriptions); `getValues()` reserved for handlers and effects
- Transitional escape hatch: a `'use no memo'` directive on a problematic form component

RHF v8 (the compiler-first rewrite) is in beta: don't adopt it before stable.

---

## 8. Quality Assurance: frontend

### ESLint

Lints the TypeScript/React code. Checks hook rules, React patterns, types.

```bash
pnpm lint          # check
pnpm lint:fix      # auto-fix
```

Flat config (`eslint.config.js`) with:
- `@eslint/js` + `typescript-eslint`: TS rules
- `eslint-plugin-react-hooks` ≥ 7 through `configs.flat.recommended`: hook rules **and React Compiler rules** (they entered the standard `recommended` in **v7.0**; v6 only exposed them behind the opt-in `recommended-latest`; `eslint-plugin-react-compiler` is obsolete)
- `eslint-config-prettier`: disables the rules that conflict with Prettier

### Prettier

Formats the code (indentation, quotes, trailing commas, Tailwind class sorting).

```bash
pnpm format        # format
pnpm format:check  # check without writing
```

Config (`.prettierrc`) with `prettier-plugin-tailwindcss` for automatic class sorting (≥ 0.8 requires Prettier ≥ 3.7; sorting inside **Twig templates** has existed since 0.6, and 0.7 extends it to Twig **function calls**: turn it on for `templates/`).

### TypeScript strict

`tsc --noEmit` checks types without emitting files. It catches type errors ESLint can't see.

```bash
pnpm tsc --noEmit
```

**TypeScript 7 has been GA since July 2026**: the native Go port, published under the standard `typescript` npm package, `tsc` binary unchanged, checks 7 to 12 times faster, language server moved to LSP. Migrate from `^5.9` in two steps: **5.9 → 6.0** (adopt the new defaults and purge the deprecated flags, which 7.0 turns into hard errors) **→ 7.0**. Caveat: no programmatic API before TS 7.1; typescript-eslint (≥ 8.67) goes through the `@typescript/typescript6` shim, which doesn't block pure React/TSX.

### Summary

| Tool | Role | When |
|-------|------|------|
| ESLint | Lints JS/TS/React, hook rules | `/quality` |
| Prettier | Formatting, Tailwind class sorting | `/quality` |
| `tsc --noEmit` | Type checking | `/quality` |

> All of these are bundled in the global `/quality` skill, which auto-detects the project type. For the backend quality tools (PHPStan, PHP-CS-Fixer, Doctrine, Psalm), see `docs/symfony-guidelines.md` section 14.

### Pre-commit: husky + lint-staged

The universal frontend guardrail: ESLint + Prettier run automatically on staged `*.{ts,tsx}` files before every commit. `tsc --noEmit` and drift detection on `openapi.yaml` / `assets/lib/api/` run on top, at project level. The setup is shared with the backend (PHP-CS-Fixer, PHPStan, `lint:container`, `schema:validate`) in a single config. Details and timings in `docs/symfony-guidelines.md`, Quality Assurance section.

In an AI-assisted dev session, run `/quality` before calling a task done whenever code changed: the pre-commit hook is the final net, not the first resort.

---

## 9. Tests
The standard stack, shared by every Symfony + React project. No "light" or "heavy" variant: the same thing everywhere, so a dev moving between projects learns it once.

| Layer | Tool | Role |
|---|---|---|
| Unit / component | **Vitest 4** + `@testing-library/react` + `@testing-library/user-event` (jsdom) | Pure logic and isolated components |
| SDK / API mocking | **MSW 2** (Mock Service Worker) | Intercepts the generated SDK's network calls; the mocks are reusable in Storybook and dev |
| E2E / journeys | **Playwright** | Covers multi-page flows (funnel, payment, signature) in a real browser |
| Accessibility | **`@axe-core/playwright`** | WCAG audit inside every E2E spec |

```bash
pnpm test                # Vitest, full run
pnpm test --watch        # watch mode
pnpm test:e2e            # Playwright
pnpm test:e2e:ui         # Playwright in interactive UI mode
```

> **Why Vitest and not Jest**: the project runs on Vite, so Vitest shares the same config (the `@/` alias, plugins, TS/TSX transformers). Native ESM means `lucide-react`, the hey-api SDK and other ESM modules work without `transformIgnorePatterns`. React 19 compatible, 5 to 28 times faster than Jest depending on the suite. The API is near-identical: `vi` instead of `jest`, `vi.mock()` hoisted like `jest.mock()`, the same matchers through `@testing-library/jest-dom` (Vitest compatible).

> **Vitest 5 is in RC** (August 2026): don't adopt before stable, but write code today that will survive it. Announced breaking changes: `clearMocks: true` becomes the default, an un-`await`ed async assertion fails the test, `toHaveTextContent` becomes a strict equality (partial matching moves to `toMatchTextContent`), Node ≥ 22.12 required (Node 24 is the Active LTS as of August 2026).

### What to test, by return on investment

1. **Pure functions** (business calculations, domain helpers, formatters, Zod transforms). No mocks, no DOM. Bugs here shift the numbers shown to the customer: visible, and expensive.
2. **Form components** with Zod / RHF validation. Hit every invalid field, the happy path, and the server-side 422s. Mock the SDK through MSW.
3. **E2E journeys** for the critical flows: the full purchase or subscription funnel, login, a signature flow. **One journey, one Playwright spec.**
4. **Complex components before a refactor** (multi-step wizards, components over 500 lines). Write the tests that pin the **current** visible behaviour before changing the internals.
5. **The rest: skip.** A presentational component passing 3 props to 3 shadcn children needs no test. TypeScript and ESLint are enough.

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
beforeAll(() => server.listen({onUnhandledRequest: 'error'}));
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

### Safety net first, before a big refactor

Before migrating a large, fragile component (over 500 lines, `useState` → RHF + Zod say), first write the tests that pin its **current** visible behaviour: happy path, guards, server errors. Then refactor, keeping the suite green. If a test breaks, that is a real behaviour change: either intentional, or a regression.

### Vitest + React 19 + shadcn gotchas

Each of these costs 30 minutes to an hour to diagnose the first time. Vitest + ESM removes several (lucide, the hey-api SDK), but the rest remain.

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

**Always prefer MSW over `vi.mock('@/lib/api')`.** Module mocking works, but MSW intercepts at the right level (the network) and stays consistent when you move to E2E. If a test really does nothing but `vi.mock` the SDK, that is a sign it should be a pure-function test, not a component test.

### E2E with Playwright

Full details in `symfony-guidelines.md` §13 (Playwright shares the backend's DB infrastructure: the `app:e2e:seed` command, a pre-logged `storageState`, and so on). On the React side, what to know:

1. **One spec per user journey**, not per page. The granularity is "what a user is trying to do". So `checkout.spec.ts`, not `step-amount.spec.ts` + `step-identity.spec.ts`.
2. **Semantic locators**: `page.getByRole('button', {name: /valider/i})` rather than `page.locator('.btn-submit')`. It survives Tailwind refactors.
3. **shadcn forms**: `await page.getByLabel(/Nom/i).fill('Dupont')`. If the `Label` isn't wired, fall back to `getByPlaceholder` or `getByRole('textbox', {name: ...})`.
4. **Inline a11y in every spec** (plus structure: `await expect(page).toMatchAriaSnapshot()`, full-page aria snapshots since Playwright 1.61):
    ```ts
    import AxeBuilder from '@axe-core/playwright';

    test('the amount step is accessible', async ({page}) => {
        await page.goto('/tunnel');
        const results = await new AxeBuilder({page}).analyze();
        expect(results.violations).toEqual([]);
    });
    ```

### What is NOT worth testing on the frontend

- A component that only calls an SDK and displays the result: the backend contract test (functional PHPUnit) already covers the API contract, TypeScript covers the typing, ESLint the structure.
- Full-render snapshot tests: they break on any Tailwind class change and carry no useful signal. Prefer `toHaveTextContent` + `toBeVisible`.
- UI kit components (shadcn passthrough).
- Testing a component's rendering just because you happen to be in the file. Add a test because the component's *complexity* justifies it, not by reflex.

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
- A raw `<button>` for an **action** or a **link**: use `<Button>` (a variant for the action, composition with a real `<a>` for the link). _NB: a raw `<button>` stays correct for bespoke cases (tile, clickable card, absolute micro-icon, dropzone), and toggles go to `Toggle`/`ToggleGroup`, see the "shadcn components" section._
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
