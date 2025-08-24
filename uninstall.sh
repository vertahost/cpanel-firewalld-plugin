#!/usr/bin/env bash
set -euo pipefail
WHM_CGI_DIR="/usr/local/cpanel/whostmgr/docroot/cgi/firewalld_manager"
WHM_ICON="/usr/local/cpanel/whostmgr/docroot/addon_plugins/firewalld_manager.png"
TP_DIR="/usr/local/cpanel/3rdparty/firewalld_manager"

echo "[*] Unregistering AppConfig..."
/usr/local/cpanel/bin/unregister_appconfig firewalld_manager || true

echo "[*] Removing files..."
rm -rf "$WHM_CGI_DIR"
rm -f "$WHM_ICON"
rm -rf "$TP_DIR"

echo "[*] Restarting cpsrvd..."
/usr/local/cpanel/scripts/restartsrv_cpsrvd --restart >/dev/null 2>&1 || true

echo "[+] Uninstall complete."
