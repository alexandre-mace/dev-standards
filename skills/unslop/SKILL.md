---
name: unslop
description: Nettoie un texte de ses tics d'écriture IA, en français comme en anglais. Déclencheurs - unslop, désloppe, "ça sonne IA", relis ce texte, humanise.
---

# Unslop

Réécrit un texte pour en retirer les marqueurs d'écriture IA et lui rendre une
voix humaine. S'applique à tout texte produit ou co-produit par un agent :
réponse de chat à retravailler, note pour la PM, description de PR, README,
doc, copie de site. Le sens ne change pas, la longueur baisse presque toujours.

## Cible

Le texte fourni par l'utilisateur (`$ARGUMENTS`), ou à défaut le dernier texte
substantiel produit dans la conversation. Détecte la langue et applique le
volet correspondant : les deux si le texte est mixte.

## Volet commun (les deux langues)

- **Politesse de chatbot** : « J'espère que cela vous aidera », « N'hésitez pas à », « I hope this helps », « Feel free to » → supprimer.
- **Gras systématique** : garder le gras pour ce qui doit accrocher l'œil au survol, pas pour scander chaque phrase.
- **Listes de trois** : trois exemples, trois adjectifs, trois bénéfices partout. Casser le rythme : un seul exemple fort vaut mieux que trois moyens.
- **Emphase vide** : remplacer l'impression par le mécanisme. « améliore considérablement les performances » → le chiffre, ou la cause.
- **Hedging en grappe** : « peut-être », « il semblerait », « dans une certaine mesure » empilés → trancher ou dire franchement qu'on ne sait pas.
- **Headers décoratifs** : pas de titre pour deux phrases, pas d'emoji de section.
- **Auto-audit final** : relire et se demander « qu'est-ce qui sonne encore IA là-dedans ? », corriger, puis s'arrêter, un texte sur-nettoyé sonne aussi faux qu'un texte sloppé.

## Volet français

- **Tiret cadratin (—) : interdit.** Remplacer par deux-points, virgule, ou couper la phrase. (Règle maison, non négociable.)
- **Point médian (·) : interdit** dans les libellés et séparateurs.
- « **Il est important de noter que** », « **il convient de** », « **force est de constater** » → supprimer, dire la chose.
- « **En tant que** [rôle], ... » en ouverture → supprimer.
- « **n'hésitez pas à** » → supprimer ou remplacer par l'impératif direct.
- « **De plus** », « **Par ailleurs** », « **En outre** » en début de phrase à répétition → varier ou couper : souvent la phrase tient sans connecteur.
- « **cruciales** », « **essentielles** », « **incontournables** » → un fait à la place d'un adjectif.
- Vouvoiement/tutoiement : respecter celui du contexte, ne jamais mélanger.

## Volet anglais

- Vocabulaire signature : « pivotal », « testament to », « landscape », « tapestry », « delve », « seamless », « robust », « leverage » (verbe) → mots simples.
- « serves as », « boasts », « stands as » → « is ».
- Constructions en « -ing » creuses : « highlighting », « fostering », « showcasing » en fin de phrase → couper ou dire ce qui se passe.
- « It's not just X, it's Y » → dire Y.
- Em dashes en série → ponctuation normale.
- Title Case Headings → sentence case.

## Sortie

Rendre le texte réécrit, puis en une ou deux lignes ce qui a été retiré (les
catégories, pas l'inventaire). Si le texte est déjà propre, le dire et ne rien
toucher : le but est la justesse, pas la réécriture pour la réécriture.
