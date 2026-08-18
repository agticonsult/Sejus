#!/usr/bin/env python3
"""
CONECTA EGRESSO (SEJUS/ES) - Multi-Tier E2E Test Runner
Unified CLI test discovery and execution engine.

Supports:
- Tiers: --tier 1, --tier 2, --tier 3, --tier 4, --tier 5, --all
- Filtering: --filter <pattern>
- Formats: Terminal colorized (ANSI), --json, --output <file>
- Discovery: unittest.TestCase classes and standalone test_* functions across:
    * tests_e2e/tier1_features
    * tests_e2e/tier2_boundaries
    * tests_e2e/tier3_combinations
    * tests_e2e/tier4_scenarios
    * tests_e2e/tier5_adversarial
- Exit Code: 0 on success, 1 on any failure/error.
"""

from __future__ import annotations

import argparse
import importlib.util
import inspect
import json
import os
import re
import sys
import time
import traceback
import unittest
from dataclasses import asdict, dataclass, field
from pathlib import Path
from typing import Any, Callable, Dict, List, Optional, Set, Tuple, Type, Union


# Reconfigure standard streams to UTF-8 with replace fallback if on Windows/legacy terminals
if hasattr(sys.stdout, "reconfigure"):
    try:
        sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    except Exception:
        pass
if hasattr(sys.stderr, "reconfigure"):
    try:
        sys.stderr.reconfigure(encoding="utf-8", errors="replace")
    except Exception:
        pass


# ==============================================================================
# ANSI Terminal Color Support
# ==============================================================================


class Colors:
    """ANSI color codes with automatic Windows / no-color terminal support."""
    ENABLED = True

    HEADER = "\033[95m"
    BLUE = "\033[94m"
    CYAN = "\033[96m"
    GREEN = "\033[92m"
    YELLOW = "\033[93m"
    RED = "\033[91m"
    WHITE = "\033[97m"
    BOLD = "\033[1m"
    DIM = "\033[2m"
    UNDERLINE = "\033[4m"
    RESET = "\033[0m"

    @classmethod
    def disable(cls) -> None:
        cls.ENABLED = False
        cls.HEADER = ""
        cls.BLUE = ""
        cls.CYAN = ""
        cls.GREEN = ""
        cls.YELLOW = ""
        cls.RED = ""
        cls.WHITE = ""
        cls.BOLD = ""
        cls.DIM = ""
        cls.UNDERLINE = ""
        cls.RESET = ""

    @classmethod
    def green(cls, text: str) -> str:
        return f"{cls.GREEN}{text}{cls.RESET}" if cls.ENABLED else text

    @classmethod
    def red(cls, text: str) -> str:
        return f"{cls.RED}{text}{cls.RESET}" if cls.ENABLED else text

    @classmethod
    def yellow(cls, text: str) -> str:
        return f"{cls.YELLOW}{text}{cls.RESET}" if cls.ENABLED else text

    @classmethod
    def cyan(cls, text: str) -> str:
        return f"{cls.CYAN}{text}{cls.RESET}" if cls.ENABLED else text

    @classmethod
    def bold(cls, text: str) -> str:
        return f"{cls.BOLD}{text}{cls.RESET}" if cls.ENABLED else text

    @classmethod
    def dim(cls, text: str) -> str:
        return f"{cls.DIM}{text}{cls.RESET}" if cls.ENABLED else text


# On Windows, attempt to enable virtual terminal processing
if os.name == "nt":
    try:
        import ctypes
        kernel32 = ctypes.windll.kernel32
        kernel32.SetConsoleMode(kernel32.GetStdHandle(-11), 7)
    except Exception:
        pass


# ==============================================================================
# Data Models for Test Results
# ==============================================================================

@dataclass
class TestResult:
    tier: str
    module_name: str
    class_name: Optional[str]
    test_name: str
    status: str  # "PASS", "FAIL", "ERROR", "SKIP"
    duration_seconds: float = 0.0
    error_message: Optional[str] = None
    traceback_str: Optional[str] = None
    docstring: Optional[str] = None

    @property
    def full_name(self) -> str:
        if self.class_name:
            return f"{self.module_name}::{self.class_name}::{self.test_name}"
        return f"{self.module_name}::{self.test_name}"


@dataclass
class TierSummary:
    tier_name: str
    tier_label: str
    total: int = 0
    passed: int = 0
    failed: int = 0
    errors: int = 0
    skipped: int = 0
    duration_seconds: float = 0.0
    results: List[TestResult] = field(default_factory=list)

    @property
    def success(self) -> bool:
        return self.failed == 0 and self.errors == 0


@dataclass
class RunSummary:
    total: int = 0
    passed: int = 0
    failed: int = 0
    errors: int = 0
    skipped: int = 0
    duration_seconds: float = 0.0
    tier_summaries: Dict[str, TierSummary] = field(default_factory=dict)
    all_results: List[TestResult] = field(default_factory=list)

    @property
    def success(self) -> bool:
        return self.failed == 0 and self.errors == 0 and (self.total > 0 or self.skipped > 0)


# ==============================================================================
# Discovery & Execution Engine
# ==============================================================================

TIER_DIRECTORIES: Dict[int, Tuple[str, str]] = {
    1: ("tier1_features", "Tier 1: Feature Coverage Tests"),
    2: ("tier2_boundaries", "Tier 2: Boundary & Corner Cases"),
    3: ("tier3_combinations", "Tier 3: Pairwise Combinatorial Tests"),
    4: ("tier4_scenarios", "Tier 4: Real-World Workload Scenarios"),
    5: ("tier5_adversarial", "Tier 5: Adversarial Hardening Suite"),
}


class E2ETestRunner:
    """Multi-tier E2E test discovery and execution framework."""

    def __init__(
        self,
        base_dir: Optional[Union[str, Path]] = None,
        tiers: Optional[List[int]] = None,
        filter_pattern: Optional[str] = None,
        verbose: bool = False,
        fail_fast: bool = False,
    ):
        self.base_dir = Path(base_dir or Path(__file__).parent.resolve())
        # Ensure project root is in sys.path
        project_root = self.base_dir.parent.resolve()
        if str(project_root) not in sys.path:
            sys.path.insert(0, str(project_root))
        if str(self.base_dir) not in sys.path:
            sys.path.insert(0, str(self.base_dir))

        self.tiers = sorted(list(set(tiers or [1, 2, 3, 4, 5])))
        self.filter_pattern = filter_pattern
        self.verbose = verbose
        self.fail_fast = fail_fast
        self.filter_regex = re.compile(filter_pattern, re.IGNORECASE) if filter_pattern else None

    def _matches_filter(self, module_name: str, class_name: Optional[str], test_name: str) -> bool:
        if not self.filter_regex:
            return True
        full_id = f"{module_name}::{class_name or ''}::{test_name}"
        return bool(
            self.filter_regex.search(module_name)
            or (class_name and self.filter_regex.search(class_name))
            or self.filter_regex.search(test_name)
            or self.filter_regex.search(full_id)
        )

    def _load_module_from_path(self, file_path: Path) -> Any:
        module_name = f"tests_e2e.{file_path.parent.name}.{file_path.stem}"
        spec = importlib.util.spec_from_file_location(module_name, str(file_path))
        if not spec or not spec.loader:
            raise ImportError(f"Could not load module spec for {file_path}")
        module = importlib.util.module_from_spec(spec)
        sys.modules[module_name] = module
        spec.loader.exec_module(module)
        return module

    def discover_tests_in_tier(self, tier_num: int) -> List[Tuple[str, Optional[Type], str, Callable, Optional[str]]]:
        """
        Discovers test targets in a specific tier.
        Returns tuples: (module_name, class_or_None, method_name, callable_func, docstring)
        """
        if tier_num not in TIER_DIRECTORIES:
            return []
        dir_name, _ = TIER_DIRECTORIES[tier_num]
        tier_path = self.base_dir / dir_name
        if not tier_path.exists() or not tier_path.is_dir():
            return []

        discovered: List[Tuple[str, Optional[Type], str, Callable, Optional[str]]] = []
        py_files = sorted(set(list(tier_path.glob("test_*.py")) + list(tier_path.glob("scenario_*.py"))))

        for file_path in py_files:
            try:
                module = self._load_module_from_path(file_path)
            except Exception as err:
                print(Colors.red(f"Error loading {file_path}: {err}"), file=sys.stderr)
                traceback.print_exc()
                continue

            module_name = file_path.stem

            # 1. Discover unittest.TestCase subclasses or classes starting with Test
            for name, obj in inspect.getmembers(module, inspect.isclass):
                if name.startswith("Test") and obj.__module__ == module.__name__:
                    for attr_name in dir(obj):
                        if attr_name.startswith("test_"):
                            method = getattr(obj, attr_name)
                            if callable(method) and self._matches_filter(module_name, name, attr_name):
                                doc = inspect.getdoc(method)
                                discovered.append((module_name, obj, attr_name, method, doc))

            # 2. Discover standalone test_* functions in module
            for name, obj in inspect.getmembers(module, inspect.isfunction):
                if name.startswith("test_") and obj.__module__ == module.__name__:
                    if self._matches_filter(module_name, None, name):
                        doc = inspect.getdoc(obj)
                        discovered.append((module_name, None, name, obj, doc))

        return discovered

    def run_single_test(
        self,
        tier_dir_name: str,
        module_name: str,
        test_class: Optional[Type],
        method_name: str,
        test_func: Callable,
        docstring: Optional[str],
    ) -> TestResult:
        """Executes a single test callable with proper lifecycle management."""
        start_time = time.time()
        status = "PASS"
        err_msg = None
        tb_str = None

        instance = None
        try:
            if test_class:
                if issubclass(test_class, unittest.TestCase):
                    # Use unittest.TestCase lifecycle
                    instance = test_class(methodName=method_name)
                    instance.setUp()
                    getattr(instance, method_name)()
                    instance.tearDown()
                else:
                    instance = test_class()
                    if hasattr(instance, "setUp") and callable(getattr(instance, "setUp")):
                        instance.setUp()
                    getattr(instance, method_name)()
                    if hasattr(instance, "tearDown") and callable(getattr(instance, "tearDown")):
                        instance.tearDown()
            else:
                # Standalone function
                test_func()

        except unittest.SkipTest as skip_err:
            status = "SKIP"
            err_msg = str(skip_err) or "Skipped"
        except AssertionError as assert_err:
            status = "FAIL"
            err_msg = str(assert_err) or "Assertion failed"
            tb_str = traceback.format_exc()
        except Exception as err:
            status = "ERROR"
            err_msg = f"{type(err).__name__}: {err}"
            tb_str = traceback.format_exc()

        duration = time.time() - start_time
        return TestResult(
            tier=tier_dir_name,
            module_name=module_name,
            class_name=test_class.__name__ if test_class else None,
            test_name=method_name,
            status=status,
            duration_seconds=duration,
            error_message=err_msg,
            traceback_str=tb_str,
            docstring=docstring,
        )

    def run(self, quiet: bool = False) -> RunSummary:
        """Executes all requested test tiers and aggregates results."""
        total_start = time.time()
        summary = RunSummary()

        if not quiet:
            print(Colors.cyan("=" * 80))
            print(Colors.bold(Colors.cyan("  CONECTA EGRESSO (SEJUS/ES) - MULTI-TIER E2E TEST RUNNER")))
            print(Colors.cyan("=" * 80))
            print(f"Target Tiers: {', '.join(f'Tier {t}' for t in self.tiers)}")
            if self.filter_pattern:
                print(f"Filter Pattern: {self.filter_pattern}")
            print()

        for tier_num in self.tiers:
            dir_name, label = TIER_DIRECTORIES[tier_num]
            tier_summary = TierSummary(tier_name=dir_name, tier_label=label)
            tier_start = time.time()

            discovered = self.discover_tests_in_tier(tier_num)
            if not quiet:
                print(Colors.bold(Colors.cyan(f"[{label}] - Found {len(discovered)} tests")))

            if not discovered:
                if not quiet:
                    print(Colors.dim(f"  (No tests found matching criteria in tests_e2e/{dir_name})"))
                    print()
                summary.tier_summaries[dir_name] = tier_summary
                continue

            # Group discovered tests by (module_name, test_class)
            grouped_tests: Dict[Tuple[str, Optional[Type]], List[Tuple[str, Callable, Optional[str]]]] = {}
            for module_name, test_class, method_name, test_func, doc in discovered:
                key = (module_name, test_class)
                if key not in grouped_tests:
                    grouped_tests[key] = []
                grouped_tests[key].append((method_name, test_func, doc))

            for (module_name, test_class), methods in grouped_tests.items():
                # Class-level setUpClass
                if test_class and hasattr(test_class, "setUpClass") and callable(getattr(test_class, "setUpClass")):
                    try:
                        test_class.setUpClass()
                    except Exception as err:
                        if not quiet:
                            print(Colors.red(f"  [ERROR] {module_name}::{test_class.__name__}::setUpClass failed: {err}"))
                            traceback.print_exc()

                for method_name, test_func, doc in methods:
                    res = self.run_single_test(dir_name, module_name, test_class, method_name, test_func, doc)
                    tier_summary.results.append(res)
                    tier_summary.total += 1
                    summary.all_results.append(res)
                    summary.total += 1

                    if res.status == "PASS":
                        tier_summary.passed += 1
                        summary.passed += 1
                        status_badge = Colors.green("[PASS]")
                    elif res.status == "FAIL":
                        tier_summary.failed += 1
                        summary.failed += 1
                        status_badge = Colors.red("[FAIL]")
                    elif res.status == "ERROR":
                        tier_summary.errors += 1
                        summary.errors += 1
                        status_badge = Colors.red("[ERROR]")
                    else:  # SKIP
                        tier_summary.skipped += 1
                        summary.skipped += 1
                        status_badge = Colors.yellow("[SKIP]")

                    if not quiet:
                        duration_fmt = Colors.dim(f"({res.duration_seconds * 1000:.1f}ms)")
                        print(f"  {status_badge} {res.full_name} {duration_fmt}")

                        if res.status in ("FAIL", "ERROR"):
                            if res.error_message:
                                print(Colors.red(f"    Message: {res.error_message}"))
                            if self.verbose and res.traceback_str:
                                indented_tb = "\n".join("      " + line for line in res.traceback_str.strip().splitlines())
                                print(Colors.dim(indented_tb))

                    if res.status in ("FAIL", "ERROR") and self.fail_fast:
                        if not quiet:
                            print(Colors.red("\n[!] Fail-fast active: Stopping execution immediately."))
                        break

                # Class-level tearDownClass
                if test_class and hasattr(test_class, "tearDownClass") and callable(getattr(test_class, "tearDownClass")):
                    try:
                        test_class.tearDownClass()
                    except Exception as err:
                        if not quiet:
                            print(Colors.red(f"  [ERROR] {module_name}::{test_class.__name__}::tearDownClass failed: {err}"))

                if self.fail_fast and (tier_summary.failed > 0 or tier_summary.errors > 0):
                    break

            tier_summary.duration_seconds = time.time() - tier_start
            summary.tier_summaries[dir_name] = tier_summary
            if not quiet:
                print(f"  Tier Result: {tier_summary.passed} passed, {tier_summary.failed} failed, {tier_summary.errors} errors, {tier_summary.skipped} skipped in {tier_summary.duration_seconds:.2f}s\n")

            if self.fail_fast and (tier_summary.failed > 0 or tier_summary.errors > 0):
                break

        summary.duration_seconds = time.time() - total_start
        if not quiet:
            self._print_final_report(summary)
        return summary

    def _print_final_report(self, summary: RunSummary) -> None:
        """Prints rich colorized final summary table and status banner."""
        print(Colors.cyan("=" * 80))
        print(Colors.bold("                        FINAL E2E EXECUTION SUMMARY"))
        print(Colors.cyan("=" * 80))

        header = f"{'Tier':<35} | {'Total':<6} | {'Pass':<6} | {'Fail':<6} | {'Skip':<6} | {'Time':<8}"
        print(header)
        print("-" * 80)

        for dir_name, ts in summary.tier_summaries.items():
            line = f"{ts.tier_label:<35} | {ts.total:<6} | {ts.passed:<6} | {ts.failed + ts.errors:<6} | {ts.skipped:<6} | {ts.duration_seconds:.2f}s"
            if ts.failed + ts.errors > 0:
                print(Colors.red(line))
            elif ts.passed > 0:
                print(Colors.green(line))
            else:
                print(Colors.dim(line))

        print("-" * 80)
        tot_line = f"{'TOTAL (ALL SELECTED TIERS)':<35} | {summary.total:<6} | {summary.passed:<6} | {summary.failed + summary.errors:<6} | {summary.skipped:<6} | {summary.duration_seconds:.2f}s"
        print(Colors.bold(tot_line))
        print(Colors.cyan("=" * 80))

        if summary.success:
            banner = Colors.bold(Colors.green("[SUCCESS] ALL TESTS PASSED SUCCESSFULLY (Verdict: CLEAN / PRODUCTION READY)"))
        elif summary.total == 0 and summary.skipped == 0:
            banner = Colors.bold(Colors.yellow("[WARNING] NO TESTS DISCOVERED (Check tiers directory or filter pattern)"))
        else:
            banner = Colors.bold(Colors.red(f"[FAILED] TEST SUITE FAILED ({summary.failed} failures, {summary.errors} errors)"))

        print(f"\n{banner}\n")



# ==============================================================================
# CLI Argument Parsing & Entrypoint
# ==============================================================================

def parse_args(argv: Optional[List[str]] = None) -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="CONECTA EGRESSO (SEJUS/ES) - Multi-Tier E2E Test Suite Runner",
        formatter_class=argparse.RawDescriptionHelpFormatter,
    )

    parser.add_argument(
        "--tier",
        dest="tiers",
        nargs="+",
        type=int,
        choices=[1, 2, 3, 4, 5],
        help="Specific tier(s) to execute (1: Features, 2: Boundaries, 3: Combinations, 4: Scenarios, 5: Adversarial)",
    )
    parser.add_argument(
        "--all",
        action="store_true",
        help="Execute all tiers sequentially (Tiers 1, 2, 3, 4, 5)",
    )
    parser.add_argument(
        "-f", "--filter",
        dest="filter",
        type=str,
        help="Filter pattern to run specific tests matching substring/regex",
    )
    parser.add_argument(
        "-v", "--verbose",
        action="store_true",
        help="Show verbose output including stack traces for failures",
    )
    parser.add_argument(
        "-x", "--fail-fast",
        action="store_true",
        help="Stop on first test failure or error",
    )
    parser.add_argument(
        "--json",
        action="store_true",
        help="Output structured JSON results",
    )
    parser.add_argument(
        "-o", "--output",
        dest="output_file",
        type=str,
        help="Save execution summary or JSON report to specified file path",
    )
    parser.add_argument(
        "-l", "--list",
        action="store_true",
        help="List discovered tests without executing them",
    )
    parser.add_argument(
        "--no-color",
        action="store_true",
        help="Disable ANSI color output in terminal",
    )

    return parser.parse_args(argv)


def main(argv: Optional[List[str]] = None) -> int:
    args = parse_args(argv)

    if args.no_color:
        Colors.disable()

    selected_tiers: List[int]
    if args.all or not args.tiers:
        selected_tiers = [1, 2, 3, 4, 5]
    else:
        selected_tiers = sorted(list(set(args.tiers)))

    runner = E2ETestRunner(
        tiers=selected_tiers,
        filter_pattern=args.filter,
        verbose=args.verbose,
        fail_fast=args.fail_fast,
    )

    if args.list:
        print(Colors.cyan("Discovered Tests matching filter:"))
        total_disc = 0
        for t in selected_tiers:
            disc = runner.discover_tests_in_tier(t)
            _, label = TIER_DIRECTORIES[t]
            print(Colors.bold(f"\n[{label}] ({len(disc)} tests):"))
            for mod, cls_type, m_name, _, doc in disc:
                c_name = f"{cls_type.__name__}::" if cls_type else ""
                print(f"  - {mod}::{c_name}{m_name}")
                if doc and args.verbose:
                    print(Colors.dim(f"      {doc.splitlines()[0]}"))
                total_disc += 1
        print(f"\nTotal discovered: {total_disc}")
        return 0

    summary = runner.run(quiet=args.json)

    # JSON output handling
    if args.json or args.output_file:

        data = {
            "summary": {
                "total": summary.total,
                "passed": summary.passed,
                "failed": summary.failed,
                "errors": summary.errors,
                "skipped": summary.skipped,
                "duration_seconds": summary.duration_seconds,
                "success": summary.success,
            },
            "tiers": {
                name: {
                    "label": ts.tier_label,
                    "total": ts.total,
                    "passed": ts.passed,
                    "failed": ts.failed,
                    "errors": ts.errors,
                    "skipped": ts.skipped,
                    "duration_seconds": ts.duration_seconds,
                }
                for name, ts in summary.tier_summaries.items()
            },
            "results": [
                {
                    "tier": r.tier,
                    "module": r.module_name,
                    "class": r.class_name,
                    "test": r.test_name,
                    "status": r.status,
                    "duration_seconds": r.duration_seconds,
                    "error": r.error_message,
                }
                for r in summary.all_results
            ],
        }

        if args.json:
            print(json.dumps(data, indent=2))

        if args.output_file:
            out_path = Path(args.output_file)
            out_path.parent.mkdir(parents=True, exist_ok=True)
            with open(out_path, "w", encoding="utf-8") as f:
                json.dump(data, f, indent=2)
            print(f"Wrote report to {out_path}")

    # Return exit code: 0 if success, 1 on failure/error
    return 0 if summary.success else 1


if __name__ == "__main__":
    sys.exit(main())
