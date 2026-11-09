"""Checkpointed audit log reader with explicit rename-rotation recovery.

The reader never invokes a shell or changes audit rules. A cursor is committed
only after the caller has durably spooled the returned records. Rotation is
handled by draining the renamed inode before explicitly switching to the new
active file.
"""
from __future__ import annotations

import json
import os
import tempfile
from pathlib import Path


class AuditLogIdentityChanged(RuntimeError):
    """The expected audit log inode cannot be found safely."""


class AuditLogReader:
    def __init__(self, log_path: str | Path, cursor_path: str | Path):
        self.log_path = Path(log_path)
        self.cursor_path = Path(cursor_path)
        self.cursor_path.parent.mkdir(parents=True, exist_ok=True)

    def _load_cursor(self) -> dict:
        if not self.cursor_path.exists():
            return {"device": None, "inode": None, "offset": 0, "source_path": None}
        try:
            data = json.loads(self.cursor_path.read_text(encoding="utf-8"))
            required = {"device", "inode", "offset", "source_path"}
            if (not isinstance(data, dict) or set(data) != required
                    or not isinstance(data["offset"], int) or data["offset"] < 0):
                raise ValueError
            return data
        except (OSError, ValueError, json.JSONDecodeError):
            raise RuntimeError("audit reader cursor is invalid; refusing to reset position") from None

    def _find_inode(self, device: int, inode: int) -> Path | None:
        candidates = [self.log_path, *self.log_path.parent.glob(self.log_path.name + ".*")]
        for candidate in candidates:
            try:
                stat = candidate.stat()
            except OSError:
                continue
            if stat.st_dev == device and stat.st_ino == inode and candidate.is_file():
                return candidate
        return None

    def read_batch(self, max_bytes: int = 262144) -> tuple[list[str], dict]:
        if max_bytes < 1 or max_bytes > 4 * 1024 * 1024:
            raise ValueError("max_bytes must be between 1 and 4194304")
        cursor = self._load_cursor()
        if cursor["inode"] is None:
            source = self.log_path
        else:
            source = self._find_inode(cursor["device"], cursor["inode"])
            if source is None:
                raise AuditLogIdentityChanged(
                    "checkpointed audit log inode is missing; refusing to skip rotated records"
                )

        stat = source.stat()
        if stat.st_size < cursor["offset"]:
            raise AuditLogIdentityChanged("audit log was truncated; rotation recovery is required")
        with source.open("rb") as stream:
            stream.seek(cursor["offset"])
            raw = stream.read(max_bytes)
        complete_end = raw.rfind(b"\n") + 1
        complete = raw[:complete_end]
        next_cursor = {
            "device": stat.st_dev,
            "inode": stat.st_ino,
            "offset": cursor["offset"] + complete_end,
            "source_path": str(source),
        }
        lines = [line.decode("utf-8", errors="replace") for line in complete.splitlines()]
        return lines, next_cursor

    def complete_rotation(self, drained_cursor: dict) -> dict:
        """Switch cursor to active log only after the renamed inode is fully drained."""
        required = {"device", "inode", "offset", "source_path"}
        if not isinstance(drained_cursor, dict) or set(drained_cursor) != required:
            raise ValueError("invalid drained cursor")
        old_path = Path(drained_cursor["source_path"])
        try:
            old_stat = old_path.stat()
            active_stat = self.log_path.stat()
        except OSError as exc:
            raise AuditLogIdentityChanged("cannot verify rotated and active audit logs") from exc
        if (old_stat.st_dev, old_stat.st_ino) != (drained_cursor["device"], drained_cursor["inode"]):
            raise AuditLogIdentityChanged("rotated audit log identity changed before completion")
        if old_stat.st_size != drained_cursor["offset"]:
            raise AuditLogIdentityChanged("rotated audit log has unread data; drain it before switching")
        if (active_stat.st_dev, active_stat.st_ino) == (old_stat.st_dev, old_stat.st_ino):
            raise AuditLogIdentityChanged("active audit log has not switched to a new inode")
        return {
            "device": active_stat.st_dev,
            "inode": active_stat.st_ino,
            "offset": 0,
            "source_path": str(self.log_path),
        }

    def commit(self, cursor: dict) -> None:
        required = {"device", "inode", "offset", "source_path"}
        if (not isinstance(cursor, dict) or set(cursor) != required
                or not isinstance(cursor["offset"], int) or cursor["offset"] < 0):
            raise ValueError("invalid audit reader cursor")
        fd, temp_name = tempfile.mkstemp(prefix=".audit-cursor-", dir=str(self.cursor_path.parent))
        try:
            with os.fdopen(fd, "w", encoding="utf-8") as stream:
                json.dump(cursor, stream, separators=(",", ":"))
                stream.flush()
                os.fsync(stream.fileno())
            os.replace(temp_name, self.cursor_path)
            dir_fd = os.open(str(self.cursor_path.parent), os.O_DIRECTORY)
            try:
                os.fsync(dir_fd)
            finally:
                os.close(dir_fd)
        finally:
            if os.path.exists(temp_name):
                os.unlink(temp_name)
