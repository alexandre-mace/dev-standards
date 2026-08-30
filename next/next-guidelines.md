# Next Guidelines : sites statiques perso

> **Dernière veille : 24 août 2026** (`/sota-gap`), repartir de cette date au prochain run.
>
> Radar : Instant Navigations (`cacheComponents` + `partialPrefetching`, opt-in, annoncé comme futur défaut) et `transitionTypes` sur `<Link>`. À regarder, pas encore des normes.

| Outil | Version | À savoir |
|---|---|---|
| Next.js | 16.3 | App Router, Active LTS. Releases de sécurité préannoncées : patcher sous une semaine (ex. 16.3.3, critique le 26/08/2026). `catchError`/`retry()` stables pour les error boundaries ; `middleware.ts` déprécié au profit de `proxy.ts` |
| React | 19.2 | |
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

## 1. Scaffolding

Next.js 16, App Router, TypeScript strict. Structure à la racine, pas de `src/` :

```
app/            # pages, layout, icon.tsx, opengraph-image.tsx
components/     # composants du projet
components/ui/  # kit @alexandremace, ne pas éditer
lib/            # données typées, utils (cn)
scripts/        # pipeline data éventuel (*.mjs)
```

**pnpm**, lockfile commité, `packageManager` épinglé dans `package.json`. Un `package-lock.json` qui apparaît signale que quelqu'un a installé avec le mauvais outil.

**`next.config.ts`** typé `NextConfig`, vide par défaut. On n'y ajoute que le nécessaire :

- `images.remotePatterns` si le site charge des images distantes. Lister les hôtes réels, jamais `hostname: "**"`. `images.domains` est déprécié.
- Défauts `next/image` en 16 : `qualities` réduit à `[75]`, cache TTL à 4 h, et une src locale avec query string exige `images.localPatterns`.

**`AGENTS.md` est commité.** Depuis 16.3, `next dev` crée et maintient un bloc auto-géré qui pointe la doc de la version installée. On le versionne tel quel sans l'éditer. C'est aussi le fichier qui porte les conventions du projet, `CLAUDE.md` se réduisant à une ligne `@AGENTS.md`.

**`tsconfig`** : alias `@/*` vers la racine.

**Scripts** : `dev` (avec `--turbopack`), `build`, `start`, `lint`, plus `data` si le projet a un pipeline.

**Biome** pour le lint et le format, un seul binaire et un `biome.json`. `next lint` a disparu en Next 16. Scripts : `"lint": "biome check"` et `"lint:fix": "biome check --write"`. Un site encore en ESLint migre à l'occasion, pas en big-bang.

## 2. Composants

**Base UI, jamais React Aria ni Radix**, quel que soit le projet. La composition passe par la prop `render` et les handlers DOM standards : `asChild` n'existe dans aucune des deux bases, c'est du Radix recopié d'un projet pro. Un projet resté sur une autre base se migre en entier, jamais deux bases dans un même projet.

`lucide-react` 1.x n'a plus d'icônes de marque : SVG local dans `components/icons.tsx`. Les variantes de composants suivent CVA.

Ensuite, deux cas.

**Projet à identité propre** : Base UI stock via le CLI shadcn officiel, style Nova, sans registry.

**Site d'écosystème** : le kit `@alexandremace`. `components.json` déclare la base Base UI style Nova, `iconLibrary: lucide`, `cssVariables: true`, les alias standards, et le registry :

```json
"registries": { "@alexandremace": "https://ui.alexandremace.fr/r/{name}.json" }
```

Installation : `npx shadcn@latest add -y -o @alexandremace/<item>`. Les composants d'écosystème (`made-with-love`, `brand`, `climatelab-badge`, `search-trigger`) passent par le registry aussi, jamais par copier-coller entre projets.

**Le kit est la source.** `components/ui/` ne se modifie jamais dans un consommateur : un besoin local est soit un vrai écart à remonter dans le kit puis à propager avec `/propagate-kit`, soit un cas d'usage à styler via `className`. Les composants propres au projet vivent à la racine de `components/`.

## 3. Styling

Entête canonique de `app/globals.css`, dans cet ordre :

```css
@import "tailwindcss";
@import "tw-animate-css";
@import "shadcn/tailwind.css";

/* Mono-theme clair : la variante dark ne s'active que par classe .dark, jamais posee. */
@custom-variant dark (&:is(.dark *));
```

**Mono-thème clair.** Ce `@custom-variant` neutralise les `dark:` du stock shadcn, et la classe `.dark` n'est jamais posée. Un thème sombre est une décision de projet, pas un défaut.

Tokens en OkLCh dans `:root`, remappés en `--color-*` dans `@theme inline`, échelle de radius dérivée de `--radius: 0.625rem`. Tailwind 4 se configure en CSS : zéro `tailwind.config.js`.

Palette par défaut : celle du kit, fond sable `#FAF8F0`, cartes `#FDFCF8`, primary `#0737FF`, plus `--warning` et `--success`. Un projet peut assumer la sienne en re-déclarant les tokens, sans toucher aux composants.

Piège : `shadcn add theme` ne réécrit pas un `globals.css` existant. Un nouveau token s'ajoute à la main dans `:root` **et** dans `@theme inline`.

Classes conditionnelles via `cn()`, de `lib/utils.ts`.

## 4. Layout et SEO

**Server Components par défaut.** `"use client"` est réservé aux composants qui portent de l'état ou de l'interaction. Une poignée de fichiers client par projet, pas la moitié.

**Français partout** : contenu, metadata (`locale: "fr_FR"`), commentaires. Les identifiants métier français sont tolérés dans les scripts et les modèles (`Pays`, `ANNEE`, `donnees`).

Fonts Geist Sans et Geist Mono via le paquet npm `geist`, variables posées sur `<html>` :

```tsx
<html lang="fr" className={`${GeistSans.variable} ${GeistMono.variable} antialiased`}>
  <body className="font-sans">{children}</body>
</html>
```

**Chaque site vit sur son domaine canonique** (`<projet>.alexandremace.fr` ou `<projet>.climatelab.fr`), déclaré dans `metadataBase`. Jamais un `*.vercel.app` : le domaine canonique fait foi dans les metadata, les OG et les redirects.

```tsx
const description = "…";

export const metadata: Metadata = {
  metadataBase: new URL("https://<projet>.climatelab.fr"),
  title: "…",
  description,
  openGraph: { title: "…", description, type: "website", locale: "fr_FR" },
  twitter: { card: "summary", title: "…", description },
};
```

**OG image générée** : `app/opengraph-image.tsx` avec `ImageResponse`, 1200×630, jamais une image statique qui périme. Depuis 16.2 sa police par défaut est Geist Sans, donc la cohérence est gratuite.

**Favicon** : `app/icon.tsx` généré, ou un emoji en data-URI dans `icons.icon`. Pas de `favicon.ico` par défaut de Next qui traîne.

## 5. Données cuites

Le contenu vit dans `lib/` en TypeScript typé. Tout ce que la page affiche existe au build : pas de route handlers, pas de server actions, pas de DB, sauf si le site a un backend (§6).

**Petit jeu de données** (fiches projets, modèle) : écrit à la main dans `lib/data.ts`, typé, sources documentées en commentaire d'entête.

**Source externe** (Banque mondiale, OWID) : pipeline `pnpm data`.

- `scripts/build-*.mjs`, ESM natif : `node:fs`, `fetch` global, top-level await.
- Sortie générée dans `lib/**/*.ts` avec entête obligatoire : `// Généré par scripts/…, ne pas éditer à la main.`, la source, la date d'extraction, et la licence si elle l'exige (CC BY pour OWID). Un fichier généré édité à la main perd sa modification au prochain run.
- **Logs de contrôle en fin de run** : totaux croisés avec un agrégat de référence, comptes de lignes. Sur un site sans tests, le log de contrôle est le test.
- Relancer `pnpm data` avant un déploiement qui dépend de la fraîcheur.

**Fetch runtime : seulement pour l'exploration volontaire**, par exemple un sélecteur de pays sur un graphique. Petits appels ciblés, cache en mémoire, dégradation propre si l'upstream tombe. Jamais `useEffect + fetch` pour du contenu qui pouvait être cuit au build.

## 6. Backend

Un site qui a besoin de comptes ou de données stockées ajoute **Convex** et ses composants officiels `@convex-dev/*` (rate limiting, emails Resend, paiements Stripe), avec **Clerk** pour l'auth.

Les tests deviennent alors obligatoires : Vitest et `convex-test` pour les fonctions backend, Playwright pour les parcours critiques. Le log de contrôle des scripts data ne suffit plus quand un utilisateur peut écrire.

## 7. Qualité et déploiement

**`pnpm build` est le check** : types, génération statique. Le lancer avant de pousser. Pas de suite de tests sur un site sans backend : la logique critique est dans les scripts data, contrôlée par leurs logs.

**Pousser, c'est déployer** (webhook Vercel sur main). Trois conséquences :

- Grouper les pushes. Le plan Hobby plafonne à 100 déploiements par 24 h glissantes, et chaque push de chaque projet consomme un slot.
- La rétention Hobby est de 30 jours : ne jamais compter sur une vieille URL de déploiement comme archive.
- Une security release Next se patche sur tous les projets sous une semaine.

Un ancien hébergement se recycle en page de redirection vers le domaine canonique, jamais en doublon vivant.

Dev local : serveur déclaré dans `.claude/launch.json`, un port dédié par projet.
