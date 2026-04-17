---
name: update-guidelines
description: Relie docs/symfony-guidelines.md et docs/reactony.md, confronte leurs recommandations aux dernières pratiques web (changelogs officiels, blogs mainteneurs, migration guides), et propose des modifs là où les reco ne sont plus au niveau de l'état de l'art.
---

# update-guidelines

Relie `docs/symfony-guidelines.md` et `docs/reactony.md`, confronte leurs recommandations aux **sources autoritaires du web** (changelogs, blogs officiels, migration guides), et propose des modifs là où tes reco ne sont plus au niveau de l'état de l'art.

C'est une **veille techno** : les guidelines sont le point de référence, le web est le juge de paix, le repo du projet n'est **pas** une source (il peut contenir du legacy).

## Steps

1. **Read current state**
   - Read `docs/symfony-guidelines.md` and `docs/reactony.md`
   - Read `composer.json` and `package.json` to know exact versions (Symfony, PHP, React, TanStack Query, hey-api, Vite, Tailwind, etc.)

2. **Research web-first — sources autoritaires uniquement**

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

   **À NE PAS faire** :
   - **Ne pas s'inspirer des commits récents du projet**. Ils peuvent refléter du legacy, des compromis de livraison, ou des patterns antérieurs aux recommandations actuelles. Le repo est ce qu'on veut *corriger*, pas la source.
   - Ne pas se fier à un seul tuto — cross-checker avec ≥ 2 sources autoritaires avant d'adopter un pattern.
   - Ne pas citer des tutos de > 18 mois sans vérifier qu'ils sont toujours à jour.
   - Ne pas inventer : si le pattern n'apparaît pas dans une source officielle, le marquer "à valider avec l'utilisateur".

3. **Critical analysis** — For each guideline file, check:
   - **Version references** — do they match the project's actual versions? Remove or update stale version mentions
   - **Factual accuracy** — are the patterns and APIs described correctly?
   - **Completeness** — are important patterns missing? (breaking changes, security, new stable features)
   - **Clarity** — are there ambiguities, duplications, or unclear sections?
   - **Consistency** — do the two files agree where they overlap? (DTO patterns, upload patterns, format:'json')
   - **Setup/dependencies** — are all required packages actually declared in package.json/composer.json?

4. **Present findings** — Show the user a structured summary:
   - Corrections factuelles (must fix)
   - Ajouts importants (should add)
   - Améliorations de clarté (nice to have)
   - What's already good and should stay
   - **Si aucun changement n'est nécessaire** : le dire clairement ("Les guidelines sont à jour, aucune modification nécessaire") avec un résumé de ce qui a été vérifié, pour que l'utilisateur sache que la review a bien été faite.

5. **Apply changes** — After user approval, edit both files with the corrections. If no changes are needed, skip this step.

## Rules

- The guidelines are prescriptive (how code SHOULD be written), not descriptive (how code IS written). Don't weaken a guideline just because the current code doesn't follow it yet. Don't strengthen one just because the recent commits do follow it — the web sources win.
- Keep the same tone and structure — pragmatic, concise, with code examples.
- French for prose, English for code and technical terms.
- Don't add patterns the project doesn't use or plan to use — ask the user if unsure.
- Don't remove existing patterns that are correct — only update, add, or clarify.
- Both files should be self-contained but consistent where they overlap (DTO patterns, upload patterns, format:'json').
- If the guidelines are already up-to-date and correct after thorough review, it's perfectly fine to conclude with "no changes needed". The goal is accuracy, not change for the sake of change.
