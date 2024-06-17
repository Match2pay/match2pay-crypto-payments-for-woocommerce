#!/bin/bash

ZIP_FILE="match2pay-crypto-payments-for-woocommerce.zip"
TEMP_DIR="temp"
NEW_SUBDIR="match2pay-crypto-payments-for-woocommerce"

unzip "$ZIP_FILE" -d "$TEMP_DIR"

mkdir "$TEMP_DIR/$NEW_SUBDIR"

mv "$TEMP_DIR"/* "$TEMP_DIR/$NEW_SUBDIR"

cd "$TEMP_DIR"
rm "../$ZIP_FILE"
zip -r "../$ZIP_FILE" "$NEW_SUBDIR"
cd ..

rm -rf "$TEMP_DIR"
echo "New ZIP file created: $ZIP_FILE"
