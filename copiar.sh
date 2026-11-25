#!/bin/sh
# Roda no cPanel: copia site/ para o public_html da conta.
# Só apaga arquivos antigos dentro de _astro/.
set -eu

ORIGEM=$(cd "$(dirname "$0")" && pwd)/site
DESTINO="$HOME/public_html"

[ -f "$ORIGEM/index.html" ] && [ -f "$ORIGEM/.htaccess" ] && [ -d "$ORIGEM/_astro" ] || { echo "Origem incompleta"; exit 1; }
[ -d "$DESTINO/cgi-bin" ] && [ -f "$DESTINO/php.ini" ] || { echo "Destino inesperado"; exit 1; }

cd "$ORIGEM"
find . -mindepth 1 -type d | while read -r d; do
  mkdir -p "$DESTINO/$d"
  chmod 755 "$DESTINO/$d"
done

# Copia com nome temporário e troca no fim, sem arquivo pela metade no ar.
copiar() {
  while read -r f; do
    cp "$f" "$DESTINO/$f.novo"
    chmod 644 "$DESTINO/$f.novo"
    mv -f "$DESTINO/$f.novo" "$DESTINO/$f"
  done
}

find . -type f ! -name '*.html' ! -name .htaccess | copiar
find . -type f -name '*.html' | copiar
echo .htaccess | copiar

for f in "$DESTINO"/_astro/*; do
  [ -f "$f" ] || continue
  [ -f "_astro/$(basename "$f")" ] || rm -f "$f"
done
echo "Copia concluida"
