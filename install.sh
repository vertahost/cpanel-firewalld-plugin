#!/usr/bin/env bash
set -euo pipefail

SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WHM_CGI_DIR="/usr/local/cpanel/whostmgr/docroot/cgi/firewalld_manager"
WHM_ICON_DIR="/usr/local/cpanel/whostmgr/docroot/addon_plugins"
TP_DIR="/usr/local/cpanel/3rdparty/firewalld_manager"
APPCONF="${SRC_DIR}/appconfig/firewalld_manager.conf"

echo "[*] Installing Firewalld Manager (WHM)..."

install -d -m 755 "$WHM_CGI_DIR" "$WHM_ICON_DIR" "$TP_DIR/scripts"

install -m 755 "${SRC_DIR}/whostmgr/docroot/cgi/firewalld_manager/index.php" "$WHM_CGI_DIR/index.php"
install -m 755 "${SRC_DIR}/whostmgr/docroot/cgi/firewalld_manager/api.php"   "$WHM_CGI_DIR/api.php"

if [[ -f "${SRC_DIR}/icons/firewalld_manager.png" ]]; then
  install -m 644 "${SRC_DIR}/icons/firewalld_manager.png" "$WHM_ICON_DIR/firewalld_manager.png"
fi

install -m 755 "${SRC_DIR}/3rdparty/firewalld_manager/scripts/fwctl.sh" "$TP_DIR/scripts/fwctl.sh"

echo "[*] Registering with AppConfig..."
/usr/local/cpanel/bin/register_appconfig "$APPCONF"

echo "[*] Restarting cpsrvd..."
/usr/local/cpanel/scripts/restartsrv_cpsrvd --restart >/dev/null 2>&1 || true

echo "[+] Install complete. Open WHM » Plugins » Firewalld Manager"
