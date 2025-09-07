#!/usr/bin/env bash
# clean_plugin.sh - Safely purge contents of svntex2-plugin except protected files
# Usage: ./scripts/clean_plugin.sh
# Optional flags:
#   -y       Skip confirmation prompt
#   -d PATH  Target directory (default: script's parent directory)
#
# Protected (will NOT be deleted):
#   .git/ (git metadata)
#   .gitignore
#   scripts/clean_plugin.sh (this script)
#   README.md (if present)
#
# Produces a summary report of deleted vs kept items.

set -euo pipefail
IFS=$'\n\t'

# Colors
RED="\033[31m"; GREEN="\033[32m"; YELLOW="\033[33m"; CYAN="\033[36m"; RESET="\033[0m"

SKIP_CONFIRM=false
TARGET_DIR="$(cd "$(dirname "$0")/.." && pwd)"

while [[ $# -gt 0 ]]; do
  case "$1" in
    -y) SKIP_CONFIRM=true; shift;;
    -d) TARGET_DIR="$2"; shift 2;;
    *) echo "Unknown arg: $1"; exit 1;;
  esac
done

if [[ ! -d "$TARGET_DIR" ]]; then
  echo -e "${RED}Target directory does not exist: $TARGET_DIR${RESET}" >&2
  exit 1
fi

cd "$TARGET_DIR"

echo -e "${CYAN}Preparing to clean directory:${RESET} $TARGET_DIR"

PROTECTED=( ".git" ".gitignore" "scripts" "scripts/clean_plugin.sh" "README.md" )

mapfile -t ALL_ITEMS < <(find . -mindepth 1 -maxdepth 1 ! -name '.' )

TO_DELETE=()
KEPT=()

is_protected() {
  local item="$1"
  for p in "${PROTECTED[@]}"; do
    if [[ "$item" == "./$p" || "$item" == "$p" ]]; then
      return 0
    fi
  done
  return 1
}

for item in "${ALL_ITEMS[@]}"; do
  if is_protected "$item"; then
    KEPT+=("$item")
  else
    TO_DELETE+=("$item")
  fi
done

if [[ ${#TO_DELETE[@]} -eq 0 ]]; then
  echo -e "${YELLOW}Nothing to delete. Exiting.${RESET}"
  exit 0
fi

echo -e "${YELLOW}Items to be deleted:${RESET}"
for f in "${TO_DELETE[@]}"; do
  echo "  - $f"
done

echo -e "${GREEN}Protected / kept:${RESET}"
for f in "${KEPT[@]}"; do
  echo "  + $f"
done

if ! $SKIP_CONFIRM; then
  read -rp $'\nType YES to proceed: ' ANSWER
  if [[ "$ANSWER" != "YES" ]]; then
    echo -e "${RED}Aborted by user.${RESET}"
    exit 1
  fi
fi

# Deletion phase
ERRORS=()
COUNT=0
for item in "${TO_DELETE[@]}"; do
  if rm -rf "$item" 2>/dev/null; then
    ((COUNT++)) || true
  else
    ERRORS+=("$item")
  fi
done

# Report
echo -e "\n${CYAN}Deletion Summary${RESET}"
echo "Deleted items: $COUNT"
echo "Kept items: ${#KEPT[@]}"
if [[ ${#ERRORS[@]} -gt 0 ]]; then
  echo -e "${RED}Failed to delete:${RESET}"
  for e in "${ERRORS[@]}"; do
    echo "  ! $e"
  done
  exit 2
else
  echo -e "${GREEN}All specified items deleted successfully.${RESET}"
fi

exit 0
