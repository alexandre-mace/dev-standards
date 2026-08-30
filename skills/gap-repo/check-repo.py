#!/usr/bin/env python3
"""Confronte le depot dev-standards a lui-meme.

Ne verifie que le mecanique : ce qu'un script tranche sans jugement. Le reste,
coherence entre skills freres et qualite d'ecriture, est dans le SKILL.md.

Usage: python3 check-repo.py [racine]   (defaut: le parent du dossier du script)
"""
import os, re, sys

def read(p):
    with open(p, encoding='utf-8', errors='replace') as f:
        return f.read()

def markdown_files(root):
    for dirpath, dirnames, filenames in os.walk(root):
        dirnames[:] = [d for d in dirnames if d != '.git']
        for n in filenames:
            if n.endswith('.md'):
                yield os.path.join(dirpath, n)

def check(root):
    out = []
    skills_dir = os.path.join(root, 'skills')
    skills = sorted(d for d in os.listdir(skills_dir)
                    if os.path.isfile(os.path.join(skills_dir, d, 'SKILL.md')))

    # 1. tout /skill cite existe. Hors perimetre : les commandes livrees par Claude Code,
    # et les skills qui vivent dans un autre depot.
    known = set(skills) | {'run', 'code-review', 'debug', 'verify', 'doctor', 'batch',
                           'loop', 'compact', 'help', 'review', 'mcp',
                           'propagate-kit', 'sentry-fix-issues'}
    for path in markdown_files(root):
        body = re.sub(r'```.*?```', '', read(path), flags=re.S)
        for i, line in enumerate(body.split('\n'), 1):
            hits = re.findall(r'`/([a-z][a-z0-9-]{2,})`', line)
            # deux occurrences ou plus sur une ligne : ce sont des exemples d'URL
            if len(hits) > 1:
                continue
            for name in hits:
                if name not in known:
                    out.append(f'{os.path.relpath(path, root)}:{i}: cite /{name}, qui n\'existe pas')

    # 2. le README et le disque disent la meme chose
    readme = read(os.path.join(root, 'README.md'))
    listed = set(re.findall(r'^\| `([a-z-]+)` \|', readme, re.M))
    for name in sorted(set(skills) - listed):
        out.append(f'README.md: le skill {name} n\'est pas dans le tableau')
    for name in sorted(listed - set(skills)):
        out.append(f'README.md: le tableau cite {name}, qui n\'existe plus')

    # 3. pas de chemin d'installation en dur : le depot peut vivre ailleurs
    for name in skills:
        p = os.path.join(skills_dir, name, 'SKILL.md')
        for i, line in enumerate(read(p).split('\n'), 1):
            if '~/.claude/' in line:
                out.append(f'skills/{name}/SKILL.md:{i}: chemin d\'installation en dur')

    # 4. les renvois entre guidelines pointent sur une section qui existe
    guides = {}
    for rel in ('symfony-react/symfony-guidelines.md', 'symfony-react/reactony.md',
                'next/next-guidelines.md', 'tanstack-start/tanstack-start-guidelines.md'):
        p = os.path.join(root, rel)
        if os.path.isfile(p):
            key = os.path.basename(rel).replace('.md', '')
            guides[key] = {int(n) for n in re.findall(r'^## (\d+)\.', read(p), re.M)}
    for path in markdown_files(root):
        body = read(path)
        for m in re.finditer(r'(reactony|symfony-guidelines|next-guidelines|tanstack-start-guidelines)'
                             r'(?:\.md)?\s*(?:section\s*)?§?\s*(\d+)', body):
            guide, num = m.group(1), int(m.group(2))
            if guide in guides and guides[guide] and num not in guides[guide]:
                line = body[:m.start()].count('\n') + 1
                out.append(f'{os.path.relpath(path, root)}:{line}: renvoie a {guide} §{num}, section absente')

    # 5. regles d'ecriture verifiables mecaniquement. Le point median n'en est pas :
    # la regle vise les libelles d'interface, et le separateur des en-tetes de veille
    # est un usage legitime qu'un script ne sait pas distinguer.
    for path in markdown_files(root):
        raw = read(path)
        rel = os.path.relpath(path, root)
        for i, line in enumerate(raw.split('\n'), 1):
            # les deux fichiers qui enoncent la regle doivent citer le caractere
            if ('—' in line or '–' in line) and rel not in (
                    'agent/redaction.md', 'skills/technical-writing/SKILL.md'):
                out.append(f'{rel}:{i}: tiret cadratin ou demi-cadratin')
        if '\r\n' in raw:
            out.append(f'{rel}: fins de ligne CRLF')

    return out

if __name__ == '__main__':
    root = sys.argv[1] if len(sys.argv) > 1 else os.path.dirname(os.path.dirname(
        os.path.dirname(os.path.abspath(__file__))))
    findings = check(os.path.realpath(root))
    for f in findings:
        print(f)
    print(f'\n{len(findings)} ecart(s).')
    sys.exit(1 if findings else 0)
