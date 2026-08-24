---
name: review-diff
description: Revue complète avant deploy - le diff contre le ticket d'origine et les guidelines, puis lance /quality et, si besoin, /check-implementation. Déclencheurs - review, relis le diff, "prêt à déployer ?", revue avant deploy.
disable-model-invocation: true
---

# Review du diff avant deploy

La revue de PR d'un flux sans PR. À lancer entre la recette et `/deploy` :
relit tout ce que la branche va poser sur `main`, contre l'intention
d'origine et les règles maison, puis délègue les vérifications mécaniques
aux skills spécialisés.

## 1. Rassembler les deux termes de la comparaison

- **Le diff complet** : `git diff main...HEAD` (et `git log main..HEAD --oneline`
  pour la forme des commits). Pas un échantillon : tout le diff.
- **L'intention** : le ticket d'origine s'il est dans la conversation, sinon le
  demander. Sans intention de référence, la revue se limite aux guidelines et
  le dit explicitement.

## 2. Le diff contre le ticket — le cœur du skill

Trois questions, dans cet ordre :

- **Tout ce qui est demandé y est ?** Reprendre chaque critère d'acceptation
  (explicite ou implicite) et pointer où le diff y répond. Un critère sans
  réponse = un manque à lister.
- **Rien de plus n'y est ?** Chaque changement du diff doit se rattacher au
  ticket ou à une hypothèse assumée annoncée pendant `/ticket`. Un changement
  orphelin est du périmètre qui a gonflé : le signaler, proposer de l'extraire
  dans un commit ou une branche à part. Ne pas laisser la revue elle-même
  élargir le périmètre.
- **Les hypothèses assumées tiennent-elles ?** Relire celles annoncées à
  l'implémentation face au code final.

## 3. Le diff contre les guidelines

Relire le diff (pas le projet entier — ça c'est `/gap-analysis`) avec
`docs/symfony-guidelines.md` et `docs/reactony.md` en tête : patterns DTO,
`format: 'json'` et `#[IsGranted]` sur les routes API, Domain/ pur, SDK plutôt
que fetch manuel, formulaires RHF+Zod, imports `@/`. Ne signaler que ce que le
diff introduit ou aggrave.

## 4. Déléguer les vérifications mécaniques

- Lancer **`/quality`** (obligatoire). Un FAIL bloque le verdict.
- Lancer **`/check-implementation`** seulement si le diff utilise une API ou
  un pattern inhabituel pour le projet (nouvelle lib, composant Symfony pas
  encore employé, feature React récente). Sur un diff fait de patterns déjà
  éprouvés dans le repo, c'est du bruit : ne pas le lancer, le dire.

## 5. Verdict

```
Review — <branche> vs <ticket>
--------------------------------
Couverture du ticket :   complet | manques listés
Périmètre :              net | N changements hors ticket
Guidelines :             conforme | écarts listés
/quality :               PASS | FAIL
/check-implementation :  PASS | non pertinent ici
Verdict :                prêt pour /deploy | à reprendre (liste)
```

Chaque manque ou écart pointe un fichier et une ligne. Un verdict « à
reprendre » liste des actions, pas des impressions.

## Règles

- Ce skill **ne modifie rien** : il diagnostique. Les corrections se font
  après, puis on le relance.
- Ne pas re-débattre les hypothèses tranchées pendant `/ticket` : elles ont
  été annoncées, la revue vérifie qu'elles sont tenues, pas qu'elles étaient
  les bonnes.
- Un diff parfait sur un ticket mal compris est un échec : la couverture du
  ticket prime sur l'élégance du code.
