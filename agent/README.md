# agent/ : comment je veux que l'agent travaille

Ce dossier ne contient **aucune convention de stack**. Les règles techniques vivent
dans `symfony-react/` et `next/`, et l'état d'un projet ne vit nulle part ici
L'écart entre un code et les guidelines se mesure avec `/gap-analysis`.

Ici vivent les règles **comportementales** : valables sur tous les projets, quelle
que soit la stack, quel que soit le langage. Rédaction, registre, façon de
travailler.

## Chargement

Ces fichiers sont symlinkés dans `~/.claude/rules/`, qui est chargé à chaque
session de chaque projet. Même mécanique que les skills de ce dépôt, symlinkés
dans `~/.claude/skills/`.

```bash
ln -s ~/dev/dev-standards/agent/redaction.md ~/.claude/rules/redaction.md
ln -s ~/dev/dev-standards/agent/recherche.md ~/.claude/rules/recherche.md
```

## Où va quoi

| Nature de la règle | Destination |
|---|---|
| Comportement, rédaction, registre, sur tous les projets | **ici**, `agent/` |
| Règle technique liée à une stack | `symfony-react/`, `next/` ou `tanstack-start/` |
| Vrai pour un seul dépôt | l'`AGENTS.md` de ce dépôt |
| Procédure outillée, déclenchée à la demande | `skills/` |
| Où en est un projet | nulle part : ce n'est pas de la doc. `/gap-analysis` liste les écarts au code, pas l'avancement |
