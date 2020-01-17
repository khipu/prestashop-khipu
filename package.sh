#!/usr/bin/env bash

cd ..
rm -rf prestashop-khipu-release prestashop-khipu/dist
mkdir prestashop-khipu-release prestashop-khipu/dist
cp -R prestashop-khipu prestashop-khipu-release
cd prestashop-khipu-release
mv prestashop-khipu khipupayment
rm -rf \
    khipupayment/.git \
    khipupayment/.gitignore \
    khipupayment/.gitmodules \
    khipupayment/.idea \
    khipupayment/*.iml \
    khipupayment/.DS_Store \
    khipupayment/package.sh \
    khipupayment/composer.phar
zip -r khipupayment.zip khipupayment
cp khipupayment.zip ../prestashop-khipu/dist
cd ../prestashop-khipu
