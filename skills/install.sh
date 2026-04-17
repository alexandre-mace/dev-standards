#!/usr/bin/env bash
# Install (or refresh) symlinks from ~/.claude/skills/ → this directory.
# Idempotent: safe to re-run.
#
# Usage:
#   cd ~/dev/dev-standards/skills && ./install.sh

set -euo pipefail

SKILLS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TARGET_DIR="$HOME/.claude/skills"

mkdir -p "$TARGET_DIR"

for skill_dir in "$SKILLS_DIR"/*/; do
  skill_name="$(basename "$skill_dir")"
  link_path="$TARGET_DIR/$skill_name"

  if [ -L "$link_path" ]; then
    existing_target="$(readlink "$link_path")"
    if [ "$existing_target" = "$skill_dir" ] || [ "$existing_target" = "${skill_dir%/}" ]; then
      echo "OK       $skill_name (symlink already correct)"
      continue
    fi
    echo "RELINK   $skill_name (was pointing to $existing_target)"
    rm "$link_path"
  elif [ -e "$link_path" ]; then
    echo "CONFLICT $skill_name — $link_path exists and is not a symlink. Skipping. Back it up manually and re-run."
    continue
  fi

  ln -s "${skill_dir%/}" "$link_path"
  echo "LINKED   $skill_name"
done

echo
echo "Done. Skills available:"
ls -1 "$TARGET_DIR" | grep -vE '^\.' | sed 's/^/  - /'
