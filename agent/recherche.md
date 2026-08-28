# Recherche

Règle valable pour toute recherche technique, quelle que soit la langue du
document ou de la conversation.

## Chercher en anglais, citer les sources primaires

Les requêtes web se font **en anglais**, même quand la question est posée en
français et que le document produit sera en français.

**Pourquoi** : les sources primaires d'un écosystème technique sont en anglais.
Une requête française ramène des blogs de seconde main qui recopient, résument
mal, et prêtent des propos à des gens sans les sourcer. « nouveautés Symfony 8.2 »
ramène des articles, « Symfony 8.2 release notes » ramène le changelog.

**Hiérarchie des sources**, dans l'ordre :

1. Le dépôt du projet : changelog, release notes, avis de sécurité GitHub, RFC, code
2. La doc officielle et le blog des mainteneurs
3. Les paquets publiés : `npm view`, `composer show`, la registry
4. En dernier recours seulement, la presse technique et les blogs tiers

Un blog tiers ne suffit jamais à établir un fait : il sert à repérer un sujet,
puis on va vérifier à la source. Quand une affirmation ne se vérifie nulle part
ailleurs que dans du contenu secondaire, le dire plutôt que de la reprendre.

## Vérifier contre la version réellement installée

Une réponse juste « en général » peut être fausse pour la version du projet.
Lire `composer.json`, `package.json` ou le lockfile avant de conclure, et
préférer la doc de cette version-là à la doc de la dernière.
