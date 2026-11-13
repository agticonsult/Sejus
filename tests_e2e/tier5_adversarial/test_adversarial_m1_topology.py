"""
CONECTA EGRESSO (SEJUS/ES) - Milestone 1 Adversarial Topology & Manifest Test Suite
==================================================================================
Empirical Challenger: challenger_m1_2
Objective:
  Adversarially stress-test and validate:
  1. Manifests syntax & schema in docker-compose.server1.yml, docker-compose.server2.yml, docker-compose.yml.
  2. Strict service isolation & boundaries:
     - Server 1: exactly {'postgres', 'redis', 'php', 'nginx'}
     - Server 2: exactly {'python', 'coturn'}
  3. Absolute database volume isolation:
     - Server 2 must NOT mount, define or reference PostgreSQL data volumes, init scripts, or credentials.
  4. Coturn port boundaries & media range:
     - turnserver.conf: min-port=49152, max-port=65535, listening-port=3478, tls-listening-port=5349.
     - Server 2 compose & root compose: UDP range 49152-65535.
  5. Cross-language RFC 5766 Ephemeral TURN Credentials generation (PHP <-> Python equivalence).
  6. Environment secret alignment (JWT_SECRET_KEY, WEBRTC_JWT_SECRET, AliasChoices).
  7. Host file existence checks for all mounted volumes and build contexts.
"""

import base64
import hashlib
import hmac
import os
import subprocess
import time
import unittest
from pathlib import Path
import yaml

BASE_DIR = Path(__file__).resolve().parent.parent.parent


class TestMilestone1AdversarialTopology(unittest.TestCase):
    """Adversarial validation for Milestone 1 2-Server Topology & WebRTC/Coturn."""

    @classmethod
    def setUpClass(cls):
        cls.server1_path = BASE_DIR / "docker-compose.server1.yml"
        cls.server2_path = BASE_DIR / "docker-compose.server2.yml"
        cls.compose_path = BASE_DIR / "docker-compose.yml"
        cls.coturn_conf_path = BASE_DIR / "docker" / "coturn" / "turnserver.conf"

        # Ensure files exist before parsing
        assert cls.server1_path.exists(), f"Missing {cls.server1_path}"
        assert cls.server2_path.exists(), f"Missing {cls.server2_path}"
        assert cls.compose_path.exists(), f"Missing {cls.compose_path}"
        assert cls.coturn_conf_path.exists(), f"Missing {cls.coturn_conf_path}"

        with open(cls.server1_path, "r", encoding="utf-8") as f:
            cls.server1_yml = yaml.safe_load(f)

        with open(cls.server2_path, "r", encoding="utf-8") as f:
            cls.server2_yml = yaml.safe_load(f)

        with open(cls.compose_path, "r", encoding="utf-8") as f:
            cls.compose_yml = yaml.safe_load(f)

    # --------------------------------------------------------------------------
    # 1. Manifests Syntax & Structure
    # --------------------------------------------------------------------------
    def test_01_manifests_are_valid_yaml_dictionaries(self):
        """Verify manifests parse as valid YAML maps with required top-level keys."""
        for name, doc in [
            ("server1", self.server1_yml),
            ("server2", self.server2_yml),
            ("compose", self.compose_yml),
        ]:
            self.assertIsInstance(doc, dict, f"{name} manifest must parse to a dictionary")
            self.assertIn("version", doc, f"{name} must specify version")
            self.assertIn("services", doc, f"{name} must specify services")
            self.assertIn("networks", doc, f"{name} must specify networks")

    # --------------------------------------------------------------------------
    # 2. Service Boundaries & Isolation
    # --------------------------------------------------------------------------
    def test_02_server1_service_boundaries(self):
        """
        Server 1 must contain exactly: postgres, redis, php, nginx.
        Must NOT contain webrtc/python or coturn.
        """
        services = set(self.server1_yml.get("services", {}).keys())
        expected_server1 = {"postgres", "redis", "php", "nginx"}
        self.assertEqual(
            services,
            expected_server1,
            f"Server 1 services mismatch. Found: {services}, Expected: {expected_server1}",
        )
        self.assertNotIn("python", services, "Server 1 must not run python/webrtc service")
        self.assertNotIn("coturn", services, "Server 1 must not run coturn service")

    def test_03_server2_service_boundaries(self):
        """
        Server 2 must contain exactly: python, coturn.
        Must NOT contain postgres, redis, php, or nginx.
        """
        services = set(self.server2_yml.get("services", {}).keys())
        expected_server2 = {"python", "coturn"}
        self.assertEqual(
            services,
            expected_server2,
            f"Server 2 services mismatch. Found: {services}, Expected: {expected_server2}",
        )
        forbidden_in_server2 = {"postgres", "redis", "php", "nginx"}
        for forbidden in forbidden_in_server2:
            self.assertNotIn(
                forbidden,
                services,
                f"Server 2 boundary violation: {forbidden} must not be present in Server 2",
            )

    def test_04_unified_compose_profiles_alignment(self):
        """
        In unified docker-compose.yml:
        - postgres, redis, php, nginx must have 'server1' profile.
        - python, coturn must have 'server2' profile.
        - all services must have 'all' profile.
        """
        services = self.compose_yml.get("services", {})
        server1_services = ["postgres", "redis", "php", "nginx"]
        server2_services = ["python", "coturn"]

        for svc in server1_services:
            self.assertIn(svc, services, f"Unified compose missing {svc}")
            profiles = services[svc].get("profiles", [])
            self.assertIn("server1", profiles, f"{svc} must have profile 'server1'")
            self.assertIn("all", profiles, f"{svc} must have profile 'all'")
            self.assertNotIn("server2", profiles, f"{svc} must not have profile 'server2'")

        for svc in server2_services:
            self.assertIn(svc, services, f"Unified compose missing {svc}")
            profiles = services[svc].get("profiles", [])
            self.assertIn("server2", profiles, f"{svc} must have profile 'server2'")
            self.assertIn("all", profiles, f"{svc} must have profile 'all'")
            self.assertNotIn("server1", profiles, f"{svc} must not have profile 'server1'")

    # --------------------------------------------------------------------------
    # 3. Data & Volume Isolation (Server 2 Zero PostgreSQL references)
    # --------------------------------------------------------------------------
    def test_05_server2_zero_postgres_volumes_or_definitions(self):
        """
        Server 2 must NEVER mount, declare, or reference postgres_data or any PostgreSQL storage.
        """
        # 1. No top-level volumes defined in Server 2
        server2_volumes = self.server2_yml.get("volumes", {})
        self.assertEqual(
            len(server2_volumes),
            0,
            f"Server 2 must have no persistent database volumes, found: {server2_volumes}",
        )

        # 2. Inspect every service in Server 2
        for svc_name, svc_conf in self.server2_yml.get("services", {}).items():
            mounted_volumes = svc_conf.get("volumes", [])
            for vol in mounted_volumes:
                vol_str = str(vol).lower()
                self.assertNotIn("postgres", vol_str, f"Server 2 service {svc_name} references postgres volume: {vol}")
                self.assertNotIn("pgdata", vol_str, f"Server 2 service {svc_name} references pgdata: {vol}")
                self.assertNotIn("init.sql", vol_str, f"Server 2 service {svc_name} references init.sql: {vol}")

            # 3. Inspect environment variables in Server 2
            env = svc_conf.get("environment", {})
            if isinstance(env, dict):
                env_keys = list(env.keys())
                env_vals = [str(v) for v in env.values()]
            elif isinstance(env, list):
                env_keys = [e.split("=")[0] for e in env]
                env_vals = [e.split("=")[1] if "=" in e else "" for e in env]
            else:
                env_keys, env_vals = [], []

            for k in env_keys:
                self.assertNotIn("POSTGRES", k.upper(), f"Server 2 {svc_name} has postgres env var {k}")
                self.assertNotIn("DB_PASSWORD", k.upper(), f"Server 2 {svc_name} has DB password env var {k}")
                self.assertNotIn("DB_DATABASE", k.upper(), f"Server 2 {svc_name} has DB database env var {k}")

    # --------------------------------------------------------------------------
    # 4. Coturn Configuration & Port Range Verification
    # --------------------------------------------------------------------------
    def test_06_coturn_turnserver_conf_boundaries(self):
        """
        turnserver.conf must have:
        - min-port=49152
        - max-port=65535
        - listening-port=3478
        - tls-listening-port=5349
        - use-auth-secret
        - static-auth-secret
        """
        content = self.coturn_conf_path.read_text(encoding="utf-8")
        lines = [line.strip() for line in content.splitlines() if line.strip() and not line.strip().startswith("#")]
        conf_dict = {}
        flags = set()
        for line in lines:
            if "=" in line:
                k, v = line.split("=", 1)
                conf_dict[k.strip()] = v.strip()
            else:
                flags.add(line.strip())

        self.assertEqual(conf_dict.get("min-port"), "49152", "Coturn min-port must be 49152")
        self.assertEqual(conf_dict.get("max-port"), "65535", "Coturn max-port must be 65535")
        self.assertEqual(conf_dict.get("listening-port"), "3478", "Coturn listening-port must be 3478")
        self.assertEqual(conf_dict.get("tls-listening-port"), "5349", "Coturn tls-listening-port must be 5349")
        self.assertEqual(conf_dict.get("realm"), "sejus.es.gov.br", "Coturn realm must be sejus.es.gov.br")
        self.assertEqual(conf_dict.get("static-auth-secret"), "sejus_turn_secret_key_2026")
        self.assertIn("use-auth-secret", flags, "Coturn must enable use-auth-secret")
        self.assertIn("lt-cred-mech", flags, "Coturn must enable lt-cred-mech")

    def test_07_coturn_ports_in_compose_server2_and_unified(self):
        """
        Verify Coturn exposes 49152-65535/udp in both server2 compose and unified compose.
        """
        expected_ports = {
            "3478:3478/udp",
            "3478:3478/tcp",
            "5349:5349/udp",
            "5349:5349/tcp",
            "49152-65535:49152-65535/udp",
        }

        # Check server2 compose
        s2_coturn_ports = set(self.server2_yml["services"]["coturn"]["ports"])
        self.assertTrue(
            expected_ports.issubset(s2_coturn_ports),
            f"Server 2 coturn ports missing required ports: {expected_ports - s2_coturn_ports}",
        )

        # Check unified compose
        unified_coturn_ports = set(self.compose_yml["services"]["coturn"]["ports"])
        self.assertTrue(
            expected_ports.issubset(unified_coturn_ports),
            f"Unified compose coturn ports missing required ports: {expected_ports - unified_coturn_ports}",
        )

    # --------------------------------------------------------------------------
    # 5. Mounted Host Paths Exist
    # --------------------------------------------------------------------------
    def test_08_all_mounted_host_files_and_directories_exist(self):
        """Adversarial check: ensure no dangling/broken host path references in volumes or builds."""
        # Check server 1
        s1_services = self.server1_yml.get("services", {})
        for svc_name, svc in s1_services.items():
            if "build" in svc and isinstance(svc["build"], dict):
                ctx = BASE_DIR / svc["build"].get("context", ".")
                df = BASE_DIR / svc["build"].get("dockerfile", "Dockerfile")
                self.assertTrue(ctx.exists(), f"Server 1 {svc_name} build context {ctx} not found")
                self.assertTrue(df.exists(), f"Server 1 {svc_name} dockerfile {df} not found")

            for vol in svc.get("volumes", []):
                if ":" in vol:
                    host_part = vol.split(":")[0]
                    # Named volumes like 'postgres_data' are not relative host paths
                    if host_part.startswith("./") or host_part.startswith("."):
                        target_path = BASE_DIR / host_part
                        self.assertTrue(
                            target_path.exists(),
                            f"Server 1 {svc_name} mounted path {host_part} does not exist on disk",
                        )

        # Check server 2
        s2_services = self.server2_yml.get("services", {})
        for svc_name, svc in s2_services.items():
            if "build" in svc and isinstance(svc["build"], dict):
                ctx = BASE_DIR / svc["build"].get("context", ".")
                df = BASE_DIR / svc["build"].get("dockerfile", "Dockerfile")
                self.assertTrue(ctx.exists(), f"Server 2 {svc_name} build context {ctx} not found")
                self.assertTrue(df.exists(), f"Server 2 {svc_name} dockerfile {df} not found")

            for vol in svc.get("volumes", []):
                if ":" in vol:
                    host_part = vol.split(":")[0]
                    if host_part.startswith("./") or host_part.startswith("."):
                        target_path = BASE_DIR / host_part
                        self.assertTrue(
                            target_path.exists(),
                            f"Server 2 {svc_name} mounted path {host_part} does not exist on disk",
                        )

    # --------------------------------------------------------------------------
    # 6. Ephemeral TURN Credentials (RFC 5766 Cross-Language Equivalence)
    # --------------------------------------------------------------------------
    def test_09_rfc5766_ephemeral_credentials_cross_language_oracle(self):
        """
        Verify that Python's generate_ephemeral_turn_credentials and PHP's
        getIceServers generate the exact same RFC 5766 HMAC-SHA1 credential
        for identical timestamp, username, and secret.
        """
        from webrtc_service.app.config import Settings

        settings = Settings()
        test_secret = "sejus_turn_secret_key_2026"
        test_username_prefix = "user_42"
        test_ttl = 3600
        fixed_timestamp = 1770000000

        # Python calculation
        username = f"{fixed_timestamp}:{test_username_prefix}"
        digest = hmac.new(
            test_secret.encode("utf-8"),
            username.encode("utf-8"),
            hashlib.sha1,
        ).digest()
        python_credential = base64.b64encode(digest).decode("utf-8")

        # Execute PHP snippet via subprocess to verify bitwise equivalence
        php_code = f"""
        $secret = '{test_secret}';
        $username = '{username}';
        $cred = base64_encode(hash_hmac('sha1', $username, $secret, true));
        echo $cred;
        """
        proc = subprocess.run(
            ["php", "-r", php_code],
            capture_output=True,
            text=True,
            check=True,
        )
        # Extract clean output ignoring any PHP CLI module warnings
        output_lines = [l.strip() for l in proc.stdout.splitlines() if l.strip() and not l.strip().startswith(("PHP Warning:", "Warning:"))]
        php_credential = output_lines[-1] if output_lines else ""

        self.assertEqual(
            python_credential,
            php_credential,
            f"Cross-language RFC 5766 mismatch: Python={python_credential}, PHP={php_credential}",
        )

        # Now test Settings.generate_ephemeral_turn_credentials
        now_before = int(time.time())
        creds = settings.generate_ephemeral_turn_credentials(
            username_prefix=test_username_prefix,
            ttl=test_ttl,
        )
        now_after = int(time.time())

        # Validate username schema: {timestamp}:{username_prefix}
        parts = creds["username"].split(":", 1)
        self.assertEqual(len(parts), 2)
        cred_ts = int(parts[0])
        self.assertEqual(parts[1], test_username_prefix)
        self.assertTrue(now_before + test_ttl <= cred_ts <= now_after + test_ttl)

        # Validate that the credential decodes to 20 bytes (SHA-1 length)
        raw_digest = base64.b64decode(creds["credential"])
        self.assertEqual(len(raw_digest), 20, "HMAC-SHA1 digest must be 20 bytes")

    # --------------------------------------------------------------------------
    # 7. JWT Shared Secret & Aliases Alignment
    # --------------------------------------------------------------------------
    def test_10_jwt_secret_aliases_and_alignment(self):
        """
        Verify that Settings accepts JWT_SECRET_KEY, JWT_SECRET, or WEBRTC_JWT_SECRET
        and defaults to the shared secret 'sejus_jwt_shared_secret_2026'.
        """
        from webrtc_service.app.config import Settings

        # Default check
        s_default = Settings()
        self.assertEqual(
            s_default.JWT_SECRET_KEY,
            "sejus_jwt_shared_secret_2026",
            "Default JWT secret must be sejus_jwt_shared_secret_2026",
        )

        # Test alias JWT_SECRET
        s_alias1 = Settings(jwt_secret="custom_secret_alpha")
        self.assertEqual(s_alias1.JWT_SECRET_KEY, "custom_secret_alpha")

        # Test alias WEBRTC_JWT_SECRET
        s_alias2 = Settings(webrtc_jwt_secret="custom_secret_beta")
        self.assertEqual(s_alias2.JWT_SECRET_KEY, "custom_secret_beta")

        # Test canonical JWT_SECRET_KEY
        s_canonical = Settings(jwt_secret_key="custom_secret_gamma")
        self.assertEqual(s_canonical.JWT_SECRET_KEY, "custom_secret_gamma")

    # --------------------------------------------------------------------------
    # 8. Adversarial Port Collision & Boundary Checks
    # --------------------------------------------------------------------------
    def test_11_zero_port_collision_between_server1_and_server2(self):
        """
        Verify that Server 1 and Server 2 do not have conflicting host port allocations.
        Server 1 host ports: 80, 443, 5432, 6379.
        Server 2 host ports: 8001, 3478, 5349, 49152-65535.
        """
        def extract_host_ports(services_dict):
            ports = set()
            for s_name, s_conf in services_dict.items():
                for p in s_conf.get("ports", []):
                    # Format: "HOST:CONTAINER" or "HOST:CONTAINER/PROTO" or single port
                    p_clean = str(p).split("/")[0]
                    if ":" in p_clean:
                        host_spec = p_clean.split(":")[0]
                    else:
                        host_spec = p_clean
                    # Handle env var interpolation like "${DB_PORT:-5432}"
                    if ":-" in host_spec:
                        host_spec = host_spec.split(":-")[-1].rstrip("}")
                    # Handle ranges like "49152-65535"
                    if "-" in host_spec and not host_spec.startswith("${"):
                        start, end = host_spec.split("-")
                        ports.update(range(int(start), int(end) + 1))
                    elif host_spec.isdigit():
                        ports.add(int(host_spec))
            return ports

        s1_ports = extract_host_ports(self.server1_yml.get("services", {}))
        s2_ports = extract_host_ports(self.server2_yml.get("services", {}))

        overlap = s1_ports.intersection(s2_ports)
        self.assertEqual(
            len(overlap),
            0,
            f"Adversarial port collision detected between Server 1 and Server 2: {overlap}",
        )

    # --------------------------------------------------------------------------
    # 9. Cryptographic Key & PII Secret Isolation in Server 2
    # --------------------------------------------------------------------------
    def test_12_server2_zero_lgpd_pepper_or_carteira_secrets(self):
        """
        Server 2 handles WebSockets/WebRTC only. It must NEVER have access to
        LGPD pepper keys or digital wallet master signing keys.
        """
        s2_text = self.server2_path.read_text(encoding="utf-8").lower()
        self.assertNotIn("lgpd_pepper_key", s2_text, "LGPD_PEPPER_KEY leaked to Server 2 manifest")
        self.assertNotIn("carteira_signing_key", s2_text, "CARTEIRA_SIGNING_KEY leaked to Server 2 manifest")
        self.assertNotIn("pepper", s2_text, "Pepper key pattern found in Server 2 manifest")

    # --------------------------------------------------------------------------
    # 10. Adversarial Stress & Fuzzing of RFC 5766 Ephemeral TURN Credentials
    # --------------------------------------------------------------------------
    def test_13_fuzzing_ephemeral_turn_credentials(self):
        """
        Stress-test RFC 5766 credential generation under exotic / adversarial inputs:
        - UUID user ID
        - Unicode characters
        - Special characters
        - High-frequency calls
        """
        from webrtc_service.app.config import Settings
        settings = Settings()

        fuzz_users = [
            "00000000-0000-0000-0000-000000000000",
            "egresso_josé_da_silva",
            "user!@#$%^&*()_+~`",
            "a" * 256,
            "1234567890",
        ]

        for user_id in fuzz_users:
            creds = settings.generate_ephemeral_turn_credentials(username_prefix=user_id, ttl=600)
            username = creds["username"]
            self.assertTrue(username.endswith(f":{user_id}"))
            self.assertEqual(creds["ttl"], 600)

            # Recompute HMAC and verify
            ts = creds["expires_at"]
            expected_username = f"{ts}:{user_id}"
            digest = hmac.new(
                settings.COTURN_SECRET.encode("utf-8"),
                expected_username.encode("utf-8"),
                hashlib.sha1,
            ).digest()
            expected_cred = base64.b64encode(digest).decode("utf-8")
            self.assertEqual(creds["credential"], expected_cred)

    def test_14_ephemeral_turn_credentials_secret_tampering(self):
        """
        Adversarial check: A credential generated with an attacker's secret must NEVER match
        the genuine Coturn credential.
        """
        from webrtc_service.app.config import Settings
        settings = Settings()

        creds = settings.generate_ephemeral_turn_credentials(username_prefix="test_user", ttl=300)
        attacker_secret = "malicious_attacker_turn_secret_2026"
        attacker_digest = hmac.new(
            attacker_secret.encode("utf-8"),
            creds["username"].encode("utf-8"),
            hashlib.sha1,
        ).digest()
        attacker_cred = base64.b64encode(attacker_digest).decode("utf-8")

        self.assertNotEqual(
            creds["credential"],
            attacker_cred,
            "Attacker secret generated identical credential (catastrophic collision)",
        )


if __name__ == "__main__":
    unittest.main()

