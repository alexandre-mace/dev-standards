# Symfony Guidelines

> Conventions Symfony réutilisables entre projets. Pragmatique, pas dogmatique.

## Principes

1. **Domain/ = les règles** — décide, valide, calcule. Ne dépend de rien d'externe.
2. **Service/ = l'exécution** — persiste, envoie, appelle des APIs. Fait les choses.
3. **Controller = l'orchestrateur** — câble Domain + Service + Api. Extrait dans Service/ si ça se répète ou déborde.
4. **Enums PHP natifs** — pour tout ensemble fini de valeurs (statuts, types, catégories)
5. **Constructor property promotion + `readonly`** — injection moderne, propriétés `private readonly`, pas d'assignation manuelle
6. **Pas d'abstraction préventive** — pas d'interface, pas de ValueObject, pas d'Aggregate sauf si réellement nécessaire
7. **`#[IsGranted]`** — sécurité au niveau méthode sur les routes API, pas de `access_control` par pattern d'URL pour les `/api/`

---

## Feature playbook — one-shot

Séquence standard pour implémenter une feature full-stack. L'IA qui la suit dans l'ordre évite les questions aller-retour et livre une feature cohérente du premier coup.

### 1. Modéliser le contrat backend

- **Lecture** : identifier l'entité + `#[Groups]` de read (ou créer un Formatter si URLs calculées, agrégats, ou données croisées)
- **Filtre GET** : DTO dans `Dto/` avec `#[MapQueryString]`
- **Écriture** :
  - Payload = entité 1:1 → `#[MapRequestPayload]` sur l'entité + `#[Assert\...]` sur les champs
  - Sous-ensemble d'une grosse entité → DTO allowlist (`#[Map(target: Entity::class)]`) + `ObjectMapper`
  - Pas d'entité → DTO simple
- **Upload fichier** : `#[MapUploadedFile(constraints: [new Assert\File(maxSize: 'XM')])]`, identifiant en param de route

### 2. Route controller

- `#[Route(path: '/api/...', methods: [...], format: 'json')]` — `format: 'json'` **obligatoire**
- `#[IsGranted('ROLE_USER')]` sur méthode ou classe — obligatoire sur `/api/`
- CRUD simple → EntityManager direct dans le controller. Logique métier → extraire Domain + Service.
- Appel externe non critique → catch + `logger->error()` + swallow (le flow user ne doit pas casser pour un upstream flaky)

### 3. Génération des types

- `make types` (ou équivalent projet) → types TS + Zod + SDK + queryOptions dans `assets/lib/api/`
- CI : `make types && git diff --exit-code assets/lib/api/` détecte le drift

### 4. Composant React

- **Multi-champs avec validation** : shadcn `Form` + RHF + `zodResolver(zSchema depuis zod.gen)` + `useMutation` + `handleSdkError` + `form.setError` par champ
- **Action simple / toggle / inline edit** : `useMutation` + SDK + `handleSdkError` + toast
- **Lecture dynamique** : `useQuery({ ...getXOptions({...}) })`
- **Mount point Twig** : wrapper la racine avec `<QueryClientProvider>` + `<Toaster>` (factoriser dans un `<AppProviders>` réutilisable)
- **Upload** : guard `file.size` côté front, limite alignée avec `Assert\File(maxSize)` backend

### 5. Invalidation du cache

- Toute mutation qui modifie des données lues ailleurs → `queryClient.invalidateQueries({ ...getXOptions(...) })` dans `onSuccess`

### 6. Tests

- **Functional PHPUnit** obligatoire pour toute nouvelle route API non triviale (contrat HTTP + état DB)
- **Domain pur** → unit PHPUnit sans mock + Foundry pour fabriquer les entités
- **Money math** (calculators, fees, paliers) → property-based via Eris (5-10 services concernés)
- **E2E Playwright** obligatoire pour tout nouveau parcours utilisateur (tunnel, paiement, signature)
- **Composant React de form** (Zod + RHF) → Vitest + RTL + MSW pour les violations 422
- **Composant React simple** (passthrough shadcn) → skip
- **Refactor d'un composant fragile** → safety-net-first : tests qui pinent le comportement actuel **avant** de toucher
- **Mutation testing Infection** sur `src/Domain/` en nightly — détecte les tests bidons
- **Diff OpenAPI** en CI : `make types && git diff --exit-code openapi.yaml assets/lib/api/`

Détails et setup : section 13.

### 7. Quality gate

`/quality` (ou équivalent) doit passer vert avant merge : PHPStan level 8, PHP-CS-Fixer, `doctrine:schema:validate --skip-sync`, `lint:container`, ESLint, Prettier, `tsc --noEmit`.

### Definition of Done

Une feature n'est "faite" que si **tous** ces points sont verts :

- [ ] `/quality` passe
- [ ] `make types` ne produit pas de drift dans `assets/lib/api/` (gate CI : `git diff --exit-code`)
- [ ] `doctrine:schema:validate --skip-sync` OK
- [ ] `lint:container` OK
- [ ] Functional test écrit pour toute nouvelle route API non triviale
- [ ] Property-based test (Eris) ajouté pour toute nouvelle logique de calcul money / fees / paliers
- [ ] Spec Playwright ajoutée pour tout nouveau parcours utilisateur
- [ ] Feature testée en navigateur — golden path + au moins un edge case
- [ ] `#[IsGranted]` et `format: 'json'` présents sur les nouvelles routes `/api/`
- [ ] Aucun anti-pattern de la section dédiée (notamment : `useEffect+fetch`, `$request->get()`, `new RetryableHttpClient`, `any` hors RHF `setError`)

---

## PHP 8.4

Le projet requiert PHP ≥ 8.4. Utiliser les features modernes : nullable explicite (`?Type $param = null`, l'implicite est déprécié), asymmetric visibility (`public private(set)`), property hooks, `array_find()`/`array_any()`/`array_all()`.

---

## 1. Structure `src/`

```
src/
├── Controller/          # Orchestration : câble Domain + Service + Api
├── Domain/              # Règles métier pures (voir section 2)
├── Dto/                 # DTOs pour les requêtes API (MapRequestPayload, MapQueryString)
├── Entity/              # Entités Doctrine
├── Repository/          # Queries Doctrine (inclut les queries spécialisées)
├── Service/             # Exécution : persist, API calls, uploads, PDF... (voir section 5)
├── Api/                 # APIs externes authentifiées (Hubspot, Discord...)
├── Message/             # DTOs pour Messenger (voir section 15)
├── MessageHandler/      # Handlers async pour les messages (voir section 15)
├── Command/             # Commandes console (avec #[AsCronTask] pour le scheduler)
├── Form/                # Symfony Form types
├── EventListener/       # Listeners Doctrine/HTTP
├── Factory/             # Création d'entités avec init complexe
├── Twig/                # Extensions Twig
└── Security/            # Auth handlers
```

### Sens des dépendances

```
Controller  →  Domain + Service + Api + Repository + Dto
Command     →  Domain + Service + Api + Repository
Service     →  Domain + Api + Repository
Domain      →  Entity uniquement (+ autres Domain)
```

Domain/ ne dépend de rien d'autre. Service/ peut appeler Domain/. Le Controller câble tout.

---

## 2. Domain/ — Les règles

Chaque sous-dossier de `Domain/` est un **contexte métier**.

### Pureté = sens des dépendances, pas absence d'attributs

La règle qui compte : **Service → Domain ✓**, **Domain → Service ✗**. Service va chercher les données et appelle une méthode pure de Domain ; Domain ne demande jamais à un Service d'aller chercher quelque chose.

Concrètement, un constructor de Domain **n'injecte pas** : Repository, `EntityManagerInterface`, `HttpClientInterface`, `LoggerInterface`, `Filesystem`, autre `Service`, classe `Api/`, `UrlGeneratorInterface`. Tout ce dont Domain a besoin lui est passé en paramètre de méthode.

En revanche, **les attributs framework Symfony sont autorisés librement dans Domain** — au même titre que `Entity/` utilise `#[ORM\Column]` :

- `#[Assert\…]` (Validator)
- `#[Groups]` (Serializer)
- `#[OA\…]` (Nelmio OpenAPI)

C'est de la metadata déclarative, pas une dépendance runtime. Le projet est et reste Symfony — pas d'effort à faire pour rester « framework-agnostic ».

**Domain/ répond aux questions :** « est-ce que cette étape est complétable ? », « quel score a cet utilisateur ? », « quel manager pour ce département ? »

**Si une classe a besoin d'aller chercher des données** (repository, API, filesystem), elle va dans Service/. Si elle contient aussi des règles métier pures, extraire ces règles dans une classe Domain/ séparée et les appeler depuis le Service.

### DTOs métier dans Domain

Les DTOs qui représentent des **structures de données métier** (input/output d'un endpoint, payload de cron, format d'échange avec un partenaire) ont leur place dans `Domain/<Context>/` à côté des Rules et Calculators du même contexte. Ce sont des objets de domaine au même titre qu'une entité. Ils peuvent porter `#[Assert\…]`, `#[OA\…]`, `#[Groups]` sans souci.

```php
// src/Domain/Jdmf/JdmfImportRequestInput.php
namespace App\Domain\Jdmf;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

class JdmfImportRequestInput
{
    #[Assert\NotBlank, Assert\Uuid]
    #[OA\Property(type: 'string', format: 'uuid')]
    public string $farmUuid;
}
```

`src/Dto/` reste utilisé pour les **DTOs techniques génériques** sans contexte métier clair (filtres GET réutilisés entre plusieurs entités, payloads d'orchestration cross-cutting). Si tu hésites : `Domain/<Context>/` quand le DTO décrit un échange métier, `src/Dto/` quand il décrit un échange technique.

### Enums PHP

Pour tout ensemble fini de valeurs métier. Le label vit dans l'enum via `label()`.

```php
enum AdvertStatus: string
{
    case Published = 'published';
    case Pending = 'pending';
    case Refused = 'refused';

    public function label(): string
    {
        return match ($this) {
            self::Published => 'Publié',
            self::Pending => 'En attente',
            self::Refused => 'Refusé',
        };
    }

    public static function forForm(): array
    {
        return array_combine(
            array_map(fn(self $c) => $c->label(), self::cases()),
            array_map(fn(self $c) => $c->value, self::cases()),
        );
    }
}
```

Doctrine supporte les enums nativement :

```php
#[ORM\Column(enumType: AdvertStatus::class)]
private AdvertStatus $status = AdvertStatus::Pending;
```

**Nommage** : singulier (`AdvertStatus`, `MemberType`).

#### Enums exposés dans le back office (EasyAdmin v5)

Deux règles pour ne pas fighter le framework :

1. **Laisser EA gérer le round-trip.** Dès que la colonne Doctrine a `#[ORM\Column(enumType: MyEnum::class)]`, `ChoiceField::new('monChamp')` détecte les cases tout seul. Ne pas ajouter `setChoices()` ni `choice_value()` — ça casse l'edit form (cf. LAGRANGE-3Q / LAGRANGE-4V, où un `choice_value` polymorphe avait dû être ajouté pour rattraper un `setChoices()` redondant).

2. **Implémenter `Symfony\Contracts\Translation\TranslatableInterface` sur l'enum** pour que EA appelle `trans()` et affiche un libellé humain au lieu du raw value. On réutilise `label()` :

   ```php
   use Symfony\Contracts\Translation\TranslatableInterface;
   use Symfony\Contracts\Translation\TranslatorInterface;

   enum AdvertStatus: string implements TranslatableInterface
   {
       case Published = 'published';
       // ...

       public function label(): string { /* ... */ }

       public function trans(TranslatorInterface $translator, ?string $locale = null): string
       {
           return $this->label();
       }
   }
   ```

   Pas besoin de fichiers de traduction : `trans()` renvoie directement la string.

### Données de référence

Pour les grands jeux de données (départements, régions) qui ne sont pas des enums :

```php
final class Departments
{
    public const ALL = ['01' => 'Ain', '02' => 'Aisne', /* ... */];
    public static function get(string $code): string { /* ... */ }
}
```

### Règles métier (conditions, validations)

```php
class StepRules
{
    public function isCompletable(UserStep $userStep): bool
    {
        return $userStep->getUserActions()
            ->filter(fn(UserAction $a) => !$a->getIsCompleted() && $a->getRelatedAction()->getIsMandatory())
            ->isEmpty();
    }
}
```

### Calculator

Calculs métier sans side effect. **Un Calculator reçoit toutes ses données en paramètre** — il ne dépend jamais d'un repository ou d'un service. C'est ce qui le rend pur et testable sans mock.

```php
class ScoreCalculator
{
    public function calculate(array $answers): array { /* ... */ }
}
```

**Si un calcul a besoin de données d'un repository**, ne pas mettre le repository dans le Calculator. Créer un Service/ qui va chercher les données et appelle le Calculator :

```php
// Domain/ — pur, testable
class ScoreCalculator
{
    public function calculate(array $answers): array { /* ... */ }
}

// Service/ — orchestre : va chercher les données, délègue le calcul
class ScoreService
{
    public function __construct(
        private readonly AnswerRepository $answerRepository,
        private readonly ScoreCalculator $calculator,
    ) {}

    public function computeForUser(User $user): array
    {
        $answers = $this->answerRepository->findByUser($user);
        return $this->calculator->calculate($answers);
    }
}
```

### Données de référence vs Rules

- **Données de référence** : données statiques sans logique (tableaux, constantes). Ex : liste de départements, codes régions.
- **Rules** : conditions et seuils avec des méthodes qui retournent un booléen ou une décision. Ex : `isEligible()`, `shouldNotify()`.

### Resolver

Résout une valeur métier à partir d'une clé.

```php
class ProjectManagerResolver
{
    public function getByDepartment(string $department): ?string { /* ... */ }
}
```

### Exceptions Domain

```php
class StepNotCompletableException extends \DomainException
{
    public function __construct()
    {
        parent::__construct("Toutes les actions obligatoires ne sont pas complétées.");
    }
}
```

---

## 3. Controller — L'orchestrateur

Le controller câble Domain + Service + Api. C'est le premier endroit où on met l'orchestration.

Pour du **CRUD simple** (persist + flush sans logique), le controller utilise directement l'EntityManager. Pas besoin de passer par un Service/ pour un simple save.

```php
// CRUD simple → EntityManager directement dans le controller
$this->entityManager->persist($notification);
$this->entityManager->flush();
return $this->json(['ok' => true], 201);

// Logique métier → Domain + Service
public function completeStep(UserStep $userStep): Response
{
    // Règle métier (Domain)
    if (!$this->stepRules->isCompletable($userStep)) {
        $this->addFlash('error', 'Actions obligatoires incomplètes.');
        return $this->redirectToRoute('...');
    }

    // Exécution (Service)
    $this->stepHandler->complete($userStep);

    // Appel externe (Api)
    $this->notificationApi->notify($userStep->getUser());

    return $this->redirectToRoute('...');
}
```

### `format: 'json'` obligatoire

**Toutes** les routes `/api/` doivent avoir `format: 'json'`. Sans ça, les erreurs 422 arrivent en HTML au lieu de JSON et le frontend ne peut pas les parser. C'est une source de bugs fréquente.

```php
#[Route('/api/mon-endpoint', methods: ['POST'], format: 'json')]
```

### Sécurité des routes API — `#[IsGranted]`

Les routes `/api/` ne sont pas protégées par `access_control` (qui fonctionne par pattern d'URL). Utiliser `#[IsGranted]` au niveau de la méthode ou de la classe :

```php
#[IsGranted('ROLE_USER')]
#[Route('/api/parcours/upload-image/{fieldId}', methods: ['POST'], format: 'json')]
public function uploadProjectImage(string $fieldId, ...): Response { /* ... */ }
```

Pour les controllers dont **toutes** les routes nécessitent le même rôle, mettre `#[IsGranted]` au niveau de la classe :

```php
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    // Toutes les méthodes héritent de #[IsGranted('ROLE_USER')]
}
```

### Accès aux données de la requête

Ne pas utiliser `$request->get()` (supprimé en Symfony 8.0). Accéder aux bags directement :

```php
$request->query->get('page');       // query string (?page=2)
$request->request->get('field');    // body (POST)
$request->attributes->get('id');    // route params (aussi via paramètre typé)
```

Préférer les attributs de mapping (`#[MapRequestPayload]`, `#[MapQueryString]`) à l'accès direct aux bags.

### Quand extraire dans Service/ ?

**Commence toujours dans le controller.** La taille du controller n'est pas un problème en soi — un controller avec beaucoup d'actions CRUD bien organisées peut être long sans que ce soit un souci. Extrait dans un `Service/*Handler` quand :

1. **Plusieurs endroits font la même chose** — le même combo d'appels est dupliqué dans 2+ controllers, ou dans un controller + une command
2. **Le controller contient de la logique métier extractable** — calculs, validations complexes, orchestration multi-étapes qui serait plus claire dans un service dédié

```php
// Service/StepCompletionHandler.php — justifié : appelé depuis Controller ET Command
class StepCompletionHandler
{
    public function handle(UserStep $userStep): void
    {
        if (!$this->stepRules->isCompletable($userStep)) {
            throw new StepNotCompletableException();
        }
        $userStep->setIsCompleted(true);
        $this->entityManager->flush();
        $this->externalApi->sync($userStep->getUser());
        $this->notificationApi->notify($userStep->getUser());
    }
}
```

### Sous-modules

Quand une feature a 3+ controllers :

```
Controller/
├── Product/
│   ├── ProductController.php
│   ├── ProductReviewController.php
│   └── FavoriteProductController.php
```

---

## 4. Dto/ — Les payloads API

DTOs pour `#[MapRequestPayload]`, `#[MapQueryString]`, et `#[MapUploadedFile]`. Cf. `docs/reactony.md` pour les patterns détaillés.

### Quand créer un DTO ?

- **Filtres GET** : `#[MapQueryString]` sur un DTO (les filtres ne sont pas une entité)
- **Écriture POST, entité dédiée** : pas de DTO, utiliser l'entité directement
- **Écriture POST, sous-ensemble d'une grosse entité** : DTO allowlist + `ObjectMapper` (cf. ci-dessous)
- **Écriture POST, pas d'entité** : DTO simple (ex. `BugReportPayload`, `ChatbotQuestionPayload`)

### DTO allowlist + ObjectMapper (update partiel)

Quand le payload met à jour un sous-ensemble de champs sur une entité existante (ex. profil User), un DTO sert de **liste blanche** et l'`ObjectMapper` (`symfony/object-mapper`) mappe les champs vers l'entité :

```php
use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: User::class)]
class SaveProfilePayload
{
    public ?string $firstName;    // Non initialisé si absent du JSON → ignoré par ObjectMapper
    public ?string $lastName;
    public ?string $phone;
}
```

**Important** : pas de constructor, pas de `= null`, pas de `readonly`. Les propriétés restent non initialisées quand le JSON ne les contient pas.

L'ObjectMapper supporte aussi des transformations avancées :

```php
#[Map(target: 'emailAddress')]     // mapper vers un nom de propriété différent
public ?string $email;

#[Map(transform: 'strtolower')]    // transformer la valeur avant mapping
public ?string $username;

#[Map(if: false)]                  // ignorer cette propriété
public ?string $debugOnly;
```

```php
public function save(
    #[MapRequestPayload] SaveProfilePayload $payload,
    ObjectMapperInterface $objectMapper,
): Response {
    $objectMapper->map($payload, $this->getUser());
    // ...
}
```

### Coercion chaînes vides → `null` pour les enums nullables

Les formulaires React (react-hook-form) défaultent les champs non initialisés à `""`. Le `BackedEnumNormalizer` de Symfony appelle `Enum::from('')` qui throw `ValueError`, résultant en un 500 côté user. Pour tout champ enum-backed nullable exposé à un form React, coerce l'empty string en `null` avant la dénormalisation :

```php
public function save(Request $request, DenormalizerInterface $denormalizer): Response
{
    $data = $request->getPayload()->all();

    // React-hook-form envoie "" quand un select enum n'est pas rempli.
    // MemberType::from('') throw ValueError → 500 sans le coerce.
    foreach (['type', 'ppHorizonInstallation'] as $enumField) {
        if (isset($data[$enumField]) && '' === $data[$enumField]) {
            $data[$enumField] = null;
        }
    }

    $denormalizer->denormalize($data, User::class, 'array', [
        AbstractNormalizer::OBJECT_TO_POPULATE => $this->getUser(),
        AbstractNormalizer::GROUPS => ['user:write'],
    ]);
    // ...
}
```

Alternative côté front : omettre la clé quand elle est vide. Le backend reste défensif.

### Upload de fichiers — `#[MapUploadedFile]`

Pour les endpoints recevant un fichier, utiliser `#[MapUploadedFile]` au lieu de `$request->files->get()` :

```php
public function upload(
    #[MapUploadedFile(name: 'avatar', constraints: [new Assert\NotNull(), new Assert\File(mimeTypes: ['image/*'])])]
    UploadedFile $file,
): Response {
    // ...
}
```

Si l'endpoint a aussi des données texte : mettre les identifiants dans la route (`{fieldId}`), les champs texte multiples dans un `#[MapRequestPayload]` séparé. Ne pas utiliser `$request->request->get()`.

> **Symfony 8.1+** supportera `UploadedFile` directement dans les DTOs `#[MapRequestPayload]` ([RFC #60440](https://github.com/symfony/symfony/issues/60440)).

---

## 5. Service/ — L'exécution

Service/ fait les choses : persiste, envoie, upload, exporte, scrape, formate.

| Pattern | Usage |
|---------|-------|
| `*Handler` | Orchestration réutilisée (Domain + Api + persist) |
| `*Formatter` | Transformation entité → array quand `#[Groups]` ne suffit pas (données croisées, URLs S3) |
| `*PdfGenerator` | Export PDF |
| `*CsvExporter` | Export CSV |
| `*FileUploadHandler` | Upload de fichiers |
| `Scrapers/*Scraper` | Scraping de sites externes |

Pour la sérialisation simple, préférer `#[Groups]` directement sur l'entité (cf. reactony.md).

Les services utilisent **constructor property promotion** avec `private readonly` systématiquement :

```php
class StepCompletionHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StepRules $stepRules,
        private readonly NotificationApi $notificationApi,
    ) {}
}
```

---

## 6. Api/

APIs externes avec authentification. URLs et credentials dans `.env`, injectés via `services.yaml`.

### Retry configuré au niveau DI, pas dans le service

`config/packages/http_client.yaml` configure déjà des clients scopés (`webflow.client`, `discord.client`, etc.) avec `retry_failed` : 3 tentatives, backoff exponentiel 1s→10s sur les codes `[0, 429, 500, 502, 503, 504]`.

**Ne pas ré-implémenter** de boucle retry par-dessus — ni avec `RetryableHttpClient` dans le service, ni avec un `while` maison. Les services injectent le client scopé directement et laissent la couche DI gérer les retries :

```php
// OK — le client scopé gère le retry
public function __construct(private HttpClientInterface $httpClient) {}

public function estimate(array $data): array
{
    $response = $this->httpClient->request('POST', $url, ['json' => $data]);
    if (200 !== $response->getStatusCode()) {
        $this->logger->error('Upstream non-200', ['status' => $response->getStatusCode()]);
        throw new \RuntimeException('...');
    }
    return $response->toArray(false);
}
```

Cas observé (corrigé) : `FarmEstimationService` wrappait `RetryableHttpClient` dans un `while` maison avec un post-loop check qui throwait sur `$retryCount === MAX_RETRIES` — donc la 3ᵉ tentative réussie à 200 throwait quand même. Résultat : 6 × 500 en prod.

### Rate Limiter sur endpoints publics

Les endpoints qui acceptent du trafic non authentifié ou bon marché à fire massivement doivent être rate-limitées au niveau controller, via `symfony/rate-limiter`. Cas d'école : login, geodata/autocomplete, upload fichier, reset password, tout POST public.

```yaml
# config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        anonymous_api:
            policy: 'sliding_window'
            limit: 60
            interval: '1 minute'
        upload:
            policy: 'token_bucket'
            limit: 10
            rate: { interval: '1 minute', amount: 10 }
```

```php
public function geodata(
    Request $request,
    #[MapQueryString] GeodataQuery $query,
    #[Autowire('@limiter.anonymous_api')] RateLimiterFactory $limiter,
): JsonResponse {
    $limiter->create($request->getClientIp() ?? 'anon')->consume()->ensureAccepted();
    // ...
}
```

Sur une `TooManyRequestsHttpException`, le framework renvoie automatiquement un 429 avec les headers `Retry-After` et `X-RateLimit-*`. Côté front, `handleSdkError` doit catcher 429 comme une erreur non-422 (retry user-side OK).

Endpoints à rate-limiter par défaut dans un projet Symfony+React :
- `POST /api/profile/save` et autres mutations auth'd (limite large, 60/min/user)
- `POST /api/*/upload` (limite serrée, 10/min)
- `GET /api/*/geodata` / autocompletes externes (limite large car appelé vite côté front debounced)
- Endpoints qui tapent une API payante (OpenAI, Hubspot forms, etc.)

### Webhook component — pattern cible pour les webhooks entrants

Quand un projet accumule 3+ webhooks entrants différents (Webflow, Hubspot, Stripe, Mailjet…), migrer depuis les controllers bespoke vers le `symfony/webhook` component. Bénéfices : abstraction uniforme (`AbstractRequestParser` + `RemoteEvent` + `#[AsRemoteEventConsumer]`), retry async via `remote_event` transport, signature-check standardisée.

Pas de parser built-in pour Webflow/Hubspot/Stripe — on écrit le sien. Tant qu'on n'a que 1-2 webhooks, la controller bespoke reste acceptable, mais la convention cible en Symfony 8 est le component. Ne pas ajouter un 3ème webhook custom sans évaluer la migration.

---

## Logging & Sentry

Le handler Sentry Monolog est configuré à niveau `ERROR` (cf. `config/packages/sentry.yaml`). Les niveaux inférieurs ne remontent **pas** à Sentry.

| Niveau | Destination | À utiliser pour |
|---|---|---|
| `$logger->notice()` | Logs Clever uniquement | Info debug, traces |
| `$logger->warning()` | Logs Clever uniquement | Anomalie non bloquante (upstream flaky, retry) |
| `$logger->error()` | Logs Clever + **Sentry** | Tout ce qui mérite qu'un humain regarde |
| `$logger->critical()` | Logs Clever + **Sentry** | Erreur bloquante / data corruption |

Règle : si on attend d'un humain qu'il réagisse, c'est `error`. Sinon c'est `warning` ou `notice`.

### Exceptions ignorées globalement

`ignore_exceptions` dans `sentry.yaml` filtre les exceptions qui ne sont pas des bugs applicatifs :

- `Symfony\Component\HttpKernel\Exception\NotFoundHttpException` — 404 = "resource not found", pas un bug. Ça reste dans les access logs Clever si audit nécessaire.
- `Symfony\Component\ErrorHandler\Error\FatalError` + `FatalErrorException` — remontés par PHP, déjà capturés par les autres handlers.

Pour ajouter une classe à ignorer, éviter les catégories trop larges (ex. ignorer toutes les `HttpException` masquerait de vrais bugs). Préférer la classe précise.

### Catch upstream + log + swallow

Pattern pour les APIs externes non critiques (Webflow, Hubspot, Airtable) : catch, log `error`, ne pas relancer. Le cron suivant ré-essaiera naturellement.

```php
try {
    $this->hubspotApi->updateContact($email, $data);
} catch (\Exception $e) {
    $this->logger->error('HubSpot updateContact failed', [
        'email' => $email,
        'error' => $e->getMessage(),
    ]);
    // swallow — ne pas bloquer le flow user, Sentry a capturé via le handler monolog
}
```

---

## 7. Repository/

Contient **toutes** les requêtes liées à une entité. Pas de couche Finder séparée.

```php
class StepRepository extends ServiceEntityRepository
{
    public function findByUserAndVersion(User $user, string $version): array { /* ... */ }

    public function findByStepIdOrFail(string $stepId): Step
    {
        return $this->findOneBy(['stepId' => $stepId])
            ?? throw new NotFoundHttpException();
    }
}
```

---

## 8. Entity/

- **Trait `Timestampable`** sur toutes les entités métier — `DateTimeImmutable` partout (pas de `DateTime` mutable)
- **TypedFieldMapper** (Doctrine ORM 3.x) : le type de colonne est inféré du type PHP. Pas besoin de `type:` explicite si la propriété est typée :

```php
// Doctrine ORM 3.x — le type est inféré depuis le type PHP
#[ORM\Column]
private \DateTimeImmutable $createdAt;

#[ORM\Column]
private string $title;

// Type explicite uniquement si le type PHP ne suffit pas (ex. text vs string)
#[ORM\Column(type: 'text')]
private string $description;
```

- **Native lazy objects** : activer `$config->enableNativeLazyObjects(true)` pour PHP 8.4+ — élimine la code generation des proxies Doctrine (plus performant, plus simple)
- **Enums Doctrine** : `#[ORM\Column(enumType: MonEnum::class)]`
- **Getters calculés** simples : OK si ça ne dépend que de `$this` (`isExpired()`, `getFullName()`)
- Logique qui dépend d'autres entités ou services → dans Domain/

---

## 9. EventListener/

Pour les actions automatiques déclenchées par le framework (pas par le code métier) :

- **Login** : tracker la dernière connexion
- **Maintenance** : bloquer les requêtes si le site est en maintenance
- **Analytics** : logger les pages vues

Ne pas utiliser pour de la logique métier. Si c'est une règle ("quand X se passe, faire Y"), c'est dans Domain/ ou Service/.

---

## 10. Twig/

Extensions Twig = **affichage uniquement**. Formater un département, une catégorie, un emoji.

Ne pas y mettre de logique métier ni de requêtes. Si c'est un calcul, c'est dans Domain/. L'extension Twig ne fait qu'appeler le Domain et retourner le résultat formaté.

Utiliser les attributs `#[AsTwigFunction]` / `#[AsTwigFilter]` au lieu de `AbstractExtension` + `getFunctions()`/`getFilters()`. Avantage : **lazy-loading** (la classe n'est instanciée que quand la fonction/filtre est utilisé).

```php
class AppExtension
{
    #[AsTwigFunction('formatDepartment')]
    public function formatDepartment(string $id): string { ... }

    #[AsTwigFilter('unslug')]
    public function unslug(string $text): string { ... }

    #[AsTwigTest('expired')]
    public function isExpired(\DateTimeImmutable $date): bool { ... }
}
```

---

## 11. Command/

Les commandes sont des orchestrateurs, comme les controllers. Mêmes règles : Domain + Service + Api directement, EntityManager pour du CRUD simple, extraction dans Service/ si ça se répète.

`#[AsCommand]` est **obligatoire** (Symfony 8.0 a supprimé `getDefaultName()`/`getDefaultDescription()`).

Utiliser le pattern **invokable** : pas de `extends Command`, pas de `execute()`, pas de `parent::__construct()`. La logique va dans `__invoke()`, les arguments/options sont des attributs `#[Argument]`/`#[Option]` sur les paramètres.

> `Command` est toujours importé pour les constantes `SUCCESS`/`FAILURE` — c'est le seul usage.

```php
#[AsCommand(name: 'app:my-command', description: 'Does something')]
class MyCommand
{
    public function __construct(
        private readonly MyService $service,
    ) {}

    public function __invoke(SymfonyStyle $io, #[Option] bool $dryRun = false): int
    {
        // ...
        return Command::SUCCESS;
    }
}
```

### Enums dans les arguments

Les enums PHP sont supportés nativement comme type d'argument/option :

```php
public function __invoke(
    SymfonyStyle $io,
    #[Argument(description: 'Export format')] ExportFormat $format,
): int { /* ... */ }
```

### Commandes complexes — `#[MapInput]`

Quand une commande a beaucoup d'arguments/options, les grouper dans un DTO avec `#[MapInput]` :

```php
class CreateUserInput
{
    #[Argument(description: 'The username')]
    public string $username;

    #[Option(description: 'Activate immediately')]
    public bool $activate = false;
}

#[AsCommand(name: 'app:create-user')]
class CreateUserCommand
{
    public function __invoke(SymfonyStyle $io, #[MapInput] CreateUserInput $input): int
    {
        // $input->username, $input->activate
        return Command::SUCCESS;
    }
}
```

**Ce qui reste dans une Command** : progress bar, retry/reconnect DB, batch sizing, log orchestration, gestion des arguments CLI.

**Ce qui part dans Service/** : parsing/scraping, règles métier, persistence complexe, envoi de notifications. Si une Command fait plus de ~100 lignes de logique métier, c'est un signal d'extraction.

---

## 12. Factory/

Quand la création d'une entité nécessite plus qu'un simple `new` : initialisation de relations, valeurs par défaut calculées, création en cascade.

Pour un simple `new Entity()` avec quelques setters, pas besoin de Factory.

---

## 13. Testing

Stack standard, partagée par tous les projets Symfony+React. Pas de "léger" pour les uns, "lourd" pour les autres — la même chose partout. Solo dev sur des plateformes critiques (argent, PII, audit gov), le filet doit être maximal et homogène.

### La pyramide

| Couche | Outil | Rôle | Vitesse |
|---|---|---|---|
| Unit | **PHPUnit** + Domain pur | calculators, enums, value objects, sans DB | très rapide |
| Property-based | **Eris** sur Domain money math | `InvestmentAmountComputer`, `DiscountCodeApplier`, fees | rapide |
| Integration | **PHPUnit** + DB transactionnelle (DAMA bundle) | services orchestrant Doctrine, repos, listeners | rapide |
| Functional | **PHPUnit** `WebTestCase` + `KernelBrowser` | contrat HTTP des endpoints API | moyen |
| E2E | **Playwright** + **`@axe-core/playwright`** | parcours utilisateur multi-pages, a11y | lent |
| Mutation | **Infection** sur `src/Domain/` | détecte les tests qui passent mais testent rien | très lent (nightly) |
| Contract drift | `make types && git diff --exit-code openapi.yaml assets/lib/api/` en CI | refuse un PR qui drift le contrat front/back | instantané |

Couches 1-4 et 7 tournent sur **chaque PR**. Couches 5-6 tournent en **nightly** sur main, les régressions ouvrent une issue.

### Quand ne pas tester

- getters/setters, mappers 1-pour-1, wrappers Doctrine passthrough
- configuration pure (classes qui ne font que exposer des env vars)
- code mort ou déprécié qui va disparaître

Écrire un test parce que la complexité du code le justifie, pas parce qu'on a un fichier à toucher. Si un service orchestre 6 autres services avec beaucoup de plomberie, c'est souvent le signe qu'il faut le découper — pas qu'il faut écrire un test qui mocke tout.

### Foundry — factories de tests

Standard Symfony moderne pour fabriquer des entités de test. Remplace les fixtures à la main et les `$entity = new Entity(); $entity->setX(...);` répétés dans les `setUp()`.

```bash
composer require --dev zenstruck/foundry
```

```php
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

final class InvestmentFactory extends PersistentObjectFactory
{
    public static function class(): string { return Investment::class; }

    protected function defaults(): array
    {
        return [
            'relatedUser' => UserFactory::new(),
            'amount' => self::faker()->numberBetween(100, 10_000),
            'shares' => self::faker()->numberBetween(1, 100),
            'fonciere' => Fonciere::LES_FEVES_1_TECH_ID,
            'status' => InvestmentStatus::STATUS_IDENTITY,
            'createdAt' => new \DateTime(),
        ];
    }
}

// Dans un test :
$investment = InvestmentFactory::createOne(['amount' => 5000, 'status' => InvestmentStatus::STATUS_PAYMENT]);
$batch = InvestmentFactory::createMany(10, ['fonciere' => Fonciere::LES_FEVES_2_TECH_ID]);
```

Une factory par entité critique. Les autres entités (passthrough) peuvent rester en `new Entity()` direct.

### DAMA Doctrine Test Bundle — rollback transactionnel auto

`dama/doctrine-test-bundle` wrappe **chaque test** dans une transaction et rollback au tearDown. Plus besoin d'écrire une `DatabaseTransactionTestCase` à la main, plus besoin de wipe entre tests, et la suite tourne 5× plus vite.

```bash
composer require --dev dama/doctrine-test-bundle
```

`config/bundles.php` :

```php
return [
    // …
    DAMA\DoctrineTestBundle\DAMADoctrineTestBundle::class => ['test' => true],
];
```

`phpunit.xml.dist` :

```xml
<extensions>
    <bootstrap class="DAMA\DoctrineTestBundle\PHPUnit\PHPUnitExtension"/>
</extensions>
```

Tous les `KernelTestCase` / `WebTestCase` héritent automatiquement du rollback. Les tests deviennent **isolés** et **rapides** sans effort.

### Exemple unit — Domain calculator

```php
public function testCalculateScores(): void
{
    $calculator = new ScoreCalculator();
    $scores = $calculator->calculate(['1' => 'B', '2' => 'C']);
    $this->assertSame(50, $scores[0]['score']);
}
```

### Exemple property-based — math d'argent (Eris)

Tester `InvestmentAmountComputer` à la main rate les edge cases (paliers, arrondis, cumuls). Eris balance des centaines d'entrées random et asserte des **invariants**.

```bash
composer require --dev giorgiosironi/eris
```

```php
use Eris\Generator;
use Eris\TestTrait;

final class InvestmentAmountComputerPropertyTest extends TestCase
{
    use TestTrait;

    public function testTotalAmountIsAlwaysAtLeastSharesPrice(): void
    {
        $this->forAll(
            Generator\choose(1, 1000),                  // shares
            Generator\elements('LES_FEVES_1', 'LES_FEVES_2'),
            Generator\bool(),                           // taxDeduction
        )->then(function (int $shares, string $fonciere, bool $taxDeduction): void {
            $computer = new InvestmentAmountComputer(/* deps */);
            $result = $computer->compute($shares, $fonciere, $taxDeduction);

            // Invariant : le montant total ne peut jamais être inférieur au prix nominal des parts
            $this->assertGreaterThanOrEqual(
                $shares * SharePrice::nominal($fonciere),
                $result->totalAmount,
            );
        });
    }
}
```

Cible : les **5-10 services de calcul money** (pas tout le code). C'est l'outil le plus puissant pour rattraper les bugs financiers qu'aucun test à la main n'écrira.

### Exemple integration — repository / listener avec DB

Avec DAMA actif, plus de boilerplate. Hériter directement de `KernelTestCase` :

```php
final class InvestmentRepositoryTest extends KernelTestCase
{
    use Factories;     // trait Foundry
    use ResetDatabase; // (uniquement si pas de DAMA — sinon DAMA gère)

    public function testFindActiveByUserExcludesArchived(): void
    {
        $user = UserFactory::createOne();
        InvestmentFactory::createOne(['relatedUser' => $user, 'status' => InvestmentStatus::STATUS_VALIDATED]);
        InvestmentFactory::createOne(['relatedUser' => $user, 'status' => InvestmentStatus::STATUS_ARCHIVED]);

        $repo = self::getContainer()->get(InvestmentRepository::class);
        $active = $repo->findActiveByUser($user);

        $this->assertCount(1, $active);
    }
}
```

### Exemple functional — contract test d'un endpoint

```php
final class ValidateSharesTest extends WebTestCase
{
    use Factories;

    public function testValidPayloadCreatesInvestmentInIdentityStatus(): void
    {
        $user = UserFactory::createOne();
        $client = self::createClient();
        $client->loginUser($user->_real());

        $client->request('POST', '/tunnel/validate/shares',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['shares' => 5, 'fonciere' => 'LES_FEVES_2', 'taxDeduction' => true]),
        );

        self::assertSame(200, $client->getResponse()->getStatusCode());
        $investment = self::getContainer()->get(InvestmentRepository::class)
            ->findOneBy(['relatedUser' => $user->_real()]);
        self::assertSame(InvestmentStatus::STATUS_IDENTITY, $investment?->getStatus());
    }
}
```

### E2E avec Playwright

`tests/` couvre le contrat HTTP, mais **n'attrape pas** les régressions multi-pages (le tunnel investissement = 5 étapes, JS interactif, redirections). Playwright tape sur l'app dans Chromium réel.

#### Setup

```bash
pnpm add -D @playwright/test @axe-core/playwright
pnpm exec playwright install --with-deps chromium
```

`playwright.config.ts` à la racine :

```ts
import {defineConfig, devices} from '@playwright/test';

export default defineConfig({
    testDir: './e2e',
    fullyParallel: false,                  // serial = DB déterministe
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: 1,
    reporter: process.env.CI ? 'github' : 'list',

    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'https://localhost:8000',
        ignoreHTTPSErrors: true,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
    },

    projects: [
        // Setup project : seed DB + login, sauve le storageState
        {name: 'setup', testMatch: /auth\.setup\.ts/},
        // Specs publiques (pas d'auth)
        {
            name: 'chromium-public',
            testMatch: /(smoke|registration)\.spec\.ts/,
            use: {...devices['Desktop Chrome']},
        },
        // Specs authentifiées : réutilisent le storageState
        {
            name: 'chromium-auth',
            testIgnore: /(smoke|registration|auth\.setup)\.(spec|ts)/,
            use: {
                ...devices['Desktop Chrome'],
                storageState: 'e2e/.auth/user.json',
            },
            dependencies: ['setup'],
        },
    ],
});
```

#### Commande de seed dédiée

`src/Command/E2eSeedCommand.php` — **idempotente** (wipe puis insère), prefixe les rows avec `__e2e__` pour isolation, ne dépend d'aucune API externe :

```php
#[AsCommand(name: 'app:e2e:seed', description: 'Seed la DB pour les tests Playwright')]
final class E2eSeedCommand
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {}

    public function __invoke(SymfonyStyle $io): int
    {
        // 1. Wipe : tout ce qui commence par __e2e__
        $this->em->createQuery('DELETE FROM App\Entity\User u WHERE u.email LIKE :p')
            ->setParameter('p', '__e2e__%')->execute();

        // 2. Re-seed
        $user = new User();
        $user->setEmail('__e2e__@e2e.test');
        $user->setPassword($this->hasher->hashPassword($user, 'E2eTestPass1!'));
        // … champs requis
        $this->em->persist($user);
        $this->em->flush();

        $io->success('Seeded.');
        return Command::SUCCESS;
    }
}
```

#### Setup auth Playwright

`e2e/auth.setup.ts` :

```ts
import {test as setup, expect} from '@playwright/test';
import {execSync} from 'node:child_process';

const STORAGE_STATE = 'e2e/.auth/user.json';

setup('seed DB and authenticate', async ({page}) => {
    execSync('php bin/console app:e2e:seed --no-interaction', {stdio: 'inherit'});

    await page.goto('/connexion');
    await page.fill('input[name="_username"]', '__e2e__@e2e.test');
    await page.fill('input[name="_password"]', 'E2eTestPass1!');
    await Promise.all([
        page.waitForURL((url) => !/\/connexion$/.test(url.pathname)),
        page.click('button[type="submit"]'),
    ]);

    await expect(page.locator('input[name="_username"]')).toHaveCount(0);
    await page.context().storageState({path: STORAGE_STATE});
});
```

#### Spec E2E + a11y

```ts
import {test, expect} from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test('investment tunnel — happy path', async ({page}) => {
    await page.goto('/tunnel');
    await page.getByRole('spinbutton', {name: /parts/i}).fill('5');
    await page.getByRole('button', {name: /continuer/i}).click();
    await expect(page).toHaveURL(/\/tunnel\/identity/);

    // a11y inline — les violations cassent le test
    const a11y = await new AxeBuilder({page}).analyze();
    expect(a11y.violations).toEqual([]);
});
```

#### Bypass des intégrations externes en E2E

Mêmes contraintes qu'en `tests/` : **jamais d'appel à HubSpot, ZohoSign, Pennylane, etc.** depuis un E2E. Soit :
- Variables d'environnement `*_API_KEY=` vides → les services tombent dans leurs branches no-op
- Override des services dans `config/packages/test/services.yaml` chargé par l'env de dev quand `APP_ENV=e2e`
- Un wrapper `MockHttpClient` injecté à la place

### Mutation testing — Infection

Couvre la zone "tests qui passent mais ne testent rien". L'outil mute le code (change `>` en `>=`, supprime un `if`, etc.) et vérifie qu'au moins un test casse pour chaque mutation. Si aucun test ne casse → **tu as un test bidon**.

```bash
composer require --dev infection/infection
```

`infection.json5` :

```json5
{
    source: { directories: ['src/Domain'] },     // Domain seul — controllers et services apportent du bruit
    timeout: 30,
    mutators: { '@default': true },
    minMsi: 70,
    minCoveredMsi: 80,
}
```

Lancement :

```bash
vendor/bin/infection --threads=4 --show-mutations
```

Tourne en **nightly** (lent), pas sur chaque PR. Au début : pas de gate strict — observer le score sur 2 semaines, fixer un seuil défendable, **ensuite** gater dans CI.

### Contract drift — diff OpenAPI en CI

Le SDK frontend est généré depuis `openapi.yaml` (Nelmio) via `make types`. Si le runtime backend drift du dump checked-in, le front casse silencieusement. Gate gratuit en CI :

```bash
make types
git diff --exit-code openapi.yaml assets/lib/api/
```

Si la commande échoue, le PR a oublié de regénérer le SDK ou a introduit un breaking change non assumé. Aucune dépendance Python/Node supplémentaire.

### Safeguard obligatoire : `tests/bootstrap.php` refuse les DB distantes

Un test qui pointe par mégarde sur la DB de préprod ou de prod efface tout. Paranoïa utile : au boot, si l'URL sent le distant, `exit 1` avant même que PHPUnit démarre.

```php
$databaseUrl = (string) ($_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? '');
foreach (['clever-cloud', 'production', '.rds.amazonaws.com', /* patterns spécifiques */] as $needle) {
    if (str_contains(strtolower($databaseUrl), $needle)) {
        fwrite(\STDERR, "Refusing to run tests: DATABASE_URL looks remote ($needle)\n");
        exit(1);
    }
}
```

### Mocker les API externes via `MockHttpClient`

Un run de tests ne doit jamais toucher une API externe (HubSpot, Slack, webhooks tiers). Pattern : remplacer le `HttpClientInterface` injecté dans chaque API wrapper par un `MockHttpClient` qui renvoie un `200 {}` pour tout.

À déclarer dans `config/services_test.yaml` (loaded **après** `config/services.yaml` par MicroKernelTrait — donc `config/packages/test/services.yaml` ne suffit **pas**, ce piège a coûté 3 essais) :

```yaml
services:
    test.mock_http_client:
        class: App\Test\TestMockHttpClient  # wrappe MockHttpClient avec une factory qui renvoie '{}'

    App\Api\HubspotApi:
        autowire: false
        arguments:
            $httpClient: '@test.mock_http_client'
            # + tous les autres args de __construct, parce que autowire: false
```

Pour les clients qui ne passent pas par `HttpClientInterface` (ex. Google SDK), mocker au niveau du service dans le `setUp()` du test concerné.

### Safety-net-first avant un gros refactor

Avant de refactorer un composant gros et fragile (> 500 lignes, beaucoup de branches, zéro test), écrire d'abord les tests qui pinent son comportement visible **actuel** : happy path, cas d'erreur, guards métier. Refactorer **ensuite**, en s'assurant que la suite reste verte. Si tu refactores d'abord, tu n'as aucun moyen de savoir que tu n'as rien cassé.

C'est particulièrement vrai pour les composants côté tunnel d'investissement / paiement : une régression silencieuse coûte du CA.

### CI — orchestrer la pyramide

Sur **chaque PR** (GitHub Actions) :

```yaml
jobs:
  quality:
    # PHPStan, CS-Fixer, lint:container, doctrine:schema:validate, ESLint, tsc
  contract-drift:
    # make types && git diff --exit-code openapi.yaml assets/lib/api/
  phpunit:
    # vendor/bin/phpunit (Unit + Integration + Functional + Property-based via Eris)
  vitest:
    # pnpm test
  playwright:
    # pnpm test:e2e (avec sharding si > 20 specs)
```

**Nightly** sur `main` :

```yaml
jobs:
  infection:
    # vendor/bin/infection --threads=4
    # Si MSI < seuil : ouvre une issue automatique
```

Pour Playwright sur grosses suites : sharder la matrice (`shardIndex: [1,2,3,4]`, `shardTotal: 4`) puis `merge-reports` job qui agrège les blob reports.

---

## 14. Quality Assurance — Analyse statique & formatage

L'analyse statique remplace les inspections IDE (PHPStorm, plugin Symfony). Ces outils sont regroupés dans la **skill `/quality`** (Claude Code), à lancer avant de committer ou pour vérifier la qualité en cours de dev.

### PHPStan — analyse statique

PHPStan (v2.x) avec les extensions `phpstan-symfony`, `phpstan-doctrine`, et `phpstan-deprecation-rules`. Level 8 (null-safety strict) est le standard recommandé pour les projets Symfony/Doctrine.

```bash
composer require --dev phpstan/phpstan phpstan/phpstan-symfony phpstan/phpstan-doctrine phpstan/phpstan-deprecation-rules
```

```neon
# phpstan.neon
includes:
    - vendor/phpstan/phpstan-symfony/extension.neon
    - vendor/phpstan/phpstan-doctrine/extension.neon
    - vendor/phpstan/phpstan-deprecation-rules/rules.neon

parameters:
    level: 8
    paths:
        - src
    symfony:
        containerXmlPath: var/cache/dev/App_KernelDevDebugContainer.xml
```

Ce que `phpstan-symfony` apporte (vs PHPStan seul) :
- Types corrects pour `ContainerInterface::get()`, `AbstractController::getParameter()`
- Analyse des commandes Console (types d'arguments/options)
- Inférence de types pour Messenger `HandleTrait`

```bash
vendor/bin/phpstan analyse
```

#### AbstractAppController — typer `getUser()`

`AbstractController::getUser()` retourne `UserInterface|null` — PHPStan ne sait pas que c'est ton entité `User`. Créer un base controller qui type le retour :

```php
abstract class AbstractAppController extends AbstractController
{
    protected function getUser(): User
    {
        $user = parent::getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }
        return $user;
    }
}
```

Tous les controllers héritent de `AbstractAppController` au lieu de `AbstractController`.

#### Types PHP natifs plutôt que PHPDoc

Quand PHP peut exprimer le type nativement, utiliser le type PHP, pas un `@param`/`@return` PHPDoc. Les PHPDoc sont réservés aux types que PHP ne supporte pas (`array<string, mixed>`, `Collection<int, User>`, `list<string>`).

PHP-CS-Fixer convertit automatiquement avec les règles `phpdoc_to_param_type`, `phpdoc_to_return_type`, `phpdoc_to_property_type`.

#### Collections Doctrine — generics

Annoter les propriétés `Collection` avec le type générique pour que PHPStan comprenne les boucles :

```php
/** @var Collection<int, UserAction> */
#[ORM\OneToMany(targetEntity: UserAction::class, mappedBy: 'user')]
private Collection $userActions;
```

### PHP-CS-Fixer — formatage

Applique automatiquement les conventions de formatage (PER Coding Style / Symfony ruleset).

```bash
composer require --dev friendsofphp/php-cs-fixer
```

```bash
# Vérification (/quality) :
vendor/bin/php-cs-fixer fix --dry-run --diff

# Application :
vendor/bin/php-cs-fixer fix
```

### Validations Symfony

```bash
# Mappings Doctrine (remplace la validation temps réel de PHPStorm)
php bin/console doctrine:schema:validate --skip-sync

# Compilation du container DI
php -d memory_limit=256M bin/console lint:container
```

### Psalm — taint analysis (optionnel, en CI)

Psalm détecte les vulnérabilités de sécurité (SQL injection, XSS, command injection) par analyse statique du flux de données — une capacité que PHPStan n'a pas. Recommandé en CI pour les projets qui gèrent de l'input utilisateur.

```bash
composer require --dev vimeo/psalm
vendor/bin/psalm --taint-analysis
```

### Composer audit — vulnérabilités

Vérifie les vulnérabilités connues dans les dépendances PHP.

```bash
composer audit
```

### Récapitulatif

| Outil | Rôle | Quand |
|-------|------|-------|
| PHPStan level 8 | Types, null-safety, logique, dépréciations, inspections Symfony/Doctrine | `/quality` |
| PHP-CS-Fixer | Formatage + conversion PHPDoc → types natifs | `/quality` |
| `doctrine:schema:validate` | Mappings Doctrine | `/quality` |
| `lint:container` | Compilation DI | `/quality` |
| `composer audit` | Vulnérabilités dépendances | `/quality` |
| Psalm taint analysis | Sécurité (SQLi, XSS) | CI |

> Tous ces checks (sauf Psalm) sont regroupés dans la skill globale `/quality` qui auto-détecte le type de projet (Symfony, Next.js, ou les deux). Pour les outils de qualité frontend (ESLint, TypeScript), voir `docs/reactony.md` section Quality Assurance.

### Pre-commit — husky + lint-staged

Le garde-fou universel : **aucun commit ne passe s'il ne respecte pas les règles**, qu'il vienne d'un humain ou d'un agent.

```bash
pnpm add -D husky lint-staged
pnpm husky init
```

`.husky/pre-commit` :

```sh
pnpm lint-staged
```

`package.json` :

```json
"lint-staged": {
    "*.php": [
        "vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --path-mode=intersection --"
    ],
    "*.{ts,tsx}": [
        "eslint --fix",
        "prettier --write"
    ]
}
```

lint-staged ne tourne que sur les fichiers **stagés**, donc c'est rapide même sur un gros projet. Les `fix` / `--write` re-stagent les fichiers auto-corrigés.

> **Gotcha PHP-CS-Fixer** : avec plusieurs chemins en argument (cas lint-staged), il faut `--config=<path>` explicite et `--path-mode=intersection` pour que le finder de la config restreigne correctement aux fichiers passés. Le `--` sépare les options des chemins.

### Checks project-wide dans le pre-commit

lint-staged tourne au niveau fichier. Mais les checks qui ont besoin du projet entier (analyse statique, validation DI, drift des types générés) sont **assez rapides pour pre-commit** sur un projet Symfony moyen — pas d'excuse pour les reléguer en CI uniquement. Mesurer le coût réel avant de décider.

Exemple `.husky/pre-commit` :

```sh
#!/usr/bin/env sh
set -e

pnpm exec lint-staged

pnpm tsc --noEmit
vendor/bin/phpstan analyse --memory-limit=1G --no-progress
php -d memory_limit=256M bin/console lint:container -q
php -d memory_limit=512M bin/console doctrine:schema:validate --skip-sync -q

# Detect drift between generated types and current entities/DTOs
make types > /dev/null
if ! git diff --quiet -- openapi.yaml assets/lib/api/; then
    echo "✖ Generated types drifted. Re-stage with: git add openapi.yaml assets/lib/api/"
    exit 1
fi
```

Ordres de grandeur observés (projet Symfony + React de taille moyenne) :

| Check | Temps typique |
|---|---|
| `lint:container` | ~0.5s |
| `schema:validate --skip-sync` | ~0.3s |
| `make types` + drift | ~2s |
| `tsc --noEmit` | ~8s |
| PHPStan level 8 (full) | ~8s |
| **Total pre-commit** | **~15–20s** |

Pas tolérable pour un projet où tu commits 10 fois par heure, mais pour un rythme feature normal (2–5 commits / feature), c'est le prix de la garantie "zéro régression silencieuse au commit". Si le projet grossit et que ça dépasse ~30s, dégrader vers "PHPStan + tsc en CI uniquement, le reste en pre-commit".

**Seuls les tests (unit + functional) ne vont PAS en pre-commit** — ils peuvent monter à plusieurs minutes. Eux restent en CI.

### Quand lancer `/quality` en session

Lors d'une session de dev assistée par IA, lancer `/quality` **avant de déclarer une tâche terminée** si du code a été modifié. Ça rattrape les erreurs pendant la session (feedback immédiat) et évite qu'elles apparaissent seulement au pre-commit (feedback différé, coûteux à debugger). Le pre-commit reste le filet final — pas le premier recours.

---

## 15. Quand créer quoi ?

| Besoin | Où le mettre |
|--------|-------------|
| Règle métier, condition, validation | `Domain/MonContexte/MonRules.php` |
| Ensemble fini de valeurs | `Domain/MonContexte/MonEnum.php` (PHP enum) |
| Données de référence volumineuses | `Domain/MonContexte/MaDonnee.php` |
| Calcul pur (données en paramètre) | `Domain/MonContexte/MonCalculator.php` |
| Calcul qui a besoin de données (repo) | `Service/MonService.php` → appelle le Calculator |
| Sérialisation entité → JSON (simple) | `#[Groups]` sur l'entité |
| Transformation entité → array (complexe) | `Service/MonFormatter.php` |
| Résolution par lookup | `Domain/MonContexte/MonResolver.php` |
| Erreur métier | `Domain/MonContexte/Exception/MonException.php` |
| Orchestration réutilisée ou complexe | `Service/MonHandler.php` |
| Payload API (sous-ensemble entité) | `Dto/MonPayload.php` + `#[Map(target:)]` + `ObjectMapper` |
| Payload API (pas d'entité) | `Dto/MonPayload.php` + `#[MapRequestPayload]` |
| Filtres GET | `Dto/MonFilterDto.php` + `#[MapQueryString]` |
| API externe authentifiée | `Api/MonServiceApi.php` |
| Scraping / export / upload | `Service/Mon*Handler.php` |
| Query sur une entité | `Repository/MonRepository.php` |
| Création d'entité complexe | `Factory/MonFactory.php` |
| Route HTTP | `Controller/MonController.php` |
| Formulaire Twig | `Form/MonFormType.php` |
| Job planifié | `Command/MonCommand.php` + `#[AsCronTask]` |
| Appel externe async | `Message/MonMessage.php` + `MessageHandler/MonHandler.php` |
| Sécurité route API | `#[IsGranted('ROLE_USER')]` sur méthode/classe |

---

## 16. Messenger — Appels externes async

Les appels vers des services externes (Hubspot, Discord, Slack, emails) sont dispatchés en async via Symfony Messenger. Le controller dispatch un message DTO, le worker le consomme en arrière-plan.

### Transport

Doctrine (PostgreSQL, table `messenger_messages`). Les messages `SendEmailMessage`, `ChatMessage`, `SmsMessage` restent en **sync** car leurs templates reçoivent des entités Doctrine qui ne se sérialisent pas.

### Message — le DTO

`final readonly class` avec uniquement des **scalaires** (int, string, bool). Pas d'entité, pas d'objet complexe — le message doit être sérialisable.

```php
namespace App\Message;

final readonly class SyncProfileToHubspot
{
    public function __construct(
        public int $userId,
    ) {}
}
```

### Handler — l'exécution

`#[AsMessageHandler]` + `final readonly class`. Le handler recharge l'entité depuis la DB par son ID, **vérifie qu'elle existe encore** (elle a pu être supprimée entre le dispatch et le traitement), puis appelle les services existants.

```php
namespace App\MessageHandler;

use App\Message\SyncProfileToHubspot;
use App\Repository\UserRepository;
use App\Service\HubspotSyncHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SyncProfileToHubspotHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private HubspotSyncHandler $hubspotSyncHandler,
    ) {}

    public function __invoke(SyncProfileToHubspot $message): void
    {
        $user = $this->userRepository->find($message->userId);
        if (!$user) {
            return;
        }

        $this->hubspotSyncHandler->handleProfileUpdate($user);
    }
}
```

### Dispatch depuis le controller

Toujours **après** le persist + flush, pour que l'entité soit en DB quand le handler la recharge.

```php
$entityManager->flush();
$bus->dispatch(new SyncProfileToHubspot($user->getId()));
```

### Cas particulier : entité supprimée

Si l'entité sera supprimée juste après le dispatch, passer les données nécessaires en scalaires (ex: email) plutôt que l'ID.

### Retry et failed

3 retries avec backoff exponentiel (1s, 2s, 4s). Après 3 échecs → transport `failed`. Les erreurs CRITICAL remontent dans Sentry.

### Routing (`config/packages/messenger.yaml`)

```yaml
routing:
    Symfony\Component\Mailer\Messenger\SendEmailMessage: sync
    Symfony\Component\Notifier\Message\ChatMessage: sync
    'App\Message\*': async
```

---

## 17. Scheduler — Crons observables

Les crons sont déclarés directement sur les commandes avec `#[AsCronTask]`. Pas de scripts shell, pas de `cron.json` (sauf `cleanup-chrome.sh`).

```php
#[AsCronTask('0 3 * * *', timezone: 'Europe/Paris')]
#[AsCommand(name: 'app:analytics', description: 'Daily analytics')]
class AnalyticsCommand
{
    public function __invoke(SymfonyStyle $io): int
    {
        ini_set('memory_limit', '4096M');
        // ...
        return Command::SUCCESS;
    }
}
```

Le scheduler est consommé par le même worker que Messenger (`scheduler_default` transport). Les erreurs remontent dans Sentry/logs Symfony.

Pour les commandes gourmandes en mémoire, utiliser `ini_set('memory_limit', ...)` dans la commande plutôt qu'un flag PHP CLI.

---

## 18. Anti-patterns interdits

Règles noires, valables partout. Si tu les vois dans le code existant, c'est à refactorer — pas à copier.

**Controller / Route**
- Route `/api/` sans `format: 'json'` — les 422 partent en HTML, le front ne peut pas les parser
- Route `/api/` sans `#[IsGranted]` sur la méthode ou la classe
- `$request->get()` — supprimé en Symfony 8. Utiliser `$request->query`, `$request->request`, `$request->attributes`, ou les attributs de mapping
- Controllers qui étendent `AbstractController` directement et typent `getUser()` comme `UserInterface` — créer un `AbstractAppController` qui retourne l'entité `User` typée et en hériter partout

**Domain / Service**
- Classe `Domain/` qui **injecte (constructor)** un Repository, EntityManager, HttpClient, Logger, Filesystem, autre Service, classe `Api/` ou `UrlGeneratorInterface` — Domain reçoit ses données en paramètre. Les attributs framework (`Assert`, `OA`, `Groups`) restent autorisés.
- Calculator qui accède à un repository — extraire le fetch dans `Service/`, Calculator reste pur
- Service qui contient uniquement une règle métier pure — déplacer dans `Domain/`
- Interface sur un service qui n'a qu'une implémentation — pas d'abstraction préventive
- ValueObject, Aggregate, ou autre cérémonie DDD sans justification concrète

**DTO / Payload**
- DTO allowlist (`ObjectMapper`) avec `constructor`, `= null`, ou `readonly` sur les propriétés — casse le mapping partiel (les champs absents du JSON doivent rester **non initialisés**)
- Filtre GET lu via `$request->query->get()` au lieu d'un DTO + `#[MapQueryString]`
- Upload fichier via `$request->files->get()` — utiliser `#[MapUploadedFile]` avec les contraintes `Assert`

**HttpClient / API externe**
- `new RetryableHttpClient($client)` dans un service — retry se configure au niveau DI (`scoped_clients` + `retry_failed`)
- Boucle `while ($attempts < $max)` autour d'un `$client->request()` — idem, c'est le job du DI
- Endpoint public (login, autocomplete, upload, reset password) sans `RateLimiterFactory`

**Command**
- `extends Command` + `protected function execute()` — pattern invokable obligatoire : pas d'`extends`, `#[AsCommand]`, logique dans `__invoke()`, paramètres typés avec `#[Argument]` / `#[Option]`
- `getDefaultName()` / `getDefaultDescription()` — supprimés en SF 8, utiliser les arguments nommés de `#[AsCommand]`
- Cron déclaré dans un shell script ou `cron.json` — utiliser `#[AsCronTask]` sur la commande

**Entity / Doctrine**
- PHPDoc `@var` / `@param` / `@return` qui duplique un type PHP natif déjà présent — PHP-CS-Fixer supprime
- Propriété `Collection` sans generic (`Collection<int, Entity>`) — PHPStan ne peut pas inférer le type en boucle
- `DateTime` mutable — utiliser `DateTimeImmutable`
- `#[ORM\Column(type: 'string')]` sur une propriété typée `string` — redondant depuis Doctrine ORM 3 (TypedFieldMapper). Type explicite uniquement quand PHP ne suffit pas (`text` vs `string`)

**Logging / Sentry**
- `logger->error()` pour une anomalie non bloquante (upstream flaky, retry réussi) — utiliser `warning` (Sentry ne remonte qu'à partir d'`ERROR`)
- `ignore_exceptions` trop large (ex. `HttpException` entier) — ignorer la classe précise
- Catch + re-throw d'une `RuntimeException` qui ne fait qu'envelopper l'originale sans ajouter d'info

**Messenger**
- Message qui contient une entité Doctrine, un `UploadedFile`, ou un callable — sérialisation fragile, garder des scalaires (ID + reload dans le handler)
- Handler qui suppose que l'entité existe encore — recharger via repo et `if (!$entity) return`
- Dispatch **avant** le `flush()` de l'entité concernée — le handler re-lira avant que la DB soit à jour

**Tests**
- Test qui pointe sur une DB prod/préprod — `tests/bootstrap.php` doit refuser via pattern matching sur `DATABASE_URL`
- Test qui hit une API externe réelle — mocker au niveau DI via `MockHttpClient`
- Refactor > 500 lignes sans test d'existants — écrire le safety-net **avant** de toucher
- Test snapshot sur un rendu complet — casse au moindre changement de classe, zéro signal utile
