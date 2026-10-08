"""Archive identity remains fixed while current plugins evolve."""
import hashlib
import importlib.util
import pathlib
import subprocess
import tempfile
import unittest

spec = importlib.util.spec_from_file_location("archive", pathlib.Path(__file__).with_name("validate-production-source-archive.py"))
archive = importlib.util.module_from_spec(spec)
spec.loader.exec_module(archive)

class ArchiveContract(unittest.TestCase):
    def test_snapshot_and_tamper_detection(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = pathlib.Path(tmp)
            subprocess.run(["git", "init", "-q", tmp], check=True)
            path = "wp-content/plugins/example/example.php"
            target = root / path
            target.parent.mkdir(parents=True)
            target.write_bytes(b"<?php // original\n")
            subprocess.run(["git", "add", "."], cwd=root, check=True)
            subprocess.run(["git", "-c", "user.name=Test", "-c", "user.email=test@example.invalid", "commit", "-qm", "snapshot"], cwd=root, check=True)
            old_ref = archive.ARCHIVE_COMMIT
            archive.ARCHIVE_COMMIT = subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=root, text=True).strip()
            entry = {"path": path, "sha256": hashlib.sha256(target.read_bytes()).hexdigest()}
            target.write_bytes(b"<?php // upgraded\n")
            try:
                self.assertEqual(archive.archive_bytes(root, entry), b"<?php // original\n")
                with self.assertRaises(AssertionError):
                    archive.archive_bytes(root, {**entry, "sha256": "0" * 64})
                snapshot = root / "snippet.php.txt"
                snapshot.write_bytes(b"original")
                entry = {"path": snapshot.name, "sha256": hashlib.sha256(b"original").hexdigest()}
                snapshot.write_bytes(b"tampered")
                with self.assertRaises(AssertionError):
                    archive.archive_bytes(root, entry)
            finally:
                archive.ARCHIVE_COMMIT = old_ref

    def test_upgrades_allowed_downgrades_rejected(self):
        self.assertTrue(archive.minimum_version("Version: 1.8.4", (1, 7, 9)))
        self.assertTrue(archive.minimum_version("Version: 1.10.0", (1, 7, 9)))
        self.assertFalse(archive.minimum_version("Version: 1.7.8", (1, 7, 9)))
        self.assertFalse(archive.minimum_version("Version: invalid", (1, 7, 9)))

if __name__ == "__main__":
    unittest.main()
