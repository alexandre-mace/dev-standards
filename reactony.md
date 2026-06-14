# Reactony — Convention Symfony + React

> Source de vérité unique, pas de duplication, un seul pattern.
>
> **Dernière veille : 11 juin 2026** (`/update-guidelines`) — repartir de cette date au prochain run. Versions de référence vérifiées : React 19.2 · React Compiler 1.0 · @vitejs/plugin-react 6 / Vite 8 (Rolldown) · vite-plugin-symfony 8.2 · ux-react 3.1 · TanStack Query 5.10x · RHF 7.78 (v8 en bêta) · Zod 4.4 · @hey-api/openapi-ts 0.98 (pin exact) · Tailwind 4.3 · shadcn (famille `Field`) · Vitest 4 · MSW 2 · Playwright 1.60 · eslint-plugin-react-hooks 7.

## Routage — quoi lire pour quelle tâche

Lire les **Principes** (ci-dessous) et les **anti-patterns (§10)** pour toute tâche ; puis seulement les sections concernées — pas le fichier entier.

| Tâche | Sections |
|---|---|
| Afficher des données (props, useQuery) | §1 Lecture · §5 Pipeline |
| Formulaire (création/édition) | §4 Formulaire · §3 Erreurs 422 · §2 Écriture |
| Upload de fichier | §2 (+ symfony-guidelines §4) |
| Nouvel endpoint consommé par React | §5 Pipeline · §2 Écriture |
| Nouveau composant / montage Twig | §6 Infra · §7 Conventions |
| « React ou Stimulus/Turbo ? » | §6 (Quand React, quand Stimulus) |
| Perf / memoization / React Compiler | §7 (Performance) |
| Tests front | §9 Tests · §8 QA |
| Doute sur un pattern | §11 Résumé |

## Principes

1. **Le PHP est la source de vérité** — types et validation vivent sur l'entité
2. **Types et Zod v4 générés** — depuis les `#[Assert\...]` PHP, jamais écrits manuellement
3. **Un seul pattern formulaire** — `Controller` RHF + shadcn `Field` + Zod + `useMutation` (actions simples : `useMutation` + toast, cf. section 4)
4. **Entité ou DTO** — entité directe quand le payload correspond 1:1 ; DTO quand c'est un sous-ensemble d'une entité large (sécurité : allowlist, mapping via `ObjectMapper`) ou quand le payload n'a pas d'entité correspondante
5. **Auth et sécurité en Twig** — login, inscription, mot de passe restent des formulaires Symfony classiques
6. **React = Reactony, Twig = Symfony Form** — si la page est en React et dynamique, le formulaire suit Reactony. Si la page est en Twig sans React et le formulaire est simple (pas de dynamisme), un Symfony Form classique suffit
7. **SDK partout** — toujours utiliser les fonctions SDK générées, y compris pour les uploads (le SDK gère le multipart via `formDataBodySerializer`)
8. **pnpm** — gestionnaire de paquets pour le front
9. **React pour l'interactivité, Stimulus = infra de montage uniquement** — pas de nouveau contrôleur Stimulus custom, Turbo Drive désactivé (cf. section 6)

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

Les `queryOptions()` sont **auto-générés** par le plugin `@tanstack/react-query` de hey-api (cf. section 5). Pas besoin de les écrire manuellement :

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
  onSuccess: () => queryClient.invalidateQueries({ ...getAlertsOptions() }),
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

### Upload de fichiers — `UploadedFile` dans le DTO (SF 8.1)

> Conventions backend upload détaillées dans `symfony-guidelines.md` section 4.

Depuis Symfony 8.1, le pattern par défaut est un **DTO plat** `#[MapRequestPayload]` contenant le fichier et les champs texte — un seul paramètre, une seule surface de validation :

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

Limites : DTO **plat** uniquement (un `UploadedFile` dans un objet imbriqué casse — [bug #64571](https://github.com/symfony/symfony/issues/64571) ; un payload d'upload imbriqué est de toute façon un smell, aplatir) ; identifiant → param de route (`{fieldId}`). `#[MapUploadedFile]` reste le fallback pour un fichier seul :

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

#### ⚠️ Convention : guard client-side sur la taille

Toujours valider `file.size` **avant** l'appel réseau et afficher un toast explicite. Raison : PHP drop silencieusement les uploads qui dépassent `upload_max_filesize` / `post_max_size` (SAPI) **avant** que Symfony n'exécute la contrainte `Assert\File(maxSize: …)`. Dans ce cas, `RequestPayloadValueResolver` voit un payload `null` et throw `HttpException(422)` **avec message vide** — le front reçoit un 422 sans `violations` et le toast reste muet. On l'a vécu avec LAGRANGE-27 (iPhones uploadant des photos > 5 MB).

Pattern standard, limite front = limite back :

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

Le backend garde sa contrainte `Assert\File(maxSize)` comme dernier rempart (bypass volontaire du front). Les deux limites doivent rester alignées.

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
  onSuccess: () => queryClient.invalidateQueries({ ...getAlertsOptions() }),
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

> **Gotcha upload (drop SAPI)** : quand PHP drop l'upload au niveau SAPI (`upload_max_filesize` dépassé), le resolver — `RequestPayloadValueResolver` (DTO plat) comme `MapUploadedFile` — throw un `HttpException(422)` **avec body vide**, pas de `violations`. Le toast est muet. Solution : guard `file.size` côté front (cf. convention plus haut). Voir aussi `symfony-guidelines.md` section 4 pour le backend.

> **Gotcha enum nullable** : React-hook-form défaulte les selects enum à `""` quand non rempli. Côté back, `Enum::from('')` throw un `ValueError` → 500. Soit le controller coerce `'' → null` avant denormalize (cf. `symfony-guidelines.md` section 4), soit le front omet la clé. On fait les deux par sécurité.

### Le choix de lib formulaire (re-validé juin 2026)

`react-hook-form` + `zod` + `@hookform/resolvers` est le stack confirmé pour ce projet. La question revient souvent ; voici la décision pour ne pas y repasser (re-confrontée au web en juin 2026 : TanStack Form v1 est mature mais son mapping d'erreurs serveur reste moins propre que `setError` ; toujours aucun outillage `useActionState` hors Next — la décision tient) :

- **Pas de migration vers TanStack Form.** Coût non trivial (réécrire `handleSdkError`, reporter tous les `setError`), gain marginal vu qu'openapi-ts + Zod couvrent déjà la type-safety bout-en-bout. RHF reste le choix.
- **Pas de migration vers React 19 Actions** (`useActionState`) pour les forms avec validation serveur structurée. Le mapping `violations[].propertyPath` → erreurs par champ n'est pas natif dans Actions, et Actions veut posséder le `pending/error` state que TanStack Query possède déjà. Double-ownership awkward.
- **Oui à `useOptimistic`** pour les mutations UI-instant (toggle favori, add to list, reorder). Compose proprement avec RHF + TanStack Query sans conflit.
- **`useFormStatus` : non, pas dans ce pattern** — il ne reporte `pending` que pour un `<form action={...}>` (React Actions). Avec RHF + `useMutation` (submit via `onSubmit`), il resterait toujours `false`. L'état de soumission vient de `mutation.isPending`, ou de `useFormState({ control }).isSubmitting` pour un bouton imbriqué profond.

```tsx
// useOptimistic — UI instant pendant qu'une mutation TanStack Query est en vol
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

À utiliser pour les mutations "réversibles / non critiques". Pas pour une création d'entité qui peut échouer visiblement côté backend.

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

Dans le JSX, afficher l'erreur root — via `useFormState`, pas en lisant le proxy `form.formState` au render (règle React Compiler, cf. section 7) :

```tsx
const { errors } = useFormState({ control: form.control });

{errors.root && (
  <p className="text-sm text-destructive">{errors.root.message}</p>
)}
```

---

## 4. Formulaire React

### Formulaire multi-champs — RHF + Zod + shadcn `Field`

Pour un formulaire avec plusieurs champs et validation côté client : **`Controller` (RHF) + famille `Field` shadcn + Zod généré + `useMutation`**.

Depuis octobre 2025, shadcn a remplacé le wrapper `<Form>/<FormField>/<FormMessage>` (boîte noire couplée RHF) par les composants **`Field`** agnostiques (`npx shadcn@latest add field`). Pattern canonique :

```tsx
import { useForm, Controller } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation } from "@tanstack/react-query";
import { z } from "zod";
import { zSearchFarmNotification } from "@/lib/api/zod.gen"; // généré (cf. section 5)
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
            {/* composant shadcn, avec id={field.name} et aria-invalid={fieldState.invalid} */}
            {fieldState.invalid && <FieldError errors={[fieldState.error]} />}
          </Field>
        )}
      />
      <Button type="submit" disabled={mutation.isPending}>Enregistrer</Button>
    </form>
  );
}
```

Clés : `data-invalid` sur `<Field>` (bascule tout le bloc en état erreur) + `aria-invalid` sur le contrôle. L'ancien pattern `<Form>/<FormField>/<FormMessage>` est toléré dans le code existant — pas pour du nouveau code ; migration opportuniste quand on touche le fichier.

**Flow** : Zod valide côté client → SDK → Symfony valide côté serveur → 422 affiché par champ via `form.setError` + `<FieldError>`.

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
> - Formulaire multi-champs avec validation client → RHF + Zod + shadcn `Field`
> - Action simple, édition inline, toggle → `useMutation` + SDK + `handleSdkError` + toast

### Invalidation du cache après mutation

Depuis TanStack Query 5.82, `mutationOptions()` est le pendant de `queryOptions()` — hey-api les génère aussi (`addPetMutation()`, etc.) : les utiliser pour factoriser une mutation partagée entre composants.

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

**Frontend** — un seul package dev (plugins et clients **bundlés** — pas de package npm séparé), **en version exacte** (`-E` : projet pré-1.0, pin demandé par les mainteneurs) ; `zod` en dépendance runtime :

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
    { name: "@hey-api/sdk", validator: { response: "zod" } }, // validation runtime des réponses (optionnel)
    "zod",                   // Zod 4 par défaut ({ name: "zod", compatibilityVersion: 3 | "mini" } sinon)
    "@tanstack/react-query", // nom du plugin bundlé — PAS un package npm
  ],
});
```

Les 5 plugins :
- `@hey-api/typescript` — types TS depuis le schéma OpenAPI
- `@hey-api/client-fetch` — client HTTP (gère fetch, headers, sérialisation, **multipart**)
- `@hey-api/sdk` — fonctions typées par endpoint (`postProfileSave({ body })`) ; `validator: { response: 'zod' }` valide aussi les réponses au runtime avec les schémas déjà générés (coût perf — opt-in)
- `zod` — schémas Zod 4 pour validation côté client
- `@tanstack/react-query` — génère automatiquement les `queryOptions()`, `queryKey`, et `mutationOptions()` depuis l'OpenAPI — élimine le boilerplate de `lib/queries/`

Au bump de version (pin exact oblige), lire la [page Migrating](https://heyapi.dev/openapi-ts/migrating). Depuis 0.93 : 0.95 n'exporte plus les schémas `Data` composites (`shouldExtract: true` pour revenir), 0.96 requiert Node ≥ 22.13, 0.97 respecte réellement `throwOnError: false`. Zod 4.4 est volontairement plus strict — relancer la suite Vitest au bump. Zod 4.1+ fournit aussi `z.codec()` (transformations bidirectionnelles typées, ex. string ISO ↔ `Date`) pour les conversions API ↔ domaine à la main.

### SDK : appels API typés

Les fonctions générées dans `sdk.gen.ts` fournissent des appels typés par endpoint (`postProfileSave({ body })`) avec autocomplete et erreur TS si le body est invalide. Le SDK gère aussi les **uploads multipart** automatiquement via `formDataBodySerializer`.

### Génération

```makefile
types:
    php -d memory_limit=512M bin/console nelmio:apidoc:dump --format=yaml > openapi.yaml
    pnpm openapi-ts
```

`openapi.yaml` et `assets/lib/api/` sont **commités** — le gate de drift compare le généré au checked-in ; `git diff --exit-code` ne verrait rien s'ils étaient gitignorés.

En CI : `make types && git diff --exit-code openapi.yaml assets/lib/api/` pour détecter le drift. En complément, `oasdiff` classifie le diff d'`openapi.yaml` en breaking / non-breaking (cf. symfony-guidelines.md §13).

---

## 6. Infra : Vite + Symfony UX

React est monté dans Twig via **Symfony UX React** + **vite-plugin-symfony**. (ux-react 3.x : requiert PHP 8.4 / Symfony 7.4, `react_component()` et `registerReactControllerComponents()` inchangés — montée mécanique depuis 2.x.)

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

### Quand React, quand Stimulus, quand Turbo

Un seul modèle d'interactivité :

- **Page = Twig statique par défaut. Interactivité = island React** (`react_component()`), même petite — le pipeline (types, SDK, shadcn) rend l'island moins cher à maintenir qu'un contrôleur Stimulus hors écosystème.
- **Stimulus = infrastructure de montage uniquement.** Le pont `symfony/ux-react` est lui-même un contrôleur Stimulus — invisible, on n'y touche pas. **Ne pas écrire de nouveau contrôleur Stimulus custom** : pas d'état, pas de fetch, pas de logique métier dans Stimulus. Tolérance : micro-comportement DOM sans état (< ~30 lignes, ex. copy-to-clipboard) si un island serait disproportionné. Les contrôleurs custom existants sont du legacy à ne pas copier.
- **Cas particulier — enrichir un champ de Symfony Form classique** (éditeur riche, datepicker, autocomplete sur un `<input>`/`<textarea>` rendu serveur) : c'est un usage Stimulus *légitime* en soi (progressive enhancement, le modèle Symfony UX). MAIS si l'équivalent React existe déjà (ex. un composant `Wysiwyg`), **réutilise-le en island** plutôt que de maintenir un contrôleur Stimulus parallèle qui le duplique : monte le composant React et fais-le **se synchroniser dans le champ caché** (`document.getElementById(targetId).value = ...` sur update) pour qu'il parte au POST. Un seul éditeur/composant pour toute l'app, le champ Symfony reste la source soumise. Cf. `AdvertWysiwygField` (island montée sur un Symfony Form, pas de RHF — le form reste serveur).
- **Turbo Drive : désactivé globalement (`<body data-turbo="false">`) et c'est voulu** — la navigation Turbo remonte les islands React (état perdu, double-mount). Ne pas le réactiver sans décision explicite ; pour le polish de navigation, la voie est les View Transitions natives (CSS cross-document). `ux-turbo` reste installé pour un éventuel usage Turbo Streams/Mercure, pas pour le drive.
- **RSC / React Server Components : on n'en fait pas, et c'est voulu.** Symfony+Twig **est** déjà la couche serveur — les îlots React sont les feuilles client intentionnelles. Bolter RSC imposerait un serveur Node de rendu à côté de PHP (double rôle, déploiement CleverCloud cassé, surface de vuln RSC en plus) pour un problème que PHP résout déjà. `@vitejs/plugin-rsc` existe (2026) mais reste expérimental et hors-Next — non pertinent pour le modèle îlots-dans-Symfony. Re-statuer seulement si on abandonnait le HTML rendu serveur pour un front 100 % JS (= une autre archi, pas une évolution de celle-ci).

---

## 7. Conventions React

### React 19

React 19 est stable (React 18 est en security-support uniquement). Features clés :

- **`ref` comme prop** — plus besoin de `forwardRef`, passer `ref` directement comme prop (+ fonctions de cleanup sur les refs)
- **`use()` API** — lire des promesses et du contexte dans le rendu
- **`useOptimistic`** — mises à jour optimistes natives
- **`<Activity>`** — stable depuis 19.2 : préserver l'état des composants cachés (`mode="visible|hidden"`)
- **`useEffectEvent`** — stable depuis 19.2 : extraire d'un Effect la logique événementielle qui lit props/state sans les mettre en dépendances. Jamais dans le tableau de deps (le lint react-hooks l'impose), à déclarer dans le composant qui contient l'Effect
- View Transitions (`<ViewTransition>`) : toujours expérimental — pas en prod

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
// Bon — queryOptions auto-générés par le plugin @tanstack/react-query (hey-api)
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
    queryClient.invalidateQueries({ ...getProfileOptions() });
  },
  onError: (error: Error) => toast.error(error.message),
});
```

### Formulaires

| Cas | Pattern |
|-----|---------|
| Formulaire multi-champs avec validation client | `Controller` RHF + shadcn `Field` + Zod généré + `useMutation` |
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

- **Boutons & éléments cliquables** — choisir selon l'intention, comme le font les primitives shadcn elles-mêmes (leurs triggers/closes Radix sont des `<button>` stylés, pas le composant `<Button>`) :
  - **Action** (submit, valider, supprimer…) → `<Button variant=…>`.
  - **Lien** (navigation) → `<Button asChild variant=…><a href></Button>` : `asChild` garde un vrai `<a>`, le variant choisit le look (`link` = lien inline ; `default`/`secondary`/`outline` = bouton plein).
  - **Sélectionnable / toggle** (pills de filtre, multi-select) → `Toggle` / `ToggleGroup` (pas `<Button>`).
  - **Sur-mesure** (tuile d'image, carte-cliquable, micro-contrôle icône positionné en `absolute`, dropzone) → un `<button>` brut est légitime — `<Button>` n'y apporterait qu'un variant à réécrire.
  - Échappatoire `buttonVariants({variant})` : pour donner le look bouton à un élément qu'on ne peut pas rendre via `<Button asChild>` (ex. composant `Link` tiers).
- Utiliser les composants composés : `Dialog` + `DialogContent` + `DialogHeader`, `Select` + `SelectTrigger` + `SelectContent`, etc.
- Loading : `<Loader2 className="h-4 w-4 animate-spin" />` de lucide-react
- Notifications : `toast` de sonner (pas d'`alert()`)

### QueryClient

Le `queryClient` partagé (`assets/lib/queryClient.ts`) a des defaults sensibles — ne pas créer de `new QueryClient()` dans les composants.

### Performance — React Compiler

Le [React Compiler](https://react.dev/learn/react-compiler) est **activé dans ce projet**. ⚠️ Depuis `@vitejs/plugin-react` v6 (Vite 8), l'option `react({ babel: {...} })` n'existe plus (transforms Oxc) et est **ignorée silencieusement** — la seule config qui exécute réellement le compiler :

```js
// vite.config.js
import react, { reactCompilerPreset } from "@vitejs/plugin-react";
import babel from "@rolldown/plugin-babel";

export default defineConfig({
  plugins: [react(), babel({ presets: [reactCompilerPreset()] })],
});
```

(`pnpm add -D -E babel-plugin-react-compiler` + `pnpm add -D @rolldown/plugin-babel @babel/core`). Les règles lint compiler sont fournies par `eslint-plugin-react-hooks` ≥ 7 (`configs.flat.recommended`) — le package `eslint-plugin-react-compiler` est gelé, ne plus l'installer.

**Conséquence sur le code à écrire** :
- Ne pas ajouter `useMemo` / `useCallback` / `React.memo` "par précaution". Le compiler les pose automatiquement là où c'est nécessaire.
- Les laisser **uniquement** quand :
  - un profilage (React DevTools Profiler) montre un re-render coûteux spécifique
  - une règle compiler du lint `react-hooks` (≥ 7) remonte un bail sur le composant (donc le compiler ne le mémoize pas, rare cas où un `useMemo` explicite a du sens)
- Les `useMemo`/`useCallback` existants dans le code pré-compiler ne sont pas à enlever activement — ils deviennent no-op (le compiler en met par-dessus). Nettoyage opportuniste quand on touche le fichier.

**Composants bailés (compiler skip)** : le plugin ESLint signale en warn les composants qui violent les Rules of React (side effects dans render, writes à `window.*`, refs mutées depuis un callback externe, `// eslint-disable-next-line react-hooks/exhaustive-deps`). Ces composants fonctionnent correctement mais ne bénéficient pas de l'auto-memoization. Pas bloquant ; fixer au cas par cas si le profilage l'exige.

**Règle d'or** : écris le code React le plus simple possible. Le compiler optimise.

**React Compiler × react-hook-form (v7)** : le proxy `formState` et `watch()` reposent sur une mutabilité interne que le compiler mémoïse à tort (la règle `incompatible-library` du lint les signale). Avec compiler actif :

- ❌ `watch('field')` et lecture de `form.formState.X` au render ; ne pas passer `formState` en prop
- ✅ `useWatch({ control, name })`, `useFormState({ control })`, `useController` / `<Controller>` (abonnements explicites) ; `getValues()` réservé aux handlers/effects
- Échappatoire transitoire : directive `'use no memo'` sur un composant formulaire problématique

RHF v8 (refonte compiler-first) est en bêta — ne pas l'adopter avant la stable.

---

## 8. Quality Assurance — Frontend

### ESLint

Lint du code TypeScript/React. Vérifie les hooks rules, les patterns React, les types.

```bash
pnpm lint          # vérifie
pnpm lint:fix      # auto-corrige
```

Config flat (`eslint.config.js`) avec :
- `@eslint/js` + `typescript-eslint` — règles TS
- `eslint-plugin-react-hooks` ≥ 7 via `configs.flat.recommended` — hooks rules **et règles React Compiler** (fusionnées depuis la v6 ; `eslint-plugin-react-compiler` est obsolète)
- `eslint-config-prettier` — désactive les règles qui entrent en conflit avec Prettier

### Prettier

Formatage du code (indentation, quotes, trailing commas, tri des classes Tailwind).

```bash
pnpm format        # formate
pnpm format:check  # vérifie sans modifier
```

Config (`.prettierrc`) avec `prettier-plugin-tailwindcss` pour le tri automatique des classes (≥ 0.8 requiert Prettier ≥ 3.7 ; depuis 0.7 le plugin trie aussi les classes dans les **templates Twig** — l'activer sur `templates/`).

### TypeScript strict

`tsc --noEmit` vérifie le typage sans produire de fichiers. Attrape les erreurs de types que ESLint ne voit pas.

```bash
pnpm tsc --noEmit
```

### Récapitulatif

| Outil | Rôle | Quand |
|-------|------|-------|
| ESLint | Lint JS/TS/React, hooks rules | `/quality` |
| Prettier | Formatage, tri classes Tailwind | `/quality` |
| `tsc --noEmit` | Vérification des types | `/quality` |

> Tous ces checks sont regroupés dans la skill globale `/quality` qui auto-détecte le type de projet. Pour les outils de qualité backend (PHPStan, PHP-CS-Fixer, Doctrine, Psalm), voir `docs/symfony-guidelines.md` section 14.

### Pre-commit — husky + lint-staged

Le garde-fou universel côté front : ESLint + Prettier tournent automatiquement sur les fichiers `*.{ts,tsx}` stagés avant chaque commit. `tsc --noEmit` et la détection de drift sur `openapi.yaml` / `assets/lib/api/` tournent en plus au niveau projet. Setup mutualisé avec le backend (PHP-CS-Fixer, PHPStan, `lint:container`, `schema:validate`) dans une seule config. Détails et timings dans `docs/symfony-guidelines.md` section Quality Assurance.

En session de dev assistée par IA, lancer `/quality` avant de déclarer une tâche terminée quand du code a été modifié — le pre-commit reste le filet final, pas le premier recours.

---

## 9. Tests

Stack standard, partagée par tous les projets Symfony+React. Pas de "léger" ou "lourd" — la même chose partout pour qu'un dev qui passe d'un projet à l'autre n'apprenne qu'une fois.

| Couche | Outil | Rôle |
|---|---|---|
| Unit / composant | **Vitest 4** + `@testing-library/react` + `@testing-library/user-event` (jsdom) | Logique pure et composants isolés |
| Mock SDK / API | **MSW 2** (Mock Service Worker) | Intercepte les requêtes réseau du SDK généré ; mocks réutilisables en Storybook et dev |
| E2E / parcours | **Playwright** | Couvre les flows multi-pages (tunnel, paiement, signature) en vrai navigateur |
| Accessibilité | **`@axe-core/playwright`** | Audit WCAG dans chaque spec E2E |

```bash
pnpm test                # Vitest, run complet
pnpm test --watch        # mode watch
pnpm test:e2e            # Playwright
pnpm test:e2e:ui         # Playwright en mode UI interactif
```

> **Pourquoi Vitest et plus Jest** : projet sous Vite, donc Vitest partage la même config (alias `@/`, plugins, transformeurs TS/TSX). ESM natif → `lucide-react`, hey-api SDK et autres modules ESM marchent sans `transformIgnorePatterns`. Compatible React 19, 5–28× plus rapide que Jest selon la suite. L'API est quasi-identique : `vi` à la place de `jest`, `vi.mock()` hoisted comme `jest.mock()`, mêmes matchers via `@testing-library/jest-dom` (compatible Vitest).

### Quoi tester — par ordre de ROI

1. **Fonctions pures** (calculs rendement, helpers métier, formatters Zod, transforms). Pas de mock, pas de DOM. Les bugs ici dérivent les chiffres affichés au client — ça se voit et ça fait perdre du CA.
2. **Composants de form** avec validation Zod / RHF (tunnel, paiement, beneficiary). Taper sur tous les champs invalides + le happy path + les erreurs serveur 422. Mocker le SDK via MSW.
3. **Parcours E2E** des flows critiques : tunnel d'investissement complet, login, achat gift card, signature ZohoSign. **Un parcours = une spec Playwright.**
4. **Composants complexes avant un refactor** (wizards multi-step, composants > 500 lignes). Écrire les tests qui pinent le comportement visible **actuel** avant de changer les entrailles.
5. **Le reste — skip.** Un composant de présentation qui passe 3 props à 3 shadcn children, pas de test. TypeScript + ESLint suffisent.

### Setup Vitest

`vitest.config.ts` à la racine, partage la config Vite :

```ts
import {defineConfig, mergeConfig} from 'vitest/config';
import viteConfig from './vite.config';

export default mergeConfig(viteConfig, defineConfig({
    test: {
        environment: 'jsdom',
        globals: true,                    // describe / it / expect dispo sans import
        setupFiles: ['./assets/test-setup.ts'],
        css: false,                       // pas besoin de parser le CSS
    },
}));
```

`assets/test-setup.ts` :

```ts
import '@testing-library/jest-dom/vitest';
import {cleanup} from '@testing-library/react';
import {afterEach, beforeAll, afterAll} from 'vitest';
import {server} from './test-mocks/server';

afterEach(() => cleanup());

// MSW : intercepte toutes les requêtes du SDK pendant les tests
beforeAll(() => server.listen({onUnhandledRequest: 'error'}));
afterEach(() => server.resetHandlers());
afterAll(() => server.close());

// Override propre de navigator.language sans casser userAgent
Object.defineProperty(window.navigator, 'language', {value: 'fr-FR', configurable: true});

// window.location.reload / assign sont non-writable en jsdom — patch via prototype
beforeAll(() => {
    const proto = Object.getPrototypeOf(window.location);
    proto.reload = vi.fn();
    proto.assign = vi.fn();
});
```

### Setup MSW — mocker le SDK généré

MSW intercepte au niveau réseau, donc les fonctions hey-api restent appelées normalement. Avantage majeur vs `vi.mock('@/lib/api')` : les mocks sont définis une fois et partagés entre tests, Storybook et dev.

`assets/test-mocks/server.ts` :

```ts
import {setupServer} from 'msw/node';
import {handlers} from './handlers';

export const server = setupServer(...handlers);
```

`assets/test-mocks/handlers.ts` — handlers par défaut, à override par test si besoin :

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

Override par test :

```tsx
import {server} from '@/test-mocks/server';
import {http, HttpResponse} from 'msw';

it('shows 422 violations', async () => {
    server.use(http.post('/api/beneficiary', () =>
        HttpResponse.json({violations: [{propertyPath: 'firstName', title: 'Required'}]}, {status: 422}),
    ));
    // … render et assert
});
```

### Wrapper TanStack Query — `QueryClient` jetable

Chaque composant qui utilise `useMutation` / `useQuery` doit être rendu dans un `QueryClientProvider`. Créer un helper :

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

`retry: false` + `gcTime: 0` → erreurs immédiates et pas de cache résiduel entre tests.

### Safety-net-first avant un gros refactor

Avant de migrer un composant gros et fragile (> 500 lignes, ex. `useState` → RHF + Zod), écrire d'abord les tests qui pinent son comportement visible **actuel** : happy path + guards + erreurs serveur. Refactorer ensuite, en s'assurant que la suite reste verte. Si un test casse, c'est un vrai changement de comportement — soit c'est intentionnel, soit c'est une régression.

### Gotchas Vitest + React 19 + shadcn

Ces pièges coûtent chacun 30 min à 1 h à diagnostiquer la première fois. Vitest + ESM en élimine plusieurs (lucide, hey-api SDK), mais les autres restent.

**Radix (shadcn Select, Dialog, Popover) est fragile dans jsdom.** Les portals + pointer events + focus trap se comportent mal sans un vrai layout engine. Deux options :

1. **Mocker localement** shadcn Select en `<select>` natif quand le test n'a besoin que du contrat `onValueChange` :
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
            SelectItem: ({value, children}: any) => /* injecte une <option> */,
        };
    });
    ```

2. **Passer en Vitest Browser Mode** (stable depuis Vitest 4) pour les specs qui interagissent vraiment avec Select / Dialog / Popover. Provider en package séparé (`pnpm add -D @vitest/browser-playwright`) et passé en **fonction** :
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
    Plus rapide à écrire qu'un mock complexe, mais plus lent à exécuter (vrai navigateur). À utiliser au cas par cas. jsdom reste le défaut pour l'unitaire léger.

**shadcn `<Label>` n'est pas wired via `htmlFor`.** `getByLabelText(/Nom/)` ne résout pas. Helper :

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

**Toujours préférer MSW à `vi.mock('@/lib/api')`.** Le mock module marche, mais MSW intercepte au bon niveau (réseau) et reste cohérent quand on passe en E2E. Si un test fait vraiment juste `vi.mock` du SDK, c'est un signe que le test devrait être un test de fonction pure, pas un test de composant.

### E2E avec Playwright

Détails complets dans `symfony-guidelines.md` §13 (Playwright partage l'infra DB du backend : commande `app:e2e:seed`, `storageState` pré-loggé, etc.). Côté React, à savoir :

1. **Une spec par parcours utilisateur**, pas une spec par page. Granularité = "ce qu'un utilisateur essaie de faire". Ex. `tunnel-invest.spec.ts`, pas `step-amount.spec.ts` + `step-identity.spec.ts`.
2. **Locators sémantiques** : `page.getByRole('button', {name: /valider/i})` plutôt que `page.locator('.btn-submit')`. Résiste aux refactors Tailwind.
3. **Forms shadcn** : `await page.getByLabel(/Nom/i).fill('Dupont')`. Si le `Label` n'est pas wired, fallback sur `getByPlaceholder` ou `getByRole('textbox', {name: ...})`.
4. **a11y inline dans chaque spec** :
    ```ts
    import AxeBuilder from '@axe-core/playwright';

    test('tunnel step amount is accessible', async ({page}) => {
        await page.goto('/tunnel');
        const results = await new AxeBuilder({page}).analyze();
        expect(results.violations).toEqual([]);
    });
    ```

### Ce qu'il n'est PAS utile de tester côté front

- Un composant qui ne fait qu'appeler un SDK et afficher le résultat : le contract test backend (functional PHPUnit) couvre déjà le contrat API, TypeScript couvre le typage, ESLint la structure.
- Les snapshot tests de rendu complet : ils cassent au moindre changement de classe Tailwind, zéro signal utile. Préférer `toHaveTextContent` + `toBeVisible`.
- Les composants de UI kit (shadcn passthrough).
- Tester le rendu d'un composant uniquement parce qu'on a un fichier à toucher. Ajouter un test parce que la *complexité* du composant le justifie, pas par réflexe.

---

## 10. Anti-patterns interdits

Règles noires côté front. Si tu les vois dans le code existant, c'est à refactorer — pas à copier.

**Data fetching / mutations**
- `useEffect(() => { fetch('/api/...') })` + `useState` — utiliser `useQuery` avec les `queryOptions` auto-générés par hey-api
- `fetch()` direct — utiliser les fonctions SDK générées (`postX`, `getY`, etc.)
- Mutation sans `handleSdkError` — les 422 sont perdues silencieusement côté UX
- `new QueryClient()` dans un composant — importer le `queryClient` partagé
- Composant monté depuis Twig qui utilise `useQuery` / `useMutation` sans `<QueryClientProvider>` wrapper

**Formulaires**
- `useState` pour l'état d'un form multi-champs avec validation — utiliser RHF + Zod
- `<Form>/<FormField>/<FormMessage>` shadcn pour du **nouveau** code — pattern legacy, utiliser `Controller` + famille `Field` (`data-invalid`, `<FieldError>`)
- Zod schéma écrit à la main pour un payload API — importer depuis `zod.gen`
- Catch 422 bespoke — utiliser `handleSdkError` + `form.setError` par champ
- Form auth (login, inscription, mot de passe) en React — garder en Twig + Symfony Form
- Form React qui manipule directement l'entité plutôt qu'un payload dérivé — passer par un DTO backend quand le form modifie un sous-ensemble de champs

**Upload**
- Upload fichier sans guard `file.size` côté front — PHP SAPI drop silencieux au-delà de `upload_max_filesize`, le resolver (DTO `#[MapRequestPayload]` ou `MapUploadedFile`) renvoie un 422 vide et le toast reste muet. Limite front = limite back (`Assert\File(maxSize)`)
- Upload via `FormData` fait main — le SDK gère le multipart automatiquement via `formDataBodySerializer`

**Typage**
- `any` — seule exception tolérée : `form.setError(field as any, ...)` (workaround documenté RHF pour le typage de `Object.entries()`)
- Props typées inline sans `interface` — déclarer une `interface Props` dans le même fichier
- `as any` sur un payload envoyé au SDK — structurer l'état du form pour matcher le type généré, ou caster vers le type généré

**React 19 / React Compiler**
- `useMemo` / `useCallback` / `React.memo` sans profilage concret — le React Compiler les pose automatiquement, les ajouter à la main est bruit (et ils deviennent no-op)
- `forwardRef` — React 19 accepte `ref` comme prop directe
- `useEffect` pour dériver un état d'un autre état — calculer pendant le render
- `watch()` ou lecture du proxy `formState` au render avec le compiler actif — utiliser `useWatch` / `useFormState({ control })` / `useController` (lint `incompatible-library`)

**Styling / UI kit**
- `<button>` brut pour une **action** ou un **lien** — utiliser `<Button>` (variant pour l'action, `asChild` + `<a>` pour le lien). _NB : un `<button>` brut reste correct pour le sur-mesure (tuile, carte-cliquable, micro-icône absolue, dropzone) et les toggles vont vers `Toggle`/`ToggleGroup` — cf. section « Composants shadcn »._
- `className={...ternary...}` dans un template literal — utiliser `cn()` de shadcn pour les classes conditionnelles
- `alert()` ou `window.confirm()` — utiliser `toast` de sonner et les composants `Dialog` / `AlertDialog` de shadcn
- Icône `lucide-react` montée à la main dans un bouton avec loading — utiliser le loading state fourni par le composant shadcn

**Imports / structure**
- Imports relatifs `../../components/...` — utiliser l'alias `@/`
- Barrel file `index.ts` qui réexporte N composants sans rapport — uniquement pour des sets de variants cohérents

**Intégration Twig**
- Nouveau contrôleur Stimulus custom (état, fetch, logique) — c'est un island React ; Stimulus n'est que le pont de montage UX (cf. section 6)
- Réactiver Turbo Drive sans décision explicite — il remonte les islands React (état perdu) ; le site est volontairement en `data-turbo="false"`

---

## 11. Résumé

| Quoi | Comment |
|------|---------|
| Données au mount | Props Twig (`react_component`) + Serializer `#[Groups]` |
| Données dynamiques | `useQuery` + queryOptions auto-générés (hey-api + TanStack Query) |
| Sérialisation API → React | Serializer + `#[Groups]` (simple) ou Formatter (complexe) |
| Formulaire multi-champs | `Controller` RHF + shadcn `Field` + Zod généré + `useMutation` |
| Action simple / édition inline | `useMutation` + SDK + `handleSdkError` + toast |
| Création (POST) | `#[MapRequestPayload]` sur l'entité, `format: 'json'`, `#[IsGranted]` |
| Modification (PUT, peu de champs) | `#[MapRequestPayload]`, même pattern que POST |
| Modification (POST, beaucoup de champs) | DTO allowlist + `ObjectMapper` |
| Upload de fichiers | DTO plat `#[MapRequestPayload]` + `UploadedFile` + Assert (SF 8.1) + SDK (multipart auto) |
| Suppression (DELETE) | `useMutation` + `DELETE` + `invalidateQueries` |
| Lecture filtrée (GET) | `#[MapQueryString]` sur un DTO filtre |
| Erreurs 422 | `handleSdkError` + `form.setError()` par champ |
| Erreurs 403/404/500 | `form.setError("root", ...)` + message global |
| Nommage Groups | `entité:read`, `entité:create`, `entité:update` |
| Types TS + Zod v4 + SDK + queryOptions/mutationOptions | Générés via `make types` → `assets/lib/api/` |
| Auth / sécurité | Twig + Symfony Form (pas React) |
| Sécurité routes API | `#[IsGranted('ROLE_USER')]` sur méthode/classe |
| Infra front | Vite + vite-plugin-symfony + Symfony UX React |
| Montage composant | `react_component()` dans Twig |
| Package manager | pnpm |
