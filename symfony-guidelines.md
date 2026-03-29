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

Chaque sous-dossier de `Domain/` est un **contexte métier**. Domain/ ne dépend jamais de Controller, Service, ou Api — uniquement d'Entity et d'autres Domain.

**Domain/ reçoit ses données, Service/ va les chercher.** Une classe Domain/ ne fait jamais de requête, ne lit jamais un fichier, n'appelle jamais une API. Elle reçoit tout en paramètre, elle décide/calcule/valide, et elle retourne un résultat.

**Domain/ répond aux questions :** "est-ce que cette étape est complétable ?", "quel score a cet utilisateur ?", "quel manager pour ce département ?"

**Si une classe a besoin d'aller chercher des données** (repository, API, filesystem), elle va dans Service/. Si elle contient aussi des règles métier pures, extraire ces règles dans une classe Domain/ séparée et les appeler depuis le Service.

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

Tester les méthodes Domain critiques (règles métier, calculators, scores). Le reste se teste en fonctionnel si nécessaire.

```php
public function testCalculateScores(): void
{
    $calculator = new ScoreCalculator();
    $scores = $calculator->calculate(['1' => 'B', '2' => 'C']);
    $this->assertSame(50, $scores[0]['score']);
}
```

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
# Vérification (en hook) :
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
