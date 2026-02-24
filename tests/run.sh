#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

run_unit() {
  echo "[tests] Running unit tests..."
  php "${PLUGIN_ROOT}/tests/unit/run.php"
}

run_integration() {
  local lab_dir="${LAB_DIR:-${PLUGIN_ROOT}/../wp-whittemore-lab}"
  local integration_file="/var/www/html/wp-content/plugins/accessibility-auditor/tests/integration/run.php"

  if [[ ! -d "${lab_dir}" ]]; then
    echo "[tests] Lab directory not found: ${lab_dir}" >&2
    echo "[tests] Set LAB_DIR=/path/to/wp-whittemore-lab and retry." >&2
    exit 1
  fi

  echo "[tests] Running integration tests via docker wpcli (lab: ${lab_dir})..."
  (
    cd "${lab_dir}"
    docker compose run --rm wpcli eval-file "${integration_file}" --path=/var/www/html --allow-root
  )
}

show_help() {
  cat <<'EOF'
Usage:
  tests/run.sh                Run unit tests only
  tests/run.sh unit           Run unit tests only
  tests/run.sh integration    Run integration tests only (requires wp-whittemore-lab)
  tests/run.sh all            Run unit + integration

Environment:
  LAB_DIR=/path/to/wp-whittemore-lab   Override default lab path (../wp-whittemore-lab)
EOF
}

mode="${1:-unit}"

case "${mode}" in
  unit)
    run_unit
    ;;
  integration)
    run_integration
    ;;
  all)
    run_unit
    run_integration
    ;;
  -h|--help|help)
    show_help
    ;;
  *)
    echo "[tests] Unknown mode: ${mode}" >&2
    show_help
    exit 2
    ;;
esac
