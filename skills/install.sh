#!/usr/bin/env bash
# Install (or refresh) the symlinks Claude Code reads:
#   ~/.claude/skills/<name>  ->  dev-standards/skills/<name>
#   ~/.claude/rules/<file>   ->  dev-standards/agent/<file>
#   ~/.claude/output-styles/  ->  dev-standards/agent/output-styles/<file>
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

# Behavioural rules: agent/*.md -> ~/.claude/rules/
RULES_SRC="$(dirname "$SKILLS_DIR")/agent"
RULES_DIR="$HOME/.claude/rules"

if [ -d "$RULES_SRC" ]; then
  mkdir -p "$RULES_DIR"
  for rule in "$RULES_SRC"/*.md; do
    [ -e "$rule" ] || continue
    rule_name="$(basename "$rule")"
    [ "$rule_name" = "README.md" ] && continue
    link_path="$RULES_DIR/$rule_name"

    if [ -L "$link_path" ]; then
      if [ "$(readlink "$link_path")" = "$rule" ]; then
        echo "OK       rules/$rule_name (symlink already correct)"
        continue
      fi
      rm "$link_path"
    elif [ -e "$link_path" ]; then
      echo "CONFLICT rules/$rule_name — exists and is not a symlink. Skipping."
      continue
    fi

    ln -s "$rule" "$link_path"
    echo "LINKED   rules/$rule_name"
  done
fi

# Output styles: agent/output-styles/*.md -> ~/.claude/output-styles/
STYLES_SRC="$RULES_SRC/output-styles"
STYLES_DIR="$HOME/.claude/output-styles"

if [ -d "$STYLES_SRC" ]; then
  mkdir -p "$STYLES_DIR"
  for style in "$STYLES_SRC"/*.md; do
    [ -e "$style" ] || continue
    style_name="$(basename "$style")"
    link_path="$STYLES_DIR/$style_name"

    if [ -L "$link_path" ]; then
      if [ "$(readlink "$link_path")" = "$style" ]; then
        echo "OK       output-styles/$style_name (symlink already correct)"
        continue
      fi
      rm "$link_path"
    elif [ -e "$link_path" ]; then
      echo "CONFLICT output-styles/$style_name — exists and is not a symlink. Skipping."
      continue
    fi

    ln -s "$style" "$link_path"
    echo "LINKED   output-styles/$style_name"
  done
fi

echo
echo "Done. Skills available:"
ls -1 "$TARGET_DIR" | grep -vE '^\.' | sed 's/^/  - /'
if [ -d "$RULES_DIR" ]; then
  echo "Rules loaded in every session:"
  ls -1 "$RULES_DIR" | sed 's/^/  - /'
fi
