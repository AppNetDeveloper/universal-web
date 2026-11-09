#!/usr/bin/env bash
# Construye el paquete oficial que descargan deploy/instalar.php y deploy/instalar-vps.sh:
#   dist/universal-web.zip            (el que apunta UWI_PACKAGE_URL: .../releases/latest/download/universal-web.zip)
#   dist/universal-web-<versión>.zip  (el mismo fichero, con la versión en el nombre)
#   dist/*.sha256                     (huella SHA-256 de cada uno; instalar.php exige que coincida)
#
# Contenido: lo que Git sigue (más ficheros nuevos aún sin añadir, respetando .gitignore), SIN .git, data/, tests/,
# CLAUDE.md ni los instaladores. Los ficheros van en la raíz del zip (sin carpeta envolvente).
# Uso:  deploy/hacer_release.sh   y sube los 4 ficheros de dist/ a la release de GitHub.
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."
command -v git >/dev/null || { echo "Hace falta git." >&2; exit 1; }
command -v php >/dev/null || { echo "Hace falta php (con la extensión zip)." >&2; exit 1; }
php -r 'exit(class_exists("ZipArchive") ? 0 : 1);' || { echo "Tu PHP no tiene la extensión zip." >&2; exit 1; }

VERSION="$(sed -n "s/^const UW_VERSION = '\([^']*\)';.*/\1/p" app/core.php)"
[ -n "$VERSION" ] || { echo "No encuentro UW_VERSION en app/core.php." >&2; exit 1; }

OUT=dist
rm -rf "$OUT"; mkdir -p "$OUT"
LIST="$(mktemp)"; trap 'rm -f "$LIST"' EXIT

git ls-files --cached --others --exclude-standard -z \
  | tr '\0' '\n' \
  | grep -Ev '^(data/|tests/|dist/|\.git|CLAUDE\.md$|deploy/(instalar\.php|instalar-vps\.sh|hacer_release\.sh)$|\.tools/)' \
  | while IFS= read -r f; do [ -f "$f" ] && printf '%s\n' "$f"; done | LC_ALL=C sort > "$LIST"
[ -s "$LIST" ] || { echo "Lista de ficheros vacía." >&2; exit 1; }

# Zip determinista (fecha fija, orden fijo): el mismo código da la misma huella.
php -r '
  $z = new ZipArchive();
  if ($z->open($argv[2], ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { fwrite(STDERR, "No puedo crear el zip\n"); exit(1); }
  foreach (file($argv[1], FILE_IGNORE_NEW_LINES) as $f) {
    $z->addFile($f, $f);
    $z->setMtimeName($f, 1767225600);
    $z->setExternalAttributesName($f, ZipArchive::OPSYS_UNIX, ((is_executable($f) ? 0100755 : 0100644) << 16));
  }
  $z->close();
' "$LIST" "$OUT/universal-web.zip"

cp "$OUT/universal-web.zip" "$OUT/universal-web-$VERSION.zip"
( cd "$OUT"
  for z in universal-web.zip "universal-web-$VERSION.zip"; do sha256sum "$z" > "$z.sha256"; done )

echo "Paquete listo (versión $VERSION, $(wc -l < "$LIST") ficheros):"
ls -l "$OUT"
cat "$OUT/universal-web.zip.sha256"
