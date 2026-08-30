#!/usr/bin/env python3
"""Confronte les affirmations verifiables d'un fichier agent au depot reel.

Ne lit que le code : blocs ``` et spans `...`. La prose n'est jamais analysee,
sinon « uses pnpm as package manager » devient un script `as` inexistant.
"""
import json, os, re, sys

PLACEHOLDER = re.compile(r'ComponentName|component-name|<[^>]*>|\{|\[|\*|\bComponent\.|\bName\b|example|Example')

def code_fragments(txt):
    """Retourne (fragments de code, texte hors code)."""
    frags, rest = [], []
    pos = 0
    for m in re.finditer(r'```.*?```', txt, re.S):
        rest.append(txt[pos:m.start()]); frags.append(m.group(0)); pos = m.end()
    rest.append(txt[pos:])
    prose = '\n'.join(rest)
    for m in re.finditer(r'`([^`\n]+)`', prose):
        frags.append(m.group(1))
    return [re.sub(r'(?m)^\s*#.*$', '', x) for x in frags]

def check(root):
    root = os.path.expanduser(root)
    out = []
    f = next((os.path.join(root, n) for n in ('AGENTS.md', 'CLAUDE.md')
              if os.path.isfile(os.path.join(root, n))
              and os.path.getsize(os.path.join(root, n)) > 80), None)
    if not f: return out
    txt = open(f, encoding='utf-8', errors='replace').read()
    rel, code = os.path.relpath(f, root), '\n'.join(code_fragments(open(f,encoding='utf-8',errors='replace').read()))

    # 1. gestionnaire de paquets contredit par le lockfile
    locks = {'pnpm-lock.yaml': 'pnpm', 'package-lock.json': 'npm', 'yarn.lock': 'yarn'}
    real = next((v for k, v in locks.items() if os.path.exists(os.path.join(root, k))), None)
    if real:
        for other in {'npm', 'yarn', 'pnpm'} - {real}:
            for m in re.finditer(r'\b' + other + r' (?:run )?([a-z][a-z0-9:._-]*)', code):
                out.append((rel, f"gestionnaire: cite `{other} {m.group(1)}` mais le lock est {real}"))

    # 2. scripts cites absents de package.json (et des binaires installes)
    pj = os.path.join(root, 'package.json')
    if os.path.exists(pj):
        try: scripts = set(json.load(open(pj)).get('scripts', {}))
        except Exception: scripts = set()
        binp = os.path.join(root, 'node_modules', '.bin')
        bins = set(os.listdir(binp)) if os.path.isdir(binp) else set()
        builtin = {'install', 'add', 'remove', 'dlx', 'exec', 'create', 'why', 'update', 'audit', 'start'}
        if scripts:
            for m in re.finditer(r'\b(?:pnpm|npm run|yarn) ([a-z][a-z0-9:._-]*)', code):
                s = m.group(1)
                if s in scripts or s in bins or s in builtin: continue
                out.append((rel, f"script inexistant: `{s}`"))

    # 3. chemins cites qui n'existent pas (hors gabarits de nommage)
    for m in re.finditer(r'([a-zA-Z0-9_./@-]+\.(?:tsx?|jsx?|css|json|ya?ml|php|md|mjs|sh)(?![\w-]))', code):
        path = m.group(1)
        if '/' not in path or path.startswith(('@/', 'http', '/')) or PLACEHOLDER.search(path): continue
        if not os.path.exists(os.path.join(root, path)):
            out.append((rel, f"chemin inexistant: `{path}`"))

    # 4. contradiction entre les deux fichiers
    a, c = os.path.join(root, 'AGENTS.md'), os.path.join(root, 'CLAUDE.md')
    if os.path.isfile(a) and os.path.isfile(c):
        ca = open(c, encoding='utf-8', errors='replace').read().strip()
        if ca and not ca.startswith('@AGENTS.md') and os.path.getsize(c) > 80:
            out.append((rel, "CLAUDE.md et AGENTS.md ont chacun du contenu autonome (risque de divergence)"))
    return sorted(set(out))

if __name__ == '__main__':
    total = 0
    for root in sys.argv[1:]:
        res = check(root)
        if res:
            print(f"\n## {root}")
            for rel, msg in res: print(f"  {rel}: {msg}")
            total += len(res)
    print(f"\n{total} ecart(s)")
