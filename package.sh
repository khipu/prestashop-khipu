#!/usr/bin/env bash
#
# Genera dist/khipupayment.zip, el archivo que se instala en PrestaShop.
#
# El directorio dentro del zip DEBE llamarse khipupayment: PrestaShop
# identifica el módulo por ese nombre.
#
# El zip sale del COMMIT (git archive HEAD), no del directorio de trabajo: lo
# que no está versionado (logs, respaldos, credenciales, archivos sueltos) no
# puede viajar en el paquete. Por eso hay que confirmar los cambios antes.

set -euo pipefail

cd "$(dirname "$0")"

if ! git diff --quiet HEAD --; then
    echo "Hay cambios sin confirmar en archivos versionados: haz commit antes de empaquetar." >&2
    echo "El zip se arma desde HEAD y no los incluiría." >&2
    exit 1
fi

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

git archive HEAD --prefix=khipupayment/ | tar -x -C "$work"

# Lo que es del repositorio y no del módulo. `vendor` aunque no se versione:
# ahí vive PHPUnit, que no tiene nada que hacer en una tienda en producción.
rm -rf \
    "$work/khipupayment/.git" \
    "$work/khipupayment/.gitignore" \
    "$work/khipupayment/.gitmodules" \
    "$work/khipupayment/.github" \
    "$work/khipupayment/.claude" \
    "$work/khipupayment/.superpowers" \
    "$work/khipupayment/.idea" \
    "$work/khipupayment/.DS_Store" \
    "$work/khipupayment/package.sh" \
    "$work/khipupayment/composer.json" \
    "$work/khipupayment/composer.lock" \
    "$work/khipupayment/composer.phar" \
    "$work/khipupayment/vendor" \
    "$work/khipupayment/docs" \
    "$work/khipupayment/dev" \
    "$work/khipupayment/dist" \
    "$work/khipupayment/tests" \
    "$work/khipupayment/phpunit.xml"
rm -f "$work"/khipupayment/*.iml

mkdir -p dist
rm -f dist/khipupayment.zip
(cd "$work" && zip -qr khipupayment.zip khipupayment)
mv "$work/khipupayment.zip" dist/khipupayment.zip

echo "Listo: dist/khipupayment.zip ($(git rev-parse --short HEAD))"
