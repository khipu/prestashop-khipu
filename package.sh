cd ..
rm -rf khipupayment khipupayment.zip
cp -R prestashop-khipu khipupayment
rm -rf khipupayment/.git khipupayment/.gitignore khipupayment/.gitmodules khipupayment/.idea khipupayment/.DS_Store khipupayment/package.sh 
zip -r khipupayment.zip khipupayment
rm -rf khipupayment
