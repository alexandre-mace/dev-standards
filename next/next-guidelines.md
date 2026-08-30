# Next Guidelines : sites statiques perso

> **Dernière veille : 24 août 2026** (`/sota-gap`), repartir de cette date au prochain run.
>
> Radar : Instant Navigations (`cacheComponents` + `partialPrefetching`, opt-in, annoncé comme futur défaut) et `transitionTypes` sur `<Link>`. À regarder, pas encore des normes.

| Outil | Version | À savoir |
|---|---|---|
| Next.js | 16.3 | App Router, Active LTS. Releases de sécurité préannoncées : patcher sous une semaine (ex. 16.3.3, critique le 26/08/2026). `catchError`/`retry()` stables pour les error boundaries ; `middleware.ts` déprécié au profit de `proxy.ts` |
| React | 19.2 | |
| `next/image` | 16 | Defaults : `qualities` reduit a `[75]`, cache TTL a 4 h, et une src locale avec query string exige `images.localPatterns` |
| TypeScript | 5.9 | TS 7 natif est GA. Migration 5.9 vers 6.0 puis 7.0 à planifier ; le type-check de `next build` supporte TS 7 depuis 16.3 |
| Tailwind | 4 | PostCSS, zéro `tailwind.config` |
| shadcn | base Base UI, style Nova | Le défaut de l'écosystème depuis juillet 2026 |
| Biome | 2.5 | Lint et format. La filière oxc (oxlint + oxfmt) est l'autre sortie du duo ESLint+Prettier, mais oxfmt est en beta : re-statuer quand il passe stable |
| Kit | `@alexandremace` | ui.alexandremace.fr |
| lucide-react | 1.x | Plus d'icônes de marque |
| Geist | paquet npm | |
| Hébergement | Vercel Hobby | |

## Ce que couvre ce fichier

Les sites Next perso. Si rendre côté serveur n'économise aucun JavaScript parce que tout est interactif, le projet est une application et va sur `tanstack-start/`. Avoir un backend ne change rien à ce choix : ce qui décide, c'est le modèle de rendu.

Deux options se combinent librement, chacune décrite à sa place : le site prend le kit `@alexandremace` ou porte son identité propre (§2), et il ajoute un backend ou n'en a pas (§6).

## Playbook

Ce qu'on ajoute le plus souvent, et par où ça passe.

**Une page** : un dossier sous `app/`, un `page.tsx` Server Component, ses `metadata` exportées. Une route dynamique préremplit ses valeurs avec `generateStaticParams`, sinon elle n'est pas statique.

**Un jeu de données externe** : un `scripts/build-*.mjs` qui écrit du TypeScript typé dans `lib/`, un script `data` dans `package.json`, et des logs de contrôle en fin de run. La page importe la constante, elle ne fetche pas.

**Un composant interactif** (simulateur, filtre, sélecteur) : un composant client, le plus bas possible dans l'arbre. Sa page reste Server Component et lui passe les données cuites en props.

**Un graphique** : le composant de graphe est client, les données arrivent en props depuis le serveur. Les couleurs viennent des tokens `--chart-*` du kit, jamais de valeurs en dur.

**Une donnée utilisateur** : voir §6, c'est le seul cas qui justifie un backend.

## Patterns courants

**Suspense pour ce qui est lent, pas pour ce qui est absent.** Un `loading.tsx` couvre le segment entier ; un `<Suspense>` local isole la seule partie lente. Sur un site statique, la plupart des pages n'ont besoin ni de l'un ni de l'autre.

**`<Activity>` plutôt qu'un démontage** quand un panneau caché doit retrouver son état, ce qui est le cas de tout simulateur à onglets. Stable depuis React 19.2 : l'état, le DOM et la position de scroll survivent, les Effects sont nettoyés.

```tsx
<Activity mode={ongletActif === "transport" ? "visible" : "hidden"}>
  <PanneauTransport />
</Activity>
```

**`useEffectEvent`** pour extraire d'un Effect la logique qui lit props ou state sans les mettre en dépendances. Stable depuis 19.2, jamais appelé en dehors de l'Effect qui le possède.

**Pas de `useMemo` ni de `useCallback` posés par réflexe**, seulement sur un coût mesuré. Le React Compiler les rendrait inutiles, mais il n'est activé sur aucun projet : à statuer à la prochaine veille.

## 1. Scaffolding

```bash
pnpm create next-app@latest <projet> --ts --app --tailwind --biome --no-src-dir --import-alias "@/*" --use-pnpm
```

Ne pas ajouter `--turbopack` : c'est le bundler par défaut depuis Next 16.

**Épingler `packageManager`** dans `package.json`, et commiter le lockfile. Un `package-lock.json` qui apparaît signale que quelqu'un a installé avec le mauvais outil.

**Commiter le bloc auto-géré d'`AGENTS.md`** sans l'éditer : `next dev` le maintient et il pointe la doc de la version installée.

**Lister les hôtes réels dans `images.remotePatterns`**, jamais `hostname: "**"` : un joker laisse n'importe qui faire transiter ses images par l'optimiseur du site.

## 2. Composants

**Base UI, jamais React Aria ni Radix**, quel que soit le projet, en style Nova. La composition passe par la prop `render` et les handlers DOM standards. `asChild` n'existe dans aucune des deux bases, c'est un idiome Radix. Migrer en entier un projet resté sur une autre base, jamais deux bases dans un même projet.

Un **projet à identité propre** s'arrête là : CLI shadcn officiel, sans registry.

Un **site d'écosystème** ajoute le kit `@alexandremace`, déclaré dans `components.json` :

```json
"registries": { "@alexandremace": "https://ui.alexandremace.fr/r/{name}.json" }
```

Tout ce qui vient du kit s'installe par le registry, y compris les composants d'écosystème. Jamais de copier-coller entre projets.

**Le kit est la source.** Ne jamais modifier `components/ui/` dans un consommateur : un besoin local est soit un vrai écart à remonter dans le kit puis à propager avec `/propagate-kit`, soit un cas d'usage à styler via `className`. Les composants propres au projet vivent à la racine de `components/`.

**Icônes** : `lucide-react` 1.x n'a plus d'icônes de marque, SVG local dans `components/icons.tsx`.

**Variantes** : CVA, comme dans le kit.

## 3. Styling

**Mono-thème clair.** Ajouter cette ligne à `app/globals.css`, après les `@import` :

```css
@custom-variant dark (&:is(.dark *));
```

Elle neutralise les `dark:` du stock shadcn, et la classe `.dark` n'est jamais posée. Un thème sombre est une décision de projet, pas un défaut.

**Tailwind 4 se configure en CSS**, zéro `tailwind.config.js`. Les tokens vivent en OkLCh dans `:root`, remappés en `--color-*` dans `@theme inline`. Piège : `shadcn add theme` ne réécrit pas un `globals.css` existant, donc ajouter tout nouveau token à la main aux deux endroits.

La palette par défaut vient du kit. Un projet peut assumer la sienne en re-déclarant les tokens, sans toucher aux composants.

## 4. Layout et SEO

**Server Components par défaut.** Réserver `"use client"` aux composants qui portent de l'état ou de l'interaction.

**La langue est une décision de projet**, prise au démarrage et tenue partout : contenu, commentaires, et déclarée à la fois dans `<html lang>` et dans `openGraph.locale`. Les identifiants métier dans la langue du domaine sont tolérés dans les scripts et les modèles (`Pays`, `ANNEE`, `donnees`).

**Geist par le paquet `geist`**, jamais par `next/font/google` : `import { GeistSans } from "geist/font/sans"`, variables posées sur `<html>`, `font-sans` sur le `<body>`.

**Chaque site vit sur son domaine canonique** (`<projet>.alexandremace.fr` ou `<projet>.climatelab.fr`), déclaré dans `metadataBase`. Jamais un `*.vercel.app` : le domaine canonique fait foi dans les metadata, les OG et les redirects. Factoriser la description en const, elle sert trois fois.

**OG image générée** : `app/opengraph-image.tsx` avec `ImageResponse`, 1200×630, jamais une image statique qui périme.

**Icônes** : `app/icon.svg`, que Next sert en `sizes="any"` à toutes les tailles, plus `app/apple-icon.png` en 180×180 pour l'écran d'accueil iOS. Next génère les balises `<link>` seul. Un `icon.tsx` qui rend une `ImageResponse` quand l'icône se dérive d'un emoji ou d'une initiale. Pas de `favicon.ico`, sauf besoin d'un très vieux navigateur.

## 5. Données cuites

Le contenu vit dans `lib/` en TypeScript typé. Tout ce que la page affiche existe au build : pas de route handlers, pas de server actions, pas de DB, sauf si le site a un backend (§6).

**Petit jeu de données** (fiches projets, modèle) : écrit à la main dans `lib/data.ts`, typé, sources documentées en commentaire d'entête.

**Source externe** (Banque mondiale, OWID) : pipeline `pnpm data`.

- `scripts/build-*.mjs`.
- Sortie générée dans `lib/**/*.ts` avec entête obligatoire : `// Généré par scripts/…, ne pas éditer à la main.`, la source, la date d'extraction, et la licence si elle l'exige (CC BY pour OWID). Un fichier généré édité à la main perd sa modification au prochain run.
- **Logs de contrôle en fin de run** : totaux croisés avec un agrégat de référence, comptes de lignes. C'est le seul filet sur un site sans suite de tests.
- Relancer `pnpm data` avant un déploiement qui dépend de la fraîcheur.

**Fetch runtime : seulement pour l'exploration volontaire**, par exemple un sélecteur de pays sur un graphique. Petits appels ciblés, cache en mémoire, dégradation propre si l'upstream tombe. Jamais `useEffect + fetch` pour du contenu qui pouvait être cuit au build.

## 6. Backend

Un site qui a besoin de comptes ou de données stockées ajoute **Convex** et ses composants officiels `@convex-dev/*` (rate limiting, emails Resend, paiements Stripe), avec **Clerk** pour l'auth.

Les tests deviennent alors obligatoires : Vitest et `convex-test` pour les fonctions backend, Playwright pour les parcours critiques. Le log de contrôle des scripts data ne suffit plus quand un utilisateur peut écrire.

## 7. Qualité et déploiement

**`pnpm build` est le check** : types et génération statique. Le lancer avant de pousser.

**Pousser, c'est déployer** (webhook Vercel sur main) :

- Grouper les pushes. Le plan Hobby plafonne à 100 déploiements par 24 h glissantes, et chaque push de chaque projet consomme un slot.
- La rétention Hobby est de 30 jours : ne jamais compter sur une vieille URL de déploiement comme archive.

Un hébergement qu'on abandonne devient une redirection vers le domaine canonique, jamais un doublon vivant.
