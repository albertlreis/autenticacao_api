#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 2 ]]; then
  echo "Uso: $0 <diretorio-de-evidencias> <auditoria-produtiva.json>" >&2
  exit 64
fi

evidence_dir="$(realpath -m "$1")"
audit_file="$(realpath "$2")"
case "$evidence_dir" in
  /home/albertreis/sierra/backups/test-user-cleanup/*) ;;
  *) echo "Diretório fora da raiz autorizada de backups." >&2; exit 65 ;;
esac

install -d -m 700 "$evidence_dir"
dump_file="$evidence_dir/sierra-production.sql.gz"
tmp_dump="$evidence_dir/.sierra-production.sql.gz.partial"
candidate_sql="$evidence_dir/.candidate-count.sql"
restore_name="sierra-test-user-restore-$(date +%Y%m%d%H%M%S)"

cleanup() {
  docker rm -f "$restore_name" >/dev/null 2>&1 || true
  rm -f "$tmp_dump"
  rm -f "$candidate_sql"
}
trap cleanup EXIT

php -r '
$audit=json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$emails=$audit["allowlist"]??[];
if (($audit["decision"]??null)!=="eligible_pending_approval_and_fresh_backup" || count($emails)!==7) exit(1);
$quoted=array_map(fn($email)=>"\x27".str_replace("\x27", "\x27\x27", (string)$email)."\x27", $emails);
echo "SELECT COUNT(*) FROM acesso_usuarios WHERE email IN (".implode(",", $quoted).");\n";
' "$audit_file" > "$candidate_sql"
chmod 600 "$candidate_sql"

ssh -o BatchMode=yes sierra-prod \
  'docker exec mysql_server mysqldump -uroot --password=$(docker exec mysql_server printenv MYSQL_ROOT_PASSWORD) --single-transaction --quick --routines --triggers --events --set-gtid-purged=OFF sierra' \
  | gzip -9 > "$tmp_dump"
gzip -t "$tmp_dump"
mv "$tmp_dump" "$dump_file"
chmod 600 "$dump_file"

docker run -d --name "$restore_name" \
  -e MYSQL_ROOT_PASSWORD=restore-only-password \
  -e MYSQL_DATABASE=sierra_restore \
  mysql:8.4 >/dev/null

ready=0
for _ in $(seq 1 45); do
  if docker logs "$restore_name" 2>&1 | grep -q 'MySQL init process done'; then
    ready=1
    break
  fi
  sleep 1
done
[[ "$ready" -eq 1 ]] || { echo "Instância isolada não ficou pronta." >&2; exit 66; }

gzip -dc "$dump_file" | docker exec -i "$restore_name" mysql -uroot -prestore-only-password sierra_restore
candidate_count="$(docker exec -i "$restore_name" mysql -uroot -prestore-only-password -N sierra_restore < "$candidate_sql")"
table_count="$(docker exec "$restore_name" mysql -uroot -prestore-only-password -Nse \
  "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='sierra_restore'" sierra_restore)"
[[ "$candidate_count" -eq 7 ]] || { echo "Clone restaurado não contém os sete candidatos." >&2; exit 67; }
[[ "$table_count" -gt 0 ]] || { echo "Clone restaurado não contém tabelas." >&2; exit 68; }

dump_size="$(stat -c %s "$dump_file")"
dump_sha="$(sha256sum "$dump_file" | awk '{print $1}')"
printf 'backup_file=%s\nsize_bytes=%s\nsha256=%s\nrestore=passed\nrestored_tables=%s\nrestored_candidates=%s\n' \
  "$(basename "$dump_file")" "$dump_size" "$dump_sha" "$table_count" "$candidate_count"
