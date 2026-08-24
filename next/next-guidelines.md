# Next Guidelines : sites statiques perso

> Conventions de la stack perso (portfolio, climatelab, wealth, taste, culture, state…). Pragmatique, pas dogmatique.
> Prescriptif et partagé : ce doc dit ce qui **doit** être, jamais l'état d'un projet particulier (ça, c'est `/gap-analysis`).
>
> **Dernière veille : 24 août 2026** (`/update-guidelines`) — repartir de cette date au prochain run. Versions de référence vérifiées : Next.js 16 (App Router) · React 19.2 · TypeScript 5.9 (TS 7 natif GA : migration 5.9 → 6.0 → 7.0 à planifier) · Tailwind 4 (PostCSS, zéro `tailwind.config`) · shadcn style `aria-nova` (base `react-aria-components`) · kit `@alexandremace` (ui.alexandremace.fr) · lucide-react 1.x · Geist (paquet npm) · Vercel Hobby.

## Portée : socle et couche site statique

Deux niveaux, à ne pas confondre :

- **Le socle** vaut pour tout projet Next perso, site vitrine ou vraie application : Next.js 16 App Router, TypeScript strict, pnpm, Tailwind 4 (PostCSS), shadcn style `aria-nova` (React Aria), ESLint flat config, Geist, français, Server Components par défaut, domaine canonique en `metadataBase`.
- **La couche site statique d'écosystème** (portfolio, climatelab, wealth, taste, culture…) ajoute : le kit `@alexandremace` et sa palette, les données cuites, le mono-thème clair, l'absence de suite de tests.

Un projet applicatif (vrai backend, tests, desktop… ex. symbl) garde le socle mais sort de la couche statique : ses conventions propres vivent dans son CLAUDE.md, et deviendront un doc de stack le jour où la structure se répète (doctrine des récurrences). Dans les principes ci-dessous, le 1 (statique), le 3 (kit), le 5 (mono-thème) et le « pas de tests » du §6 relèvent de la couche statique ; tout le reste est socle.

## Principes

1. **Statique d'abord.** Pas de backend : pas de route handlers, pas de server actions, pas de DB. Tout ce que la page affiche existe au build, dans des constantes typées sous `lib/`.
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

- `next.config.ts` typé `NextConfig`, vide par défaut ; on n'y ajoute que le nécessaire (`images.remotePatterns` si images distantes).
- `tsconfig` : alias `@/*` vers la racine ; target ES2017 minimum.
- Scripts minimaux : `dev`, `build`, `start`, `lint` (+ `data` si pipeline). `dev` avec `--turbopack`.
- ESLint flat config `eslint.config.mjs` :

```js
import { defineConfig } from "eslint/config";
import coreWebVitals from "eslint-config-next/core-web-vitals";
import typescript from "eslint-config-next/typescript";

export default defineConfig([...coreWebVitals, ...typescript,
  { ignores: [".next/**", "out/**", "node_modules/**"] }]);
```

## 2. Kit et composants

- `components.json` : style **`aria-nova`**, `iconLibrary: lucide`, `cssVariables: true`, alias standards (`@/components`, `@/lib/utils`, `@/components/ui`, `@/lib`, `@/hooks`), et le registry déclaré :

```json
"registries": { "@alexandremace": "https://ui.alexandremace.fr/r/{name}.json" }
```

- Installation d'un composant : `npx shadcn@latest add -y -o @alexandremace/<item>`. Les composants d'écosystème (`made-with-love`, `brand`, `climatelab-badge`, `search-trigger`…) passent par le registry aussi, jamais par copier-coller entre projets.
- **Projet hors kit** (application, identité propre) : style `aria-nova` stock via le CLI shadcn officiel, sans le registry. Les idiomes React Aria ci-dessous s'appliquent à l'identique.
- **React Aria, pas Radix** : pas d'`asChild`. Les liens stylés bouton passent par l'export `LinkButton` de `components/ui/button.tsx` ; interaction via `onPress`/`isDisabled` ; état de sélection stylé via `data-selected`.
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

- **OG image générée** : `app/opengraph-image.tsx` avec `ImageResponse` de `next/og`, 1200×630. Pas d'image statique qui périme.
- **Favicon** : `app/icon.tsx` (généré) ou emoji en data-URI dans `icons.icon`. Pas de `favicon.ico` par défaut Next qui traîne.

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
- **Vercel : pousser = déployer** (webhook sur main). Grouper les pushes ; le plan Hobby plafonne à 100 déploiements par 24 h glissantes, et chaque push de chaque projet consomme un slot.
- Un ancien hébergement (GitHub Pages) se recycle en page de redirection (canonical + meta refresh) vers le domaine, jamais en doublon vivant.
- Dev local : serveur via `.claude/launch.json`, un port dédié par projet.

## 7. Anti-patterns

- Éditer un fichier de `components/ui/` dans un consommateur : le changement vit dans le kit, puis `/propagate-kit`.
- `asChild`, `onClick` sur un trigger React Aria, ou un pattern Radix recopié d'un projet pro : autre base, autres idiomes (`onPress`, `LinkButton`, `data-selected`).
- `tailwind.config.js` : Tailwind 4 se configure en CSS (`@theme`).
- pnpm, yarn, ou un lockfile mixte : npm seul.
- `useEffect + fetch` pour du contenu affichable au build : cuire dans `lib/` via `pnpm data`.
- Fichier généré édité à la main (l'entête dit de ne pas le faire ; la modif part au prochain run).
- `metadataBase` absent ou pointant un `*.vercel.app` : le domaine canonique fait foi partout (metadata, OG, redirects).
- Classe `.dark` posée ou `dark:` actif sans décision de thème sombre explicite.
- Pousser plusieurs repos à la chaîne sans penser au quota Vercel.
- `npm install` ou `package-lock.json` commité : pnpm seul (un lockfile npm qui apparaît = quelqu'un a installé avec le mauvais outil).
