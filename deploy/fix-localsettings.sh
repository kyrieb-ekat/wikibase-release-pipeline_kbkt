#!/bin/bash
# Remove or comment out the last line of LocalSettings.php
sed -i '$s/^/# /' /var/www/html/LocalSettings.php
