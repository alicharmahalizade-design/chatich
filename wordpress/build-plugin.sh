#!/usr/bin/env bash
# می‌سازد: dist/dorian-core.zip — بستهٔ آمادهٔ «پیشخان → افزونه‌ها → افزودن → بارگذاری»
#
# چرا این اسکریپت لازم است؟ زیپی که با ابزارهای «استریمی» (کشیدن پوشه در فایل‌منیجر
# هاست، برخی افزونه‌های مرورگر، zip روی stdout) ساخته می‌شود، اندازه و CRC هر فایل را
# به‌جای سرآیندِ محلی در یک data descriptor بعد از داده می‌نویسد. وردپرس زیپ را با
# ZipArchive::CHECKCONS باز می‌کند و libzip های قدیمی چنین آرشیوی را ناسازگار
# می‌شمارند؛ آن‌گاه وردپرس به PclZip برمی‌گردد که همان‌جا خطای «پرونده یافت نشد»
# (PCLZIP_ERR_MISSING_FILE) می‌دهد. `zip` روی یک فایل قابل‌seek این مشکل را ندارد.
set -euo pipefail

cd "$(dirname "$0")"
SRC=dorian-core
OUT=dist/dorian-core.zip

VER=$(sed -n 's/^ \* Version:[[:space:]]*//p' "$SRC/$SRC.php" | head -1)
[ -n "$VER" ] || { echo "نسخه در سرآیند افزونه پیدا نشد"; exit 1; }

rm -rf dist && mkdir -p dist

# -X سرآیندهای اضافیِ سیستم‌عامل را حذف می‌کند؛ خروجی روی فایل است، پس CRC/اندازه
# مستقیم در سرآیند محلی نوشته می‌شود (بدون data descriptor).
zip -r -X -9 "$OUT" "$SRC" \
  -x '*.DS_Store' -x '__MACOSX/*' -x '*/.git/*' -x '*.swp' -x '*~' >/dev/null

# بررسی سلامت + اطمینان از نبودِ data descriptor
unzip -tqq "$OUT"
python3 - "$OUT" <<'PY'
import sys, zipfile
bad = [i.filename for i in zipfile.ZipFile(sys.argv[1]).infolist() if i.flag_bits & 0x8]
if bad:
    sys.exit("data descriptor در این فایل‌ها هست: " + ", ".join(bad))
PY

echo "ساخته شد: $OUT  (نسخهٔ $VER, $(du -h "$OUT" | cut -f1))"
