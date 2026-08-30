# Scan axes: Symfony + React

Read this file only when auditing a Symfony + React project.

### Symfony backend (`src/`)

- **Domain/ vs Service/**: any class in `Domain/` injecting a repository,
  EntityManager, API client, logger or any infrastructure dependency. Domain/ must be
  pure (Entity plus other Domain). Framework attributes (`Assert`, `OA`, `Groups`)
  are allowed and are not a finding.
- **Enums**: abstract classes with `public const` that should be a PHP `enum`. Naming
  (singular). Doctrine columns that should use `enumType:`.
- **Controllers**: `/api/` routes missing `format: 'json'`; `/api/` routes missing
  `#[IsGranted]`; `$request->get()` (removed in Symfony 8.0); `getRepository()` calls
  instead of an injected typed repository; controllers extending `AbstractController`
  and typing `getUser()` as `UserInterface`; inline business logic that belongs in
  Domain/ or Service/. Length alone is not a finding: a long controller of thin
  actions is fine.
- **DTOs**: `MapRequestPayload` on a large entity where an allowlist DTO is due;
  ObjectMapper DTOs carrying a constructor, `= null` or `readonly`, which breaks
  partial mapping.
- **Repositories**: queries living outside `Repository/`.
- **Entities**: missing `Timestampable`; explicit column types the TypedFieldMapper
  infers; mutable `DateTime`; logic that belongs in Domain/.
- **Commands**: `extends Command` with `execute()` instead of the invokable pattern;
  missing `#[AsCommand]`; more than ~100 lines of business logic inline.
- **Services**: constructors without property promotion, missing `readonly`.
- **Twig**: `AbstractExtension` + `getFunctions()` instead of the `#[AsTwigFunction]`
  attributes.
- **HttpClient**: `new RetryableHttpClient()` or a hand-rolled retry loop in a service;
  a public endpoint with no `#[RateLimit]`.
- **Messenger**: a message carrying an entity or an `UploadedFile`; a handler assuming
  the entity still exists; a dispatch before the `flush()`.
- **PHP 8.4**: implicit nullables, opportunities for asymmetric visibility or property
  hooks.

### React frontend (`assets/`), Symfony stack

- **Data fetching**: `useEffect` + `fetch` + `useState` instead of `useQuery` and the
  generated SDK. A bare `fetch()`. A hand-built `FormData` instead of SDK multipart.
- **Errors**: SDK calls with no `handleSdkError`.
- **Forms**: not on RHF + Zod + the shadcn `Field` family; hand-written Zod schemas
  that should come from `zod.gen`; the legacy `<Form>/<FormField>` wrapper in new code.
- **Uploads**: no `file.size` guard on the frontend.
- **Imports**: relative `../../` instead of `@/`.
- **Typing**: `any` outside the documented `form.setError` exception; untyped props.
- **QueryClient**: a `new QueryClient()` inside a component; a Twig-mounted component
  with no `<QueryClientProvider>`.
- **React 19**: `forwardRef`; `useMemo`/`useCallback` added by hand under the compiler;
  `watch()` or a render-time read of the `formState` proxy.
- **Stimulus and Turbo**: a new custom Stimulus controller carrying state or fetching;
  Turbo Drive re-enabled.
