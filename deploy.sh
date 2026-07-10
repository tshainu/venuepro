#!/bin/bash
cd /var/www/idine_lite
git pull origin main
cd packages/web
/root/.bun/bin/bun install
/root/.bun/bin/bun run build
systemctl restart idine
echo 'Deployed at 'Wed May 20 16:57:42 UTC 2026
