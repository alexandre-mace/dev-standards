---
name: diagnosing-bugs
description: Boucle disciplinée de correction de bug - reproduire, hypothèse, instrumenter, cause racine, test de régression. Déclencheurs - bug, régression, "ça marche plus", erreur en prod, issue Sentry, 500.
---

# Diagnosing bugs

Prend la place de `/ticket` quand la tâche est « répare ce comportement » et
non « construis cette chose ». Entrées typiques : ticket bug de la PM, issue
Sentry, trouvaille de `/check-logs`, comportement cassé en préprod. La suite
du fil ne change pas : `/quality` → `/commit` → `/preprod` → `/deploy`.

La boucle a cinq crans. **Dans cet ordre, sans en sauter.**

## 1. Reproduire avant tout

Écrire la reproduction qui échoue **avant** de réfléchir au correctif :

- un test PHPUnit ou Vitest si le bug est atteignable en test (le meilleur cas,
  il resservira au cran 5) ;
- sinon un script, un `curl` sur la route, un scénario Playwright ;
- pour un bug Sentry : partir de l'événement réel (payload, stack, breadcrumbs
  via le MCP Sentry), pas d'une reconstitution imaginée.

**Pas de repro = pas de fix.** Un correctif non reproduit est une hypothèse
déguisée. Si la reproduction est réellement impossible (bug dépendant d'un état
prod inaccessible), le dire explicitement et compenser au cran 3 par de
l'instrumentation en prod.

## 2. Localiser et formuler UNE hypothèse

- Lire l'erreur **en entier** : le vrai message, la vraie ligne, pas le résumé.
- `git log -- <zone>` : les changements récents d'abord, la moitié des bugs
  sont dans le dernier commit qui a touché la zone.
- Énoncer l'hypothèse **en une phrase** dans la conversation. Une hypothèse
  non formulée ne peut pas être réfutée.

## 3. Vérifier l'hypothèse par l'instrument, jamais par le correctif

Log ciblé, `dump()`, test unitaire de la fonction suspecte, requête SQL à la
main. L'hypothèse est confirmée ou tombe. Si elle tombe : retour au cran 2,
hypothèse suivante. **Interdiction de « corriger pour voir »** — chaque
correctif spéculatif pollue la zone et détruit la valeur de la repro.

## 4. Corriger la cause racine, pas le symptôme

- Si le symptôme est dans A mais la cause dans B, on corrige B. Rembourrer A
  (null-check, try/catch, valeur par défaut) laisse le bug vivant pour le
  prochain appelant.
- Rayon d'impact sur B : lister les appelants (Grep), lancer les tests de la
  zone (`bin/phpunit --filter`, `pnpm test`). Un fix de cause racine touche du
  code partagé plus souvent qu'un pansement.

## 5. La repro devient le test de régression

- Le test du cran 1 (ou sa version propre) entre dans la suite. Il doit avoir
  **échoué avant le fix et passer après** — c'est la preuve dans les deux
  directions. La montrer : sortie avant, sortie après.
- Fermer la boucle d'origine : issue Sentry → la résoudre ; trouvaille de
  `/check-logs` → la noter ; ticket PM → signaler ce qui a été corrigé et la
  cause en une phrase.

## Règles

- Ne jamais annoncer « corrigé » sur la seule disparition du symptôme : c'est
  le test de régression qui fait foi.
- Un bug qui révèle un pattern fragile répété ailleurs : le **signaler** (une
  ligne, candidat pour `/gap-analysis`), ne pas partir le corriger partout —
  le périmètre du fix reste le bug.
- Si deux hypothèses tombent d'affilée, s'arrêter et présenter l'état : ce qui
  est exclu, ce qui reste possible, ce qui manque pour trancher. Trois fixes
  spéculatifs valent moins qu'un point d'étape honnête.
