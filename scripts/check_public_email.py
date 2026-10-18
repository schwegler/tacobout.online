"""Guard the public contact surface without dependencies."""
from pathlib import Path
from html.parser import HTMLParser
from html import unescape
import re
ROOT = Path(__file__).resolve().parent.parent
ADDRESS = "andrew" + "@" + "schweg.xyz"
source = (ROOT / "inc/editorial.php").read_text()
assert "antispambot( '" + ADDRESS + "' )" in source
assert 'href="mailto:' + ADDRESS not in source
print("Shared WordPress contact uses core antispambot encoding.")
