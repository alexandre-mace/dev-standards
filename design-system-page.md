# Format de présentation d'un design system

Ce document décrit **comment présenter** un design system, pas quoi mettre dedans. Il s'applique tel
quel à un système fait maison comme à du shadcn quasi brut : ce qui change d'un produit à l'autre,
c'est le remplissage des sections, jamais leur liste ni leur ordre.

Objectif : qu'un dev qui arrive sur le DS de n'importe lequel de nos produits retrouve les mêmes
rubriques, au même endroit, avec la même signification.

## 1. Le principe : documenter la provenance, pas les valeurs

Un catalogue qui liste « voilà nos couleurs, voilà nos boutons » répond à « qu'est-ce qui existe ».
Il ne répond pas à « lequel je prends, et qu'est-ce que j'ai le droit d'en faire ». Et sur un projet
qui consomme une bibliothèque upstream (shadcn, Radix, Base UI), il duplique une doc déjà écrite
ailleurs, mieux, et qui reste à jour toute seule.

D'où la règle centrale : **chaque brique porte une provenance**, et c'est elle qui détermine combien
on écrit.

| Provenance | Signification                       | Ce qu'on documente                                                         |
| ---------- | ----------------------------------- | -------------------------------------------------------------------------- |
| `upstream` | pris tel quel à la bibliothèque     | une ligne dans l'inventaire, un lien vers la doc officielle. Rien de plus. |
| `variant`  | upstream, plus des variantes maison | **uniquement les variantes maison**. Le reste renvoie à l'upstream.        |
| `custom`   | inventé chez nous                   | la fiche complète.                                                         |

Le coût de rédaction devient proportionnel à ce qu'on a réellement inventé. Un produit qui consomme
40 composants upstream écrit 40 lignes et 3 fiches, pas 40 fiches.

Cette règle est aussi ce qui rend le format portable : sur un système entièrement fait maison, tout
est `custom` et le format redevient un catalogue classique. C'est le même formulaire, rempli
différemment.

### Ce qui entre dans le design system, ce qui n'y entre pas

Un design system documente des **briques réutilisables**, pas des features. Un formulaire de
candidature ou un simulateur appartient à sa feature. Sans critère explicite, la frontière se décide
par accident : une brique réutilisable atterrit dans le dossier de la première feature qui en a eu
besoin, et n'entre jamais dans le catalogue.

Le critère : **une brique utilisée par au moins deux features, ou écrite pour l'être, appartient au
design system** et doit sortir de son dossier de feature.

Le dossier de la bibliothèque upstream (`ui/` pour shadcn) lui reste réservé : ce qui vient d'elle,
ses variantes, et ce qui en a la forme. Un composant maison transverse va dans un dossier partagé
distinct. Sans cette séparation, on ne distingue plus ce qui est à nous de ce qui est à l'upstream,
qui est toute la question à laquelle le document répond.

Ce critère se vérifie mécaniquement : résoudre les imports, compter les zones qui consomment chaque
composant, et lister ceux qui dépassent une zone tout en vivant hors du dossier partagé. Ce sont les
candidats à la promotion. À passer périodiquement, sinon le catalogue décrit un périmètre qui n'est
plus le bon.

Deux cas à exclure explicitement, sinon la liste se pollue : l'**infrastructure** (providers,
contextes applicatifs) qui est traversante sans être une brique d'interface, et les composants de
feature partagés entre deux zones voisines par proximité fonctionnelle et non par généricité.

### Trois mondes, un format

Le format vit aujourd'hui dans trois environnements qui n'ont rien en commun techniquement, et
c'est ce qui le valide :

| Monde | Exemple | Comment le format s'incarne |
| --- | --- | --- |
| **Repo classique** | lagrange (Symfony + shadcn vendored) | le cas nominal : tout ce document s'applique tel quel |
| **No-code** | feve.co (Webflow) | les valeurs se **relèvent via l'API** (variables, classes), jamais à la main ; la page vivante est une page du site construite avec les vraies classes (`/ds-demo`) |
| **Upstream = notre propre kit** | mix, thermomètre, wealth (@alexandremace) | cas dégénéré, voir ci-dessous |

**Quand l'upstream est notre propre kit**, la provenance a deux étages (shadcn stock → kit → projet)
et toute la documentation de provenance vit déjà **dans le kit** : descriptions d'items, liste des
écarts au stock, et le site du registry qui est déjà la page vivante. Un consommateur du kit ne
documente donc que sa ligne d'identité et ses éventuelles briques custom ; tout le reste renvoie au
kit. Appliquer le format complet à un consommateur, c'est dupliquer le kit en moins bien.

## 2. Deux artefacts, jamais un seul

**`DESIGN-SYSTEM.md`** : la description écrite. Versionnée, diffable, lisible en revue de code.
C'est la référence opposable.

**Une page vivante** servie par l'application elle-même. Elle consomme le vrai CSS et les vrais
composants de production, donc elle ne peut pas mentir. C'est la preuve visuelle.

Les deux suivent le plan de la section 3, avec les mêmes titres dans le même ordre. On ne met pas de
capture d'écran dans le `.md` : une capture périme en silence, un lien vers la page vivante non.

## 3. Le plan

### 0. Identité

Une phrase, la plus importante du document : ce qu'est ce design system et son rapport à l'upstream.

> « Le DS de lagrange, c'est shadcn brut, décliné sur un axe thématique par outil. »
> « Le DS de feve.co, c'est un système maison `ui-*` posé sur les variables Webflow. »

Le lecteur sait immédiatement quoi attendre, et surtout quoi ne pas chercher.

Si un référentiel de marque vit ailleurs (le site vitrine, un brandbook), lister ici les
**divergences assumées** : ce que ce produit fait différemment et pourquoi c'est un choix. C'est ce
qui permet d'uniformiser le format des brandbooks sans uniformiser les identités.

### 1. Axes de variation

La plupart des systèmes se déclinent sur un ou plusieurs axes : un thème par domaine fonctionnel, un
mode éditorial, clair/sombre, un breakpoint. Pour chaque axe, indiquer :

- les valeurs possibles ;
- ce qui change quand on en change ;
- **ce qui ne change pas** (c'est ce qui garantit qu'on ne casse rien en basculant d'axe).

Un produit sans axe écrit « aucun » et passe à la suite.

### 2. Fondations

Couleur, typographie, espacement, rayon, icônes, ombres, motion. Avec la provenance en colonne, pour
distinguer d'un coup d'oeil ce qui est le défaut de la bibliothèque de ce qu'on a posé nous.

**La page vivante montre chaque token** : pastille, nom, rôle, et la valeur **lue dans le CSS rendu
au moment de l'affichage**, pas recopiée. Une table de tokens recopiée dérive ; une table qui lit le
CSS ne peut pas mentir. Documenter aussi les contrastes mesurés des couples réellement utilisés
(pratique du DS feve.co, à généraliser).

### 3. Composants

D'abord **l'inventaire** : un tableau, une ligne par composant, avec sa provenance et son lien
upstream. C'est la carte du territoire, et sur un projet upstream c'est souvent 90 % du contenu utile.

Ensuite **les fiches**, conditionnelles à la provenance (voir section 4).

### 4. Patterns

Les assemblages récurrents, ceux qu'on veut voir reproduits à l'identique : formulaire, état vide,
état d'erreur, chargement, pagination, confirmation destructive.

### 5. Récurrences

Ce qui se répète dans le produit **sans être une brique du design system**. C'est le chaînon manquant
entre le catalogue et le code de feature, et c'est souvent la section qui rapporte le plus.

À ne pas confondre avec la précédente : un **pattern** est un assemblage qu'on veut voir reproduit,
une **récurrence** est un assemblage qui _est_ reproduit, et dont il faut décider s'il devrait l'être.

Pour chaque archétype : où il apparaît, ce qu'il pèse, et un verdict.

| Verdict            | Quand                                                                      |
| ------------------ | -------------------------------------------------------------------------- |
| **à généraliser**  | même structure écrite plusieurs fois, les différences sont accidentelles   |
| **à surveiller**   | même apparence, objets métier différents : fusionner ferait un sac à props |
| **action précise** | un cas isolé, corrigeable en une fois                                      |

Le verdict compte plus que la liste. « À surveiller » est une vraie réponse : cinq cartes qui se
ressemblent mais portent des données et des comportements différents produisent, une fois fusionnées,
un composant à quinze props optionnelles, moins lisible que les cinq versions. Ce qui s'extrait est
alors le squelette, pas le composant.

Deux façons de trouver les récurrences sans les deviner : les **noms de fichiers identiques dans des
dossiers différents** (le signal le plus fiable), et les **besoins couverts par un composant upstream
non installé** (un état vide roulé à la main alors que la bibliothèque en fournit un).

### 6. Rédaction

Ton, registre (tutoiement ou vouvoiement), libellés de boutons, majuscules, formats de date et de
nombre. Un DS qui ne dit rien du texte laisse chaque dev inventer le sien.

### 7. Ce qu'on ne fait pas

Anti-patterns et éléments dépréciés, avec le remplacement en face. C'est en général la section la
plus utile du document, parce que c'est la seule qui empêche activement de faire une bêtise. Un token
legacy qu'on laisse sans mention sera réutilisé.

## 4. Le gabarit d'une fiche composant

Selon la provenance :

**`upstream`** : nom, une phrase d'usage, lien vers la doc officielle. Fin.

**`variant`** : nom, une phrase, lien upstream, puis **seulement** l'aperçu des variantes maison, avec
la raison d'être de chacune.

**`custom`**, le gabarit complet :

1. Aperçu (rendu réel, avec sélecteurs de variantes si le composant en a)
2. Anatomie (les parties nommées)
3. Variantes
4. États : défaut, survol, focus, actif, désactivé, chargement, erreur
5. Tailles
6. Quand l'utiliser / quand prendre autre chose
7. Accessibilité : clavier, ARIA, contraste
8. Code copiable

L'ordre est fixe. Une section sans contenu se supprime, elle ne se déplace pas.

## 5. La page vivante

Un Storybook minimal, sans la dépendance Storybook. Trois exigences seulement :

- **Elle consomme les vrais composants de production.** Pas de copie, pas de fixture stylée à la main.
- **Chaque entrée affiche sa provenance**, visuellement, à côté du titre.
- **Les axes déclarés produisent des sélecteurs**, pour que l'aperçu se manipule au lieu d'être figé.

Un Storybook complet est justifié quand le front est une SPA autonome. Il l'est beaucoup moins sur
une application où une partie du DS vit en templates serveur et en classes CSS : Storybook n'en verrait
que la moitié, et il faudrait maintenir un second build.

## 6. Une source lisible par une machine

L'inventaire et les provenances gagnent à vivre dans un fichier structuré à la racine, consommé à la
fois par le `.md` et par la page vivante, pour qu'ils ne puissent pas diverger. Sur un projet shadcn,
`npx shadcn info` fournit déjà la liste des composants installés : il ne reste qu'à ajouter la
provenance.

Deux adaptations. Sur un site **no-code**, il n'y a pas de repo où poser le fichier : il se génère
depuis les variables et les classes via l'API. Quand **l'upstream est notre propre kit**, le
`registry.json` du kit joue déjà ce rôle, avec ses items typés et décrits : ne pas en créer un second.

## 7. Tenue dans le temps

- **Dater le document** et dire comment les valeurs ont été obtenues : relevées via l'API, lues dans
  le CSS, mesurées. Une valeur reconstituée de mémoire est une valeur fausse en puissance.
- **Ne jamais recopier une valeur upstream**, la référencer.
- **Toute nouvelle couleur passe un contrôle de contraste** avant d'entrer, et le ratio est noté.
- **La page vivante tranche** en cas de désaccord avec le `.md` : elle rend le code réel.
- **Le déclencheur de mise à jour est la PR, pas le calendrier.** Toute PR qui ajoute un composant,
  crée une variante ou customise une brique met à jour l'inventaire dans la même PR. Le check
  mécanique de la section 1 sert de filet, à passer au `/gap-analysis` suivant.
- **L'inventaire est par produit et fait foi.** Les documents partagés entre produits ne portent
  jamais d'inventaire local : ils gardent les procédures et les faits d'upstream, et pointent ici.
  Deux inventaires du même fait divergent toujours.
