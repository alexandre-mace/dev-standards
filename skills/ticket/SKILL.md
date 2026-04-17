---
name: ticket
description: Analyse en profondeur un ticket produit collé par l'utilisateur avant toute implémentation. L'agent doit investiguer le code à fond, poser des questions si quelque chose est flou, incohérent ou ambigu, et n'implémenter que si tout est limpide.
---

L'utilisateur vient de coller un ticket (typiquement écrit par sa PM) dans `$ARGUMENTS`. Ton rôle est d'abord **d'investiguer**, pas de foncer dans l'implémentation.

## Ticket

$ARGUMENTS

## Processus à suivre

### 1. Comprendre le ticket
- Relis attentivement le ticket ci-dessus.
- Identifie le *quoi* (ce qui est demandé), le *pourquoi* (valeur métier, si explicitée), et les critères d'acceptation implicites ou explicites.
- Note les zones floues, les contradictions potentielles, ou les hypothèses que tu ferais.

### 2. Investiguer le code en profondeur
- Explore la codebase pour localiser les fichiers, modules, composants, routes, entités, services concernés.
- Utilise les outils de recherche (Glob, Grep, Read) — lance plusieurs recherches en parallèle quand c'est pertinent.
- Lis réellement le code impacté (pas juste les noms de fichiers) pour comprendre l'existant : architecture, conventions locales, dépendances, tests, effets de bord possibles.
- Vérifie si des patterns similaires existent déjà dans le projet à réutiliser plutôt que réinventer.
- Consulte les guidelines du projet (ex. `docs/symfony-guidelines.md`, `docs/reactony.md`, `CLAUDE.md`) si elles existent.

### 3. Faire le point avec l'utilisateur

Après l'investigation, choisis **une** des deux voies :

**Voie A — Il reste des zones d'ombre.** N'hésite pas à remonter :
- Ce qui n'est pas clair dans le ticket
- Ce qui semble illogique, contradictoire, ou incohérent avec l'existant
- Les choix d'implémentation possibles avec leurs trade-offs
- Les risques, effets de bord, ou cas limites identifiés
- Les hypothèses que tu devrais faire pour avancer

Présente un résumé structuré de ton analyse et **attends les réponses** avant d'implémenter.

**Voie B — Tout est limpide.** Si l'investigation a levé toutes les ambiguïtés et que le plan est clair :
- Résume brièvement ta compréhension et ton plan d'implémentation
- Puis procède à l'implémentation en suivant les conventions du projet
- Prends le temps de bien faire — pas de raccourcis, pas de code jeté à la va-vite

### 4. Règles générales
- **Investigation d'abord, code ensuite.** Ne saute jamais directement à l'implémentation sans avoir lu le code pertinent.
- **Questionne les hypothèses.** Si une partie du ticket ne te semble pas cohérente avec ce que tu observes dans le code, dis-le.
- **Ne bluffe pas.** Si tu n'es pas sûr, demande plutôt que de deviner.
- **Respecte les conventions locales.** Réutilise les patterns et composants existants plutôt que d'en créer de nouveaux.
