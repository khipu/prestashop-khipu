#!/usr/bin/env bash
#
# Genera dist/khipupayment.zip, el archivo que se instala en PrestaShop.
#
# El directorio dentro del zip DEBE llamarse khipupayment: PrestaShop
# identifica el módulo por ese nombre.

set -euo pipefail

cd ..
rm -rf prestashop-khipu-release prestashop-khipu/dist
mkdir prestashop-khipu-release prestashop-khipu/dist
cp -R prestashop-khipu prestashop-khipu-release
cd prestashop-khipu-release
mv prestashop-khipu khipupayment

# Todo lo que es del repositorio y no del módulo. `vendor` incluido: ahí vive
# PHPUnit, que no tiene nada que hacer dentro de una tienda en producción.
rm -rf \
    khipupayment/.git \
    khipupayment/.gitignore \
    khipupayment/.gitmodules \
    khipupayment/.github \
    khipupayment/.claude \
    khipupayment/.idea \
    khipupayment/*.iml \
    khipupayment/.DS_Store \
    khipupayment/package.sh \
    khipupayment/composer.json \
    khipupayment/composer.lock \
    khipupayment/composer.phar \
    khipupayment/vendor \
    khipupayment/docs \
    khipupayment/dev \
    khipupayment/dist \
    khipupayment/tests \
    khipupayment/phpunit.xml

zip -r khipupayment.zip khipupayment
cp khipupayment.zip ../prestashop-khipu/dist
cd ../prestashop-khipu

echo "Listo: dist/khipupayment.zip"
