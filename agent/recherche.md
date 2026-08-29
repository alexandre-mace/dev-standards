# Recherche

## Un blog n'établit jamais un fait

Il sert à repérer un sujet, puis on va vérifier à la source. Dans l'ordre :

1. Le dépôt : changelog, release notes, avis de sécurité, RFC, code
2. La doc officielle et le blog des mainteneurs
3. Les paquets publiés : `npm view`, `composer show`, la registry
4. En dernier recours, la presse technique et les blogs tiers

Quand une affirmation ne se vérifie nulle part ailleurs que dans du contenu
secondaire, le dire plutôt que de la reprendre.

## Vérifier contre la version installée

Une réponse juste « en général » peut être fausse pour la version du projet.
Lire le lockfile avant de conclure, et préférer la doc de cette version-là à
celle de la dernière.
