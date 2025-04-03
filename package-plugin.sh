zip -r woocomm-cebelca.zip 

#!/bin/bash

# Create a temporary directory for packaging
# TEMP_DIR=$(mktemp -d)
PLUGIN_DIR="cebelcabiz-woocommerce"
mkdir -p "$PLUGIN_DIR"

# Copy the plugin file with proper encoding (UTF-8 without BOM)
cp cebelcabiz.php $PLUGIN_DIR
cp readme.md $PLUGIN_DIR
cp -r lib $PLUGIN_DIR
cp -r includes/ $PLUGIN_DIR
cp -r assets/ $PLUGIN_DIR

# cat cebelcabiz-email-logger.php | iconv -f UTF-8 -t UTF-8 > "$PLUGIN_DIR/cebelcabiz-email-logger.php"

# Create the zip file
# cd "$TEMP_DIR"
zip -r cebelcabiz-woocommerce.zip cebelcabiz-woocommerce

# Move the zip file to the original directory
# mv cebelcabiz-email-logger.zip ../

# Clean up
# rm -rf "$TEMP_DIR"

echo "Plugin packaged successfully. The zip file is at ../cebelcabiz-woocommerce.zip"

