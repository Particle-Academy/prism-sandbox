"""Exercise documented Python imports/builders without sending provider requests."""
import contextlib
import importlib
import io
import json
from pathlib import Path
import re
import sys
import tempfile
from unittest.mock import MagicMock, patch

sys.path.insert(0, str(Path(sys.argv[1]) / "src"))
from prism import Prism
from prism.value_objects.media_file import Media


def reject_network(event, args):
    if event in ("socket.connect", "socket.getaddrinfo"):
        raise AssertionError("Documentation checks must never access the network")


def finished_page() -> MagicMock:
    # A bare MagicMock is truthy and never None, so a documented paging loop
    # (`while page.has_more`) never ends. A listed page here is the last one.
    return MagicMock(has_more=False, last_id=None, data=[])


real_write_bytes = Path.write_bytes


def written(path: Path, data: object) -> int:
    # Terminals return mocks, so a documented "save the generated audio" step
    # receives a mock rather than bytes. Real bytes are still written.
    if isinstance(data, (bytes, bytearray, memoryview)):
        return real_write_bytes(path, data)
    return 0

sys.addaudithook(reject_network)
snippets = json.load(sys.stdin)
failed = 0
with tempfile.TemporaryDirectory(prefix="prism-docs-python-") as directory, contextlib.chdir(directory):
    # The input files the examples name. Examples use these names so this list
    # and the docs cannot drift apart unnoticed: a new name fails here.
    Path("recording.mp3").write_bytes(b"test audio")
    Path("report.pdf").write_bytes(b"%PDF-1.4 test document")
    Path("photo.png").write_bytes(b"\x89PNG\r\n\x1a\n test image")
    Path("photo.jpg").write_bytes(b"\xff\xd8\xff test image")
    Path("notes.md").write_text("Heat pumps move heat rather than making it.\n", encoding="utf-8")

    # Examples on one page build on each other, as a reader follows them.
    namespaces: dict[str, dict[str, object]] = {}
    sys.path.insert(0, directory)

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
                            result = finished_page() if method == "list" else MagicMock()
                            stack.enter_context(patch.object(cls, method, autospec=True, return_value=result))

                    # The guarded fetch is documented; the network stays blocked.
                    # It hands back the media unchanged, as if the fetch succeeded.
                    stack.enter_context(patch.object(Media, "fetch_public", autospec=True, side_effect=lambda media, *a, **k: media))
                    stack.enter_context(patch.object(Path, "write_bytes", autospec=True, side_effect=written))

                with contextlib.redirect_stdout(io.StringIO()):
                    # An example that opens with `# name.py` IS that module: a
                    # later example imports it, as a reader's application would.
                    module = re.match(r"#\s*([A-Za-z_][A-Za-z0-9_]*)\.py\s*$", snippet["code"].splitlines()[0] if snippet["code"] else "")
                    if module:
                        Path(f"{module[1]}.py").write_text(snippet["code"], encoding="utf-8")
                        importlib.import_module(module[1])
                    else:
                        namespace = namespaces.setdefault(snippet["path"], {})
                        exec(compile(snippet["code"], snippet["path"], "exec"), namespace)
        except Exception as error:
            failed += 1
            print(f'{snippet["path"]} example {snippet["index"]}: {type(error).__name__}: {error}', file=sys.stderr)
print(f"Python: {len(snippets) - failed}/{len(snippets)} examples pass import/builder checks (network blocked).")
sys.exit(bool(failed))
