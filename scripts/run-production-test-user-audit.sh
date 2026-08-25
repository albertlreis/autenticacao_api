#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 2 ]]; then
  echo "Uso: $0 <sha-implantado> <relatorio.json>" >&2
  exit 64
fi

deployed_sha="${1,,}"
output="$(realpath -m "$2")"
[[ "$deployed_sha" =~ ^[a-f0-9]{40}$ ]] || { echo "SHA implantado inválido." >&2; exit 64; }
case "$output" in
  /home/albertreis/sierra/backups/test-user-cleanup/*/production-readonly-audit.json) ;;
  *) echo "Destino fora da raiz autorizada de evidências." >&2; exit 65 ;;
esac

install -d -m 700 "$(dirname "$output")"
tmp="$output.partial"
trap 'rm -f "$tmp"' EXIT
timeout 90s ssh -o BatchMode=yes sierra-prod \
  "docker exec sierra_auth_app php artisan app:test-users-cleanup --deployed-sha=$deployed_sha" > "$tmp"
php -r '
$p=json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
if (($p["mode"]??null)!=="dry-run"
    || ($p["schema_version"]??null)!==3
    || ($p["environment"]??null)!=="production"
    || ($p["source"]["deployed_sha"]??null)!==$argv[2]
    || ($p["found_count"]??null)!==7
    || ($p["decision"]??null)!=="eligible_pending_approval_and_fresh_backup") exit(1);
' "$tmp" "$deployed_sha"
mv "$tmp" "$output"
chmod 600 "$output"
sha256sum "$output"
