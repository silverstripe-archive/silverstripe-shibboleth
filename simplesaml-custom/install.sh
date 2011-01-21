#!/bin/bash
# Installs customizations into thirdparty code - see README.md

target="../thirdparty/simplesaml/"

# Add custom silverstripe module
cp templates/metadata-silverstripe.php "$target/templates/metadata-silverstripe.php"
cp -R modules/silverstripe/ "$target/modules/silverstripe"