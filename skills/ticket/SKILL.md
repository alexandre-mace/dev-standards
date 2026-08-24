---
name: ticket
description: Analyse en profondeur un ticket produit collé par l'utilisateur avant toute implémentation. L'agent investigue le code à fond, tranche lui-même tout ce que le code permet de trancher, et ne remonte que les points qui changeraient réellement le travail à faire.
---

L'utilisateur vient de coller un ticket (typiquement écrit par sa PM) dans `$ARGUMENTS`. Ton rôle est d'abord **d'investiguer**, pas de foncer dans l'implémentation. Mais investiguer sert à **décider**, pas à collectionner des questions.

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

### 3. Trancher tout ce qui peut l'être

Reprends chaque zone d'ombre de l'étape 1 et essaie de la lever **toi-même** avant d'envisager de la remonter. Le plus souvent, la réponse est déjà dans le code :

- Le modèle impose-t-il la réponse ? (champ NOT NULL, contrainte, validateur existant qui bloquerait déjà)
- Une seule lecture préserve-t-elle le sens de ce qui existe ? Alors c'est celle-là.
- Le ticket lui-même donne-t-il la réponse en creux ? (« il faut pouvoir saisir » ≠ « la saisie est obligatoire », une colonne « proposé par défaut » décrit un défaut surchargeable…)
- Existe-t-il un défaut conservateur qui ne peut pas produire de résultat faux ? (ne rien afficher plutôt qu'une valeur peut-être fausse, dégrader proprement plutôt que bloquer)

Ce qui se tranche ainsi n'est pas une question : c'est une **hypothèse assumée**, que tu documentes dans le code et que tu annonces en une ligne.

### 4. Faire le point avec l'utilisateur

Par défaut, tu **implémentes** avec tes hypothèses assumées, et tu les énonces clairement.

Ne remonte que ce qui remplit les deux conditions à la fois :
- ça change réellement le travail à faire (pas seulement la formulation d'un libellé) ;
- **et** aucune hypothèse raisonnable ne permet d'avancer sans risque de livrer faux, ou le code ne contient tout simplement pas l'information (un champ qui n'existe nulle part, une donnée métier que seul le produit détient).

Distingue trois catégories, sans les mélanger :
- **Ce que tu tranches** : la réponse et son raisonnement en une ou deux lignes.
- **Ce que tu signales** : un vrai constat qui n'attend pas de réponse pour avancer (un référentiel trop pauvre, une décision antérieure que le ticket renverse, un périmètre qui grossit). Tu le dis, tu continues.
- **Ce qui bloque vraiment** : idéalement zéro, souvent un. S'il y en a plus de deux ou trois, relis l'étape 3, tu n'as probablement pas assez cherché.

Quand un point bloque un seul lot, livre les autres et pose la question en parallèle plutôt que de tout arrêter.

Dans tous les cas, résume brièvement ta compréhension et ton plan avant de coder, puis implémente en suivant les conventions du projet. Prends le temps de bien faire, pas de raccourcis.

### 5. Règles générales
- **Investigation d'abord, code ensuite.** Ne saute jamais directement à l'implémentation sans avoir lu le code pertinent.
- **Une incohérence se prouve, elle ne se soupçonne pas.** Si tu crois en voir une, va vérifier dans le code avant de la remonter : la moitié se dissipent (« ça n'existe pas » devient « ça existe déjà », « c'est ambigu » devient « une seule lecture tient »). Ne remonte que ce que tu as constaté.
- **Deviner et raisonner ne sont pas la même chose.** Ne bluffe jamais sur un fait : va le vérifier. Mais quand le code et le ticket permettent de conclure, conclure n'est pas bluffer, c'est ton travail.
- **Le doute se paie en risque, pas en questions.** Avant de demander, estime ce qu'il en coûterait de se tromper. Si l'erreur est visible et rattrapable en une ligne, tranche et signale. Réserve la question à ce qui serait coûteux ou silencieux.
- **Respecte les conventions locales.** Réutilise les patterns et composants existants plutôt que d'en créer de nouveaux.
