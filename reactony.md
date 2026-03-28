# Reactony — Convention Symfony + React

> Source de vérité unique, pas de duplication, un seul pattern.

## Principes

1. **Le PHP est la source de vérité** — types et validation vivent sur l'entité
2. **Types et Zod v4 générés** — depuis les `#[Assert\...]` PHP, jamais écrits manuellement
3. **Un seul pattern formulaire** — shadcn Form + RHF + Zod + `useMutation` (actions simples : `useMutation` + toast, cf. section 4)
4. **Entité ou DTO** — entité directe quand le payload correspond 1:1 ; DTO quand c'est un sous-ensemble d'une entité large (sécurité : allowlist, mapping via `ObjectMapper`) ou quand le payload n'a pas d'entité correspondante
5. **Auth et sécurité en Twig** — login, inscription, mot de passe restent des formulaires Symfony classiques
6. **React = Reactony, Twig = Symfony Form** — si la page est en React et dynamique, le formulaire suit Reactony (shadcn + RHF + Zod + `useMutation`). Si la page est en Twig sans React et le formulaire est simple (pas de dynamisme), un Symfony Form classique suffit
7. **SDK partout** — toujours utiliser les fonctions SDK générées, y compris pour les uploads (le SDK gère le multipart via `formDataBodySerializer`)
8. **pnpm** — gestionnaire de paquets pour le front

---

## 1. Lecture : Symfony → React

### Au mount — props Twig

```twig
<div {{ react_component('MonComposant', {
    farm: farm|serialize('json', { groups: ['farm:read'] }),
    departments: getDepartments(),
}) }}></div>
```

### Dynamique (filtres, pagination) — TanStack Query

Les `queryOptions()` sont **auto-générés** par le plugin `@hey-api/tanstack-react-query` (cf. section 5). Pas besoin de les écrire manuellement :

```tsx
// Importé directement depuis le code généré
import { getResourceListOptions } from "@/lib/api";

const { data, isLoading } = useQuery({
  ...getResourceListOptions({ query: filters }),
});
```

L'avantage de `queryOptions()` : la définition est partageable entre `useQuery`, `queryClient.invalidateQueries`, `queryClient.prefetchQuery`, etc., avec le type-safety préservé.

Côté Symfony, les filtres sont typés avec `#[MapQueryString]` sur un **DTO** (seul cas où un DTO est justifié — les filtres GET ne sont pas une entité) :

```php
#[Route('/api/farms', methods: ['GET'], format: 'json')]
public function list(#[MapQueryString] FarmFilterDto $filters = new FarmFilterDto()): JsonResponse
{
    return $this->json($this->farmRepository->findByFilters($filters));
}
```

### Sérialisation (API → React)

Par défaut, utiliser le **Serializer Symfony + `#[Groups]`** :

```php
// Simple : le Serializer gère tout
return $this->json($adverts, context: ['groups' => ['advert:read']]);
```

Les `#[Groups]` sur l'entité contrôlent ce qui est exposé :

```php
#[ORM\Column]
#[Groups(['advert:read'])]
private string $title;

#[ORM\ManyToOne]
private ?User $user = null;  // pas de Group → jamais exposé
```

Pour les cas complexes (URLs S3 calculées, données croisées multi-entités), utiliser un **Formatter** dans `Service/` (cf. symfony-guidelines.md).

---

## 2. Écriture : React → Symfony

### Symfony : `#[MapRequestPayload]` sur l'entité

> Conventions détaillées des DTOs, `#[MapRequestPayload]`, `#[MapUploadedFile]`, et `ObjectMapper` : voir `symfony-guidelines.md` section 4.

Quand le payload correspond 1:1 à l'entité, on utilise directement l'entité. Les `#[Groups]` ne sont nécessaires que si l'entité a des champs qu'on ne veut pas exposer (relations, flags internes).

```php
class SearchFarmNotification
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;  // private sans Group → ignoré par le Serializer

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

`format: 'json'` est **obligatoire** — sans ça, les erreurs 422 sont en HTML.

`#[IsGranted('ROLE_USER')]` est **obligatoire** sur les routes `/api/` — pas de protection par `access_control` URL pattern.

### Modification (PUT)

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

Côté React — même pattern que POST, juste la méthode qui change :

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
  onSuccess: () => queryClient.invalidateQueries({ queryKey: ["alerts"] }),
});
```

> **Note** : `field as any` est un workaround pour une limitation de typage de React Hook Form — `Object.entries()` retourne `string[]` au lieu du type union des champs. C'est le seul `as any` accepté dans le pattern.

### Modification lourde (update partiel, beaucoup de champs)

> Pattern DTO allowlist + `ObjectMapper` détaillé dans `symfony-guidelines.md` section 4.

Quand le payload met à jour beaucoup de champs sur une entité existante (ex. profil User avec 14 champs), `#[MapRequestPayload]` ne suffit pas car il crée une **nouvelle instance**.

Le DTO sert de **liste blanche de champs acceptés** — sans lui, un mapping direct permettrait d'envoyer `{ "roles": ["ROLE_ADMIN"] }`. L'ObjectMapper ne mappe que les propriétés **initialisées** du DTO (les champs absents du JSON restent non initialisés → ignorés).

```php
// src/Dto/SaveProfilePayload.php — allowlist explicite
use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: User::class)]
class SaveProfilePayload
{
    public ?string $firstName;          // Non initialisé si absent du JSON → ignoré
    public ?string $lastName;
    public ?string $phone;
    // ... seuls les champs autorisés
}
```

**Important** : pas de constructor, pas de `= null`, pas de `readonly`. Les propriétés restent **non initialisées** quand le JSON ne les contient pas, ce qui permet à l'ObjectMapper de les ignorer.

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

> **Quand utiliser quoi ?**
> - Peu de champs / entité dédiée → `#[MapRequestPayload]` sur l'entité + copie manuelle (cf. PUT ci-dessus)
> - Beaucoup de champs / entité existante → DTO allowlist + `ObjectMapper`
> - Payload ≠ entité (champs calculés, agrégats, pas d'entité correspondante) → DTO dans `src/Dto/`

### Upload de fichiers — `#[MapUploadedFile]`

> Conventions backend upload détaillées dans `symfony-guidelines.md` section 4.

Pour les endpoints recevant un fichier (FormData), utiliser `#[MapUploadedFile]` au lieu de `$request->files->get()`. La validation du fichier est gérée par les contraintes Assert :

```php
#[IsGranted('ROLE_USER')]
#[Route('/api/avatar/upload', methods: ['POST'], format: 'json')]
public function uploadAvatar(
    #[MapUploadedFile(name: 'avatar', constraints: [new Assert\NotNull(), new Assert\File(mimeTypes: ['image/*'])])]
    UploadedFile $file,
    EntityManagerInterface $entityManager,
): Response {
    // ...
}
```

Si l'endpoint a aussi des données texte en plus du fichier :

1. **Identifiant** → paramètre de route (le plus propre) :

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

2. **Plusieurs champs texte** → `#[MapRequestPayload]` sur un paramètre séparé (fonctionne avec multipart car le resolver lit `$request->request->all()`) :

```php
public function upload(
    #[MapRequestPayload] UploadMetadataPayload $metadata,
    #[MapUploadedFile(name: 'file', constraints: [...])] UploadedFile $file,
): Response { /* ... */ }
```

> **Symfony 8.1+** : `#[MapRequestPayload]` supportera nativement `UploadedFile` dans le DTO ([RFC #60440](https://github.com/symfony/symfony/issues/60440)), permettant un seul paramètre pour fichier + champs texte. En attendant, on sépare les deux.

Côté React, le SDK gère automatiquement les uploads multipart via `formDataBodySerializer`. Utiliser le SDK comme pour les autres appels :

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

### Suppression (DELETE)

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

Côté React :

```tsx
const deleteMutation = useMutation({
  mutationFn: async () => {
    const result = await deleteAlert();
    handleSdkError(result);
  },
  onSuccess: () => queryClient.invalidateQueries({ queryKey: ["alerts"] }),
});
```

### Convention `#[Groups]`

Format : `entité:action` en minuscules.

| Usage | Nom du group | Exemple |
|-------|-------------|---------|
| Lecture (sérialisation) | `entité:read` | `advert:read`, `farm:read` |
| Création (désérialisation) | `entité:create` | `alert:create` |
| Modification (désérialisation) | `entité:update` | `alert:update` |

Ajouter des Groups **seulement si nécessaire** — quand l'entité a des champs qu'on veut exclure (relations, flags internes). Pour une entité simple, pas besoin.

```php
// Seulement si nécessaire
#[MapRequestPayload(serializationContext: ['groups' => ['alert:create']])]
```

### Quand créer un DTO ?

> Arbre de décision complet dans `symfony-guidelines.md` section 4.

Les formulaires d'auth (login, inscription, mot de passe) restent en **Twig/Symfony Form** — pas concernés.

---

## 3. Erreurs 422

Symfony renvoie automatiquement :

```json
{
  "type": "https://symfony.com/errors/validation",
  "title": "Validation Failed",
  "violations": [
    { "propertyPath": "canals", "title": "Choisis au moins un canal." }
  ]
}
```

`handleSdkError` (`lib/parseViolations.ts`) gère les deux cas :
- **422** → retourne `Record<string, string>` (erreurs par champ, parsées depuis `violations`)
- **Autre erreur (403, 500...)** → `throw new Error(...)` (catchée par `onError`)
- **Pas d'erreur** → retourne `null`

```tsx
const mutation = useMutation({
  mutationFn: async (values: FormValues) => {
    const result = await postMyEndpoint({ body: values });
    const errors = handleSdkError(result); // null si OK, Record si 422, throw sinon
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

Dans le JSX, afficher l'erreur root :

```tsx
{form.formState.errors.root && (
  <p className="text-sm text-destructive">{form.formState.errors.root.message}</p>
)}
```

---

## 4. Formulaire React

### Formulaire multi-champs — RHF + Zod + shadcn Form

Pour un formulaire avec plusieurs champs et validation côté client : **shadcn Form + React Hook Form + Zod généré + `useMutation`**.

```tsx
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation } from "@tanstack/react-query";
import { z } from "zod/v4";
import { zSearchFarmNotification } from "@/lib/api/zod.gen"; // généré (cf. section 5)
import { postAlert } from "@/lib/api";
import { handleSdkError } from "@/lib/parseViolations";
import { Form, FormField, FormItem, FormLabel, FormControl, FormMessage } from "@/components/ui/form";

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
    <Form {...form}>
      <form onSubmit={form.handleSubmit((v) => mutation.mutate(v))} className="space-y-6">
        <FormField control={form.control} name="canals" render={({ field }) => (
          <FormItem>
            <FormLabel>Canaux</FormLabel>
            <FormControl>{/* composant shadcn */}</FormControl>
            <FormMessage />
          </FormItem>
        )} />
        <Button type="submit" disabled={mutation.isPending}>Enregistrer</Button>
      </form>
    </Form>
  );
}
```

**Flow** : Zod valide côté client → SDK → Symfony valide côté serveur → 422 affiché par champ via `<FormMessage />`.

### Action simple / édition inline — `useMutation` + SDK + toast

Pour une action unitaire (date picker, toggle, champ individuel), pas besoin de RHF. `useMutation` + SDK + `handleSdkError` + toast suffit :

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

> **Quand utiliser quoi ?**
> - Formulaire multi-champs avec validation client → RHF + Zod + shadcn Form
> - Action simple, édition inline, toggle → `useMutation` + SDK + `handleSdkError` + toast

### Invalidation du cache après mutation

Utiliser les queryOptions auto-générés pour invalider avec type-safety :

```tsx
import { getResourceListOptions } from "@/lib/api";

const queryClient = useQueryClient();

const mutation = useMutation({
  mutationFn: postAlert,
  onSuccess: () => queryClient.invalidateQueries({ ...getResourceListOptions({ query: filters }) }),
});
```

---

## 5. Pipeline de types

```
Entité PHP + #[Assert\...] + DTO
    ↓  NelmioApiDocBundle
OpenAPI YAML
    ↓  @hey-api/openapi-ts
Types TS + Zod v4 + SDK + queryOptions + mutationOptions (générés dans assets/lib/api/)
```

### Setup

**Backend** :

```bash
composer require nelmio/api-doc-bundle
```

**Frontend** — les packages doivent être déclarés dans `devDependencies` :

```bash
pnpm add -D @hey-api/openapi-ts zod @hey-api/tanstack-react-query
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
    "@hey-api/sdk",
    { name: "zod", version: "v4" },
    "@hey-api/tanstack-react-query",
  ],
});
```

Les 5 plugins :
- `@hey-api/typescript` — types TS depuis le schéma OpenAPI
- `@hey-api/client-fetch` — client HTTP (gère fetch, headers, sérialisation, **multipart**)
- `@hey-api/sdk` — fonctions typées par endpoint (`postProfileSave({ body })`)
- `zod` (version `v4`) — schémas Zod v4 pour validation côté client (14x plus rapide, 57% plus petit que v3)
- `@hey-api/tanstack-react-query` — génère automatiquement les `queryOptions()`, `queryKey`, et `mutationOptions()` depuis l'OpenAPI — élimine le boilerplate de `lib/queries/`

### SDK : appels API typés

Les fonctions générées dans `sdk.gen.ts` fournissent des appels typés par endpoint (`postProfileSave({ body })`) avec autocomplete et erreur TS si le body est invalide. Le SDK gère aussi les **uploads multipart** automatiquement via `formDataBodySerializer`.

### Génération

```makefile
types:
    php -d memory_limit=512M bin/console nelmio:apidoc:dump --format=yaml > openapi.yaml
    pnpm openapi-ts
```

`openapi.yaml` et `assets/lib/api/` sont dans `.gitignore` (fichiers générés).

En CI : `make types && git diff --exit-code assets/lib/api/` pour détecter le drift.

---

## 6. Infra : Vite + Symfony UX

React est monté dans Twig via **Symfony UX React** + **vite-plugin-symfony**.

### Arborescence

```
assets/
├── app.ts                    # Entry point principal
├── react/controllers/        # Composants React montés depuis Twig
│   └── mon_domaine/          # Organisés par domaine métier
├── components/
│   ├── ui/                   # Shadcn/UI (primitives)
│   └── mon_domaine/          # Composants domaine réutilisables
├── lib/
│   ├── api/                  # Généré par hey-api (types, zod v4, sdk, queryOptions, mutationOptions)
│   ├── parseViolations.ts    # Erreurs 422
│   └── queryClient.ts        # Instance QueryClient partagée
```

- **`react/controllers/`** = composants-pages montés depuis Twig (entry points)
- **`components/`** = composants réutilisables (UI primitives, composants domaine)
- **`lib/`** = utilitaires, API client, helpers
- **`lib/api/`** = tout le code généré par hey-api (types, Zod v4, SDK, queryOptions, mutationOptions)

### Montage d'un composant

```twig
{# Le composant assets/react/controllers/mon_domaine/MonComposant.tsx #}
<div {{ react_component('mon_domaine/MonComposant', { ... }) }}></div>
```

Chaque composant monté depuis Twig est une **app React isolée**. Si le composant utilise TanStack Query (`useQuery`, `useMutation`), il doit wrapper dans un `<QueryClientProvider>` :

```tsx
import { QueryClientProvider } from "@tanstack/react-query";
import { queryClient } from "@/lib/queryClient";

const MonComposantApp = (props: MonComposantProps) => { /* ... useQuery, useMutation ... */ };

// Wrapper pour le montage Twig
const MonComposant = (props: MonComposantProps) => (
  <QueryClientProvider client={queryClient}>
    <MonComposantApp {...props} />
  </QueryClientProvider>
);

export default MonComposant;
```

Le `queryClient` est partagé globalement (`assets/lib/queryClient.ts`), pas recréé par composant.

### Vite

Au moins 1 entry point dans `vite.config.js` : `app` (principal). Ajouter des entry points supplémentaires pour les bundles lourds chargés conditionnellement (ex. cartes, éditeurs).

```twig
{{ vite_entry_link_tags('app') }}
{{ vite_entry_script_tags('app', { dependency: 'react' }) }}
```

**Commandes** : `pnpm dev` (dev + HMR), `pnpm build` (production).

---

## 7. Conventions React

### React 19

React 19 est stable (React 18 est en security-support uniquement). Features clés :

- **`ref` comme prop** — plus besoin de `forwardRef`, passer `ref` directement comme prop
- **`use()` API** — lire des promesses et du contexte dans le rendu
- **`useOptimistic`** — mises à jour optimistes natives
- **`<Activity>`** — préserver l'état des composants cachés (anciennement `<Offscreen>`)

```tsx
// React 19 — ref comme prop directement
const Input = ({ ref, ...props }: { ref?: React.Ref<HTMLInputElement> }) => (
  <input ref={ref} {...props} />
);

// Avant React 19 — forwardRef nécessaire
const Input = forwardRef<HTMLInputElement>((props, ref) => (
  <input ref={ref} {...props} />
));
```

### Fichiers et imports

- **Fichiers** : PascalCase (`SearchFarmAlert.tsx`)
- **Dossiers** : snake_case (`search_farm/`, `skills_assessment/`)
- **Imports** : toujours l'alias `@/`, jamais de `../../` relatifs
- **Pas de barrel files** sauf cas de variants (`index.ts` pour exporter un set)

```tsx
// Bon
import { Button } from "@/components/ui/button";
import { postProfileSave } from "@/lib/api";

// Mauvais
import InputWithLabel from "../../ui/composites/InputWithLabel";
```

### Typage

- **Pas de `any`** — utiliser les types générés de `@/lib/api` pour les payloads API, et des interfaces pour les props
- **Props typées** via `interface` dans le même fichier que le composant

```tsx
// Bon
interface EditProfileProps {
  firstName: string;
  lastName: string;
  types: Record<string, string>;
}

const EditProfile = ({ firstName, lastName, types }: EditProfileProps) => { ... };

// Mauvais
const EditProfile = ({ firstName, lastName, types }) => { ... };
const result = await postProfileSave({ body: data as any });
```

Pour les payloads envoyés au SDK, caster vers le type généré (`SaveProfilePayload`) ou structurer l'état du formulaire pour matcher le type directement.

### Data fetching — `useQuery` et `useMutation`

**Lecture** : `useQuery` + queryOptions auto-générés par hey-api. Jamais `useEffect` + `fetch()` + `useState`.

```tsx
// Bon — queryOptions auto-générés par @hey-api/tanstack-react-query
import { getResourceListOptions } from "@/lib/api";
const { data, isLoading } = useQuery({ ...getResourceListOptions({ query: filters }) });

// Mauvais — pas de cache, pas de retry, pas d'invalidation
const [farms, setFarms] = useState([]);
useEffect(() => {
  fetch("/api/farms").then(r => r.json()).then(setFarms);
}, []);
```

**Écriture** : `useMutation` + SDK + `handleSdkError`. Invalider les queries concernées dans `onSuccess`.

```tsx
const mutation = useMutation({
  mutationFn: async (data: SaveProfilePayload) => {
    const result = await postProfileSave({ body: data });
    const errors = handleSdkError(result);
    if (errors) throw new Error(Object.values(errors)[0]);
  },
  onSuccess: () => {
    toast.success("Enregistré");
    queryClient.invalidateQueries({ queryKey: ["profile"] });
  },
  onError: (error: Error) => toast.error(error.message),
});
```

### Formulaires

| Cas | Pattern |
|-----|---------|
| Formulaire multi-champs avec validation client | shadcn `Form` + RHF + Zod généré + `useMutation` |
| Action simple / édition inline / toggle | `useMutation` + SDK + `handleSdkError` + toast |
| Formulaire auth (login, inscription, mdp) | Twig + Symfony Form (pas React) |

### Classes CSS

Utiliser `cn()` de shadcn pour les classes conditionnelles, pas de ternaires dans les strings.

```tsx
// Bon
<div className={cn("rounded-md border p-4", isActive && "bg-primary text-white")} />

// Mauvais
<div className={`rounded-md border p-4 ${isActive ? "bg-primary text-white" : ""}`} />
```

### Composants shadcn

- Utiliser `<Button>` de shadcn avec les variants, pas de `<button>` brut
- Utiliser les composants composés : `Dialog` + `DialogContent` + `DialogHeader`, `Select` + `SelectTrigger` + `SelectContent`, etc.
- Loading : `<Loader2 className="h-4 w-4 animate-spin" />` de lucide-react
- Notifications : `toast` de sonner (pas d'`alert()`)

### QueryClient

Le `queryClient` partagé (`assets/lib/queryClient.ts`) a des defaults sensibles — ne pas créer de `new QueryClient()` dans les composants.

### Performance — React Compiler

Le [React Compiler](https://react.dev/learn/react-compiler) auto-memoize les composants et valeurs, éliminant le besoin de `useMemo`, `useCallback`, et `React.memo` dans la plupart des cas. Fonctionne avec React 17+ via le plugin Babel/Vite.

---

## 8. Quality Assurance — Frontend

### ESLint

Lint du code TypeScript/React. Vérifie les imports, les hooks rules, les patterns React.

```bash
pnpm lint
```

### TypeScript strict

`tsc --noEmit` vérifie le typage sans produire de fichiers. Attrape les erreurs de types que ESLint ne voit pas.

```bash
pnpm tsc --noEmit
```

### Récapitulatif

| Outil | Rôle | Quand |
|-------|------|-------|
| ESLint | Lint JS/TS/React | `/quality` |
| `tsc --noEmit` | Vérification des types | `/quality` |

> Tous ces checks sont regroupés dans la skill globale `/quality` qui auto-détecte le type de projet. Pour les outils de qualité backend (PHPStan, PHP-CS-Fixer, Doctrine, Psalm), voir `docs/symfony-guidelines.md` section 14.

---

## 9. Résumé

| Quoi | Comment |
|------|---------|
| Données au mount | Props Twig (`react_component`) + Serializer `#[Groups]` |
| Données dynamiques | `useQuery` + queryOptions auto-générés (hey-api + TanStack Query) |
| Sérialisation API → React | Serializer + `#[Groups]` (simple) ou Formatter (complexe) |
| Formulaire multi-champs | shadcn Form + RHF + Zod généré + `useMutation` |
| Action simple / édition inline | `useMutation` + SDK + `handleSdkError` + toast |
| Création (POST) | `#[MapRequestPayload]` sur l'entité, `format: 'json'`, `#[IsGranted]` |
| Modification (PUT, peu de champs) | `#[MapRequestPayload]`, même pattern que POST |
| Modification (POST, beaucoup de champs) | DTO allowlist + `ObjectMapper` |
| Upload de fichiers | `#[MapUploadedFile]` + constraints Assert + SDK (multipart auto) |
| Suppression (DELETE) | `useMutation` + `DELETE` + `invalidateQueries` |
| Lecture filtrée (GET) | `#[MapQueryString]` sur un DTO filtre |
| Erreurs 422 | `parseViolations()` + `form.setError()` par champ |
| Erreurs 403/404/500 | `form.setError("root", ...)` + message global |
| Nommage Groups | `entité:read`, `entité:create`, `entité:update` |
| Types TS + Zod v4 + SDK + queryOptions | Générés via `make types` → `assets/lib/api/` |
| Auth / sécurité | Twig + Symfony Form (pas React) |
| Sécurité routes API | `#[IsGranted('ROLE_USER')]` sur méthode/classe |
| Infra front | Vite + vite-plugin-symfony + Symfony UX React |
| Montage composant | `react_component()` dans Twig |
| Package manager | pnpm |
