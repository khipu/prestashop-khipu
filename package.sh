#!/usr/bin/env bash

cd ..
rm -rf prestashop-khipu-release
mkdir prestashop-khipu-release
cp -R prestashop-khipu prestashop-khipu-release
cd prestashop-khipu-release
mv prestashop-khipu khipupayment
rm -rf \
    khipupayment/.git \
    khipupayment/.gitignore \
    khipupayment/.gitmodules \
    khipupayment/.idea \
    khipupayment/.DS_Store \
    khipupayment/package.sh \
    khipupayment/composer.phar
zip -r khipupayment.zip khipupayment
cd ../prestashop-khipu
