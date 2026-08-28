---
name: sota-gap
description: Mesure l'écart entre les guidelines dev-standards (stack symfony-react et stack next) et l'état de l'art, en confrontant leurs recommandations aux sources officielles du web. Déclencheurs - sota gap, veille, update guidelines, "les guidelines sont-elles à jour", "on est encore au sota ?", état de l'art.
---

# sota-gap

L'écart entre la recette et l'écosystème. Le pendant de `/gap-analysis`, qui mesure
l'écart entre le code et la recette : ici l'autorité est inversée, c'est
l'écosystème qui a raison et la recette qu'on corrige.

Relie les guidelines de `~/dev/dev-standards` : stack pro `symfony-react/` (`symfony-guidelines.md`, `reactony.md`) et stack perso `next/` (`next-guidelines.md`), confronte leurs recommandations aux **sources autoritaires du web** (changelogs, blogs officiels, migration guides), et propose des modifs là où tes reco ne sont plus au niveau de l'état de l'art. Lancé depuis un projet, les mêmes fichiers sont accessibles via ses symlinks `docs/`. Par défaut la veille couvre les deux stacks ; l'utilisateur peut en cibler une.

C'est une **veille techno** : les guidelines sont le point de référence, le web est le juge de paix, le repo du projet n'est **pas** une source (il peut contenir du legacy).

## Steps

1. **Read current state**
   - Read the stack's guidelines: `symfony-react/symfony-guidelines.md` + `symfony-react/reactony.md` (pro) and/or `next/next-guidelines.md` (perso)
   - Read `composer.json` / `package.json` of a representative project per stack to know exact versions (Symfony, PHP, React, TanStack Query, hey-api, Vite, Tailwind : et côté next : Next.js, kit @alexandremace, etc.)

2. **Research web-first : sources autoritaires uniquement**

   Les guidelines sont **prescriptives** : elles reflètent ce que les mainteneurs et la communauté recommandent MAINTENANT, pas ce que le projet fait aujourd'hui. Le travail est fondamentalement une **veille techno web**, pas une introspection du repo.

   **Sources à consulter**, par ordre de priorité :
   - Changelogs GitHub officiels de chaque version majeure+mineure récente de chaque lib (`composer.json`/`package.json`)
   - Blogs officiels : `symfony.com/blog`, `react.dev/blog`, `vercel.com/blog`, `doctrine-project.org/blog`, `www.php.net/releases`
   - Release notes + migration guides des libs (souvent dans `UPGRADE.md` ou `CHANGELOG.md` du repo)
   - RFC / discussions GitHub labellisés "RFC" ou "roadmap"
   - Annonces de conférences récentes (SymfonyCon, ReactConf) pour les patterns émergents validés

   **Couvrir** (liste non exhaustive, explorer tout le package.json/composer.json) :
   - **Symfony** : changelog complet version actuelle + précédente, nouveaux attributs, composants, deprecations
   - **PHP** : nouveautés de la version en `require`
   - **Doctrine** ORM/DBAL
   - **React & écosystème** : React, TanStack Query, React Hook Form, Zod, @hey-api/openapi-ts, Vite, vite-plugin-symfony, Tailwind, Shadcn/UI, React Compiler
   - **Stack next (perso)** : Next.js (blog officiel + changelog), React, Tailwind, shadcn/registry + react-aria-components (aria-nova), Vercel (plateforme et limites), next/og, lucide

   **À NE PAS faire** :
   - **Ne pas s'inspirer des commits récents du projet**. Ils peuvent refléter du legacy, des compromis de livraison, ou des patterns antérieurs aux recommandations actuelles. Le repo est ce qu'on veut *corriger*, pas la source.
   - Ne pas se fier à un seul tuto : cross-checker avec ≥ 2 sources autoritaires avant d'adopter un pattern.
   - Ne pas citer des tutos de > 18 mois sans vérifier qu'ils sont toujours à jour.
   - Ne pas inventer : si le pattern n'apparaît pas dans une source officielle, le marquer "à valider avec l'utilisateur".

3. **Critical analysis** : For each guideline file, check:
   - **Version references** : do they match the project's actual versions? Remove or update stale version mentions
   - **Factual accuracy** : are the patterns and APIs described correctly?
   - **Completeness** : are important patterns missing? (breaking changes, security, new stable features)
   - **Clarity** : are there ambiguities, duplications, or unclear sections?
   - **Consistency** : do the two files agree where they overlap? (DTO patterns, upload patterns, format:'json')
   - **Setup/dependencies** : are all required packages actually declared in package.json/composer.json?

4. **Present findings** : Show the user a structured summary:
   - Corrections factuelles (must fix)
   - Ajouts importants (should add)
   - Améliorations de clarté (nice to have)
   - What's already good and should stay
   - **Si aucun changement n'est nécessaire** : le dire clairement ("Les guidelines sont à jour, aucune modification nécessaire") avec un résumé de ce qui a été vérifié, pour que l'utilisateur sache que la review a bien été faite.

5. **Apply changes** : After user approval, edit both files with the corrections. If no changes are needed, skip this step.

## Rules

- The guidelines are prescriptive (how code SHOULD be written), not descriptive (how code IS written). Don't weaken a guideline just because the current code doesn't follow it yet. Don't strengthen one just because the recent commits do follow it : the web sources win.
- **Jamais d'état par projet dans les guidelines.** Ces docs sont partagés entre plusieurs projets : une phrase comme « le projet est en ^12.5 » ou « montée pas encore faite ici » est vraie pour l'un et ment chez les voisins. Constater les écarts projet ↔ guidelines, c'est le job de `/gap-analysis`. Les leçons tirées de l'historique (réf. ticket qui motive une règle) restent légitimes : c'est de la justification, pas de l'état. (Erreur commise puis corrigée le 24/08/2026.)
- Keep the same tone and structure : pragmatic, concise, with code examples.
- French for prose, English for code and technical terms.
- **Requêtes web en anglais**, et hiérarchie des sources primaires : voir `agent/recherche.md`. Une veille faite sur des blogs francophones de seconde main ne vaut rien.
- Don't add patterns the project doesn't use or plan to use : ask the user if unsure.
- Don't remove existing patterns that are correct : only update, add, or clarify.
- Within a stack, files must be self-contained but consistent where they overlap (ex. symfony-react : DTO patterns, upload patterns, format:'json').
- If the guidelines are already up-to-date and correct after thorough review, it's perfectly fine to conclude with "no changes needed". The goal is accuracy, not change for the sake of change.
