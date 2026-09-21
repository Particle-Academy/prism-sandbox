"""Exercise documented Python imports/builders without sending provider requests."""
import contextlib
import io
import json
from pathlib import Path
import sys
import tempfile
from unittest.mock import MagicMock, patch

sys.path.insert(0, str(Path(sys.argv[1]) / "src"))
from prism import Prism


def reject_network(event, args):
    if event in ("socket.connect", "socket.getaddrinfo"):
        raise AssertionError("Documentation checks must never access the network")


sys.addaudithook(reject_network)
snippets = json.load(sys.stdin)
failed = 0
with tempfile.TemporaryDirectory(prefix="prism-docs-python-") as directory, contextlib.chdir(directory):
    Path("recording.mp3").write_bytes(b"test audio")
    Path("report.pdf").write_bytes(b"%PDF-1.4 test document")
    for snippet in snippets:
        try:
            with contextlib.ExitStack() as stack:
                # The testing guide supplies its own transport and checks a real response.
                if not snippet["path"].endswith("testing.md"):
                    for capability, methods in {
                        "text": ["as_text", "as_stream"],
                        "structured": ["as_structured"],
                        "embeddings": ["as_embeddings"],
                        "images": ["generate"],
                        "moderation": ["as_moderation"],
                        "audio": ["as_text", "as_audio"],
                        "files": ["upload", "get_metadata", "list", "download", "delete"],
                        "batch": ["create", "retrieve", "get_results", "list", "cancel"],
                        "fim": ["as_text"],
                    }.items():
                        cls = type(getattr(Prism, capability)())
                        for method in methods:
                            stack.enter_context(patch.object(cls, method, autospec=True, return_value=MagicMock()))
                with contextlib.redirect_stdout(io.StringIO()):
                    exec(compile(snippet["code"], snippet["path"], "exec"), {})
        except Exception as error:
            failed += 1
            print(f'{snippet["path"]} example {snippet["index"]}: {type(error).__name__}: {error}', file=sys.stderr)
print(f"Python: {len(snippets) - failed}/{len(snippets)} examples pass import/builder checks (network blocked).")
sys.exit(bool(failed))
