# Next Guidelines : sites statiques perso

> **Dernière veille : 24 août 2026** (`/sota-gap`), repartir de cette date au prochain run.

> Radar : Instant Navigations (`cacheComponents` + `partialPrefetching`, opt-in, annoncé comme futur défaut) et `transitionTypes` sur `<Link>`. À regarder, pas encore des normes.

| Outil | Version | À savoir |
|---|---|---|
| Next.js | 16.3 | App Router, Active LTS. Releases de sécurité préannoncées : patcher sous une semaine (ex. 16.3.3, critique le 26/08/2026). `catchError`/`retry()` stables pour les error boundaries ; `middleware.ts` déprécié au profit de `proxy.ts` |
| React | 19.2 | |
| TypeScript | 5.9 | TS 7 natif est GA. Migration 5.9 vers 6.0 puis 7.0 à planifier ; le type-check de `next build` supporte TS 7 depuis 16.3 |
| Tailwind | 4 | PostCSS, zéro `tailwind.config` |
| shadcn | base Base UI, style Nova | Le défaut de l'écosystème depuis juillet 2026 |
| Biome | 2.5 | Lint et format |
| Kit | `@alexandremace` | ui.alexandremace.fr |
| lucide-react | 1.x | Plus d'icônes de marque |
| Geist | paquet npm | |
| Hébergement | Vercel Hobby | |

## Portée : un socle, deux couches

| Couche | Quand | Ce qu'elle ajoute |
|---|---|---|
| **Socle** | tout projet Next perso | Next 16 App Router, TypeScript strict, pnpm, Tailwind 4, shadcn Base UI Nova, Biome, Geist, français, Server Components par défaut, `metadataBase` |
| **Site d'écosystème** | le site rejoint le réseau | le kit `@alexandremace` et sa palette, les données cuites, le mono-thème clair, pas de suite de tests |
| **Full-stack** | il faut des comptes ou des données stockées | **Convex** et ses composants `@convex-dev/*` (rate limiting, emails Resend, paiements Stripe), auth **Clerk**, et des tests qui deviennent obligatoires : Vitest et `convex-test` pour le backend, Playwright pour les parcours critiques |

Avoir un backend ne fait pas changer de stack. Ce qui décide, c'est le modèle de rendu. Si rendre côté serveur n'économise aucun JavaScript parce que tout est interactif, le projet est une application et va sur `tanstack-start/`. Symbl est ici pour des raisons historiques.

## Principes

1. **Statique d'abord.** Par défaut, tout ce que la page affiche existe au build, dans des constantes typées sous `lib/` : pas de route handlers, pas de server actions, pas de DB. Un backend n'arrive qu'avec la couche full-stack, quand des comptes ou des données stockées l'exigent.
2. **Server Components par défaut.** `"use client"` est réservé aux composants qui portent de l'état ou de l'interaction (filtre, simulateur, dialog). Ratio sain : une poignée de fichiers client par projet, pas la moitié.
3. **Le kit est la source.** `components/ui/` vient du registry `@alexandremace` et ne se modifie jamais dans un consommateur : le changement vit dans le kit, puis se propage (`/propagate-kit`). `components/*.tsx` à la racine = composants spécifiques au projet.
4. **pnpm**, lockfile `pnpm-lock.yaml` commité et `packageManager` épinglé dans `package.json`. Même convention que la stack pro : store partagé, installs rapides, un seul outil partout.
5. **Mono-thème clair par défaut.** Le `@custom-variant dark` de `globals.css` sert à neutraliser les `dark:` du stock shadcn, la classe `.dark` n'est jamais posée. Un thème sombre est une décision de projet, pas un défaut.
6. **Français partout** : contenu, metadata (`locale: "fr_FR"`), commentaires de code ; identifiants métier français tolérés dans les scripts et modèles (`Pays`, `ANNEE`, `donnees`).
7. **Chaque site vit sur son domaine canonique** (`<projet>.alexandremace.fr` ou `<projet>.climatelab.fr`), déclaré dans `metadataBase`.

---

## 1. Scaffolding

- **Next.js 16, App Router, TypeScript strict**, `private: true`. Structure à la racine (pas de `src/`) :

```
app/            # pages, layout, icon.tsx, opengraph-image.tsx
components/     # composants du projet
components/ui/  # kit @alexandremace, ne pas éditer
lib/            # données typées, utils (cn)
scripts/        # pipeline data éventuel (*.mjs)
```

- `next.config.ts` typé `NextConfig`, vide par défaut ; on n'y ajoute que le nécessaire (`images.remotePatterns` si images distantes : jamais `hostname: "**"`, lister les hôtes réels ; `images.domains` est déprécié). Nouveaux défauts `next/image` en 16 à connaître : `qualities` réduit à `[75]`, cache TTL passé à 4 h, et une src locale avec query string exige `images.localPatterns`.
- **`AGENTS.md` est commité** : depuis 16.3, `next dev` crée et maintient ce fichier (bloc auto-géré pointant la doc de la version installée). On le versionne tel quel et on n'édite pas le bloc auto-géré. C'est aussi le fichier qui porte les conventions du projet, `CLAUDE.md` se réduisant à une ligne `@AGENTS.md`.
- `tsconfig` : alias `@/*` vers la racine ; target ES2017 minimum.
- Scripts minimaux : `dev`, `build`, `start`, `lint` (+ `data` si pipeline). `dev` avec `--turbopack`.
- **Biome pour le lint et le format** (un binaire, `biome.json`) : `next lint` a disparu en Next 16, Biome est une option officielle de `create-next-app` (moteur de types financé par Vercel), et son domaine `next` s'active tout seul dès `next >= 14` en couvrant l'essentiel d'eslint-config-next. Scripts : `"lint": "biome check"`, `"lint:fix": "biome check --write"`. Les sites existants en ESLint flat + eslint-config-next migrent à l'occasion, pas en big-bang.

  > Nuance de veille (août 2026) : la filière oxc (oxlint + oxfmt, le choix de Theo/t3) est l'autre sortie du duo ESLint+Prettier, plus rapide encore, mais oxfmt est en beta. Biome est le tout-en-un stable du moment ; re-statuer si oxfmt passe stable.

## 2. Kit et composants

- `components.json` : **base Base UI, style Nova** (le défaut shadcn depuis juillet 2026 : les nouveaux composants sortent d'abord pour cette base), `iconLibrary: lucide`, `cssVariables: true`, alias standards (`@/components`, `@/lib/utils`, `@/components/ui`, `@/lib`, `@/hooks`), et le registry déclaré :

```json
"registries": { "@alexandremace": "https://ui.alexandremace.fr/r/{name}.json" }
```

- Installation d'un composant : `npx shadcn@latest add -y -o @alexandremace/<item>`. Les composants d'écosystème (`made-with-love`, `brand`, `climatelab-badge`, `search-trigger`…) passent par le registry aussi, jamais par copier-coller entre projets.
- **Projet hors kit** (application, identité propre) : base Base UI stock via le CLI shadcn officiel, sans le registry.
- **Base UI, jamais React Aria ni Radix.** Le kit et ses consommateurs ont migré d'`aria-nova` vers Base UI en août 2026. La composition passe par la prop `render` (il n'y a d'`asChild` dans aucune des deux bases) et par les handlers DOM standards (`onClick`). Un projet resté sur l'ancienne base est un écart que `/gap-analysis` doit sortir, et il se migre en entier : jamais deux bases dans un même projet.
- **lucide-react 1.x n'a plus d'icônes de marque** (GitHub, X…) : SVG local dans `components/icons.tsx`.
- Variantes de composants projet : CVA, comme dans le kit.

## 3. Styling

Entête canonique de `app/globals.css`, dans cet ordre :

```css
@import "tailwindcss";
@import "tw-animate-css";
@import "shadcn/tailwind.css";

/* Mono-theme clair : la variante dark ne s'active que par classe .dark, jamais posee. */
@custom-variant dark (&:is(.dark *));
```

- **Tokens en OkLCh** dans `:root`, remappés en `--color-*` dans `@theme inline` ; échelle de radius dérivée de `--radius: 0.625rem`. Tailwind 4 via PostCSS : **zéro `tailwind.config.js`**.
- Palette par défaut = celle du kit : fond sable `#FAF8F0`, cartes `#FDFCF8`, primary `#0737FF` (`--warning`/`--success` en plus du stock). Un projet peut assumer sa propre palette (souvenir, identité forte) : il re-déclare les tokens, il ne touche pas aux composants.
- Piège connu : `shadcn add theme` ne réécrit pas un `globals.css` existant. Les tokens s'ajoutent à la main dans `:root` **et** `@theme inline` (cf. skill `/propagate-kit`).
- Classes conditionnelles via `cn()` (`lib/utils.ts`, clsx + tailwind-merge), identique partout.

## 4. Layout et SEO

- **Fonts : Geist Sans + Geist Mono via le paquet npm `geist`**, variables posées sur `<html>`, `font-sans` + `antialiased` :

```tsx
<html lang="fr" className={`${GeistSans.variable} ${GeistMono.variable} antialiased`}>
  <body className="font-sans">{children}</body>
</html>
```

- **`metadata` exporté depuis `app/layout.tsx`**, description factorisée en const, `metadataBase` obligatoire sur le domaine canonique :

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

- **OG image générée** : `app/opengraph-image.tsx` avec `ImageResponse` de `next/og`, 1200×630. Pas d'image statique qui périme. Depuis 16.2, `ImageResponse` est nettement plus rapide et sa police par défaut est Geist Sans : cohérence gratuite avec le reste du système.
- **Favicon** : `app/icon.tsx` (généré) ou emoji en data-URI dans `icons.icon`. Pas de `favicon.ico` par défaut Next qui traîne. Depuis 16.2, `icon.svg` et `icon.png` peuvent coexister dans `app/` avec fallback automatique.

## 5. Données cuites

Le contenu vit dans `lib/` en TypeScript typé, jamais en fetch runtime pour l'affichage initial.

- **Petit jeu de données** (fiches projets, modèle) : écrit à la main dans `lib/data.ts` / `lib/model.ts`, typé, sources documentées en commentaire d'entête.
- **Jeu de données issu d'une source externe** (Banque mondiale, OWID…) : pipeline `pnpm data` :
  - `scripts/build-*.mjs`, ESM natif (`node:fs`, `fetch` global, top-level await) ;
  - sortie générée dans `lib/**/*.ts` avec entête obligatoire : `// Généré par scripts/…, ne pas éditer à la main.` + source + date d'extraction + licence si exigée (CC BY pour OWID) ;
  - **logs de contrôle en fin de run** (totaux croisés avec un agrégat de référence, comptes de lignes) : sur un site sans tests, le log de contrôle EST le test ;
  - relancer `pnpm data` avant un déploiement qui dépend de la fraîcheur.
- **Fetch runtime : uniquement pour l'exploration volontaire** (ex. sélecteur de pays sur un graphique) : petits appels ciblés, cache en mémoire, dégradation propre si l'upstream tombe. Jamais `useEffect + fetch` pour du contenu qui pouvait être cuit au build.

## 6. Qualité et déploiement

- **`pnpm build` est le check** : types, lint Next, génération statique. Le lancer avant de pousser. Pas de suite de tests par défaut sur ces sites : la logique critique est dans les scripts data, contrôlée par leurs logs.
- **Vercel : pousser = déployer** (webhook sur main). Grouper les pushes ; le plan Hobby plafonne à 100 déploiements par 24 h glissantes, et chaque push de chaque projet consomme un slot. Depuis avril 2026, la rétention Hobby est de **30 jours** (les 10 derniers déploiements prod et les déploiements aliasés sont préservés) : ne jamais compter sur une vieille URL de déploiement comme archive.
- **Sécurité Next** : suivre le programme LTS (16.x Active, releases de sécurité préannoncées sur le blog Next) et **patcher tous les projets sous une semaine** après une security release.
- Un ancien hébergement (GitHub Pages) se recycle en page de redirection (canonical + meta refresh) vers le domaine, jamais en doublon vivant.
- Dev local : serveur via `.claude/launch.json`, un port dédié par projet.

## 7. Anti-patterns

- Éditer un fichier de `components/ui/` dans un consommateur : le changement vit dans le kit, puis `/propagate-kit`.
- Mélanger les idiomes de deux bases dans un même projet. `asChild` n'existe ni en Base UI ni en React Aria : c'est du Radix recopié d'un projet pro.
- `tailwind.config.js` : Tailwind 4 se configure en CSS (`@theme`).
- npm, yarn, ou un lockfile mixte : pnpm seul.
- `useEffect + fetch` pour du contenu affichable au build : cuire dans `lib/` via `pnpm data`.
- Fichier généré édité à la main (l'entête dit de ne pas le faire ; la modif part au prochain run).
- `metadataBase` absent ou pointant un `*.vercel.app` : le domaine canonique fait foi partout (metadata, OG, redirects).
- Classe `.dark` posée ou `dark:` actif sans décision de thème sombre explicite.
- Pousser plusieurs repos à la chaîne sans penser au quota Vercel.
- `npm install` ou `package-lock.json` commité : pnpm seul (un lockfile npm qui apparaît = quelqu'un a installé avec le mauvais outil).
