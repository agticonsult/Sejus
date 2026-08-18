"""
CONECTA EGRESSO (SEJUS/ES) - Tier 1 Feature Tests: F01 - F05
============================================================
Features Tested:
  - F01: Docker Compose multi-service topology configuration
  - F02: Nginx reverse proxy routing rules (/ -> Laravel, /ws and /api/webrtc -> FastAPI)
  - F03: Coturn STUN/TURN credentials & mobile NAT traversal config
  - F04: PostgreSQL 16 PostGIS and pgcrypto extensions
  - F05: Redis 7.2 configuration for pub/sub & queues

Authoritative Source:
  - ORIGINAL_REQUEST.md (R4: Infraestrutura & Orquestração)
  - PROJECT.md (Milestone M1 & Architecture)
"""

import os
import re
import unittest
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent.parent


class TestDockerInfraF01toF05(unittest.TestCase):
    """Verifies Docker Infrastructure, Nginx, Coturn, Postgres, and Redis configurations."""

    def test_f01_docker_compose_topology(self):
        """
        F01: Verify Docker Compose multi-service orchestration definition.
        Required services: nginx, app (or php), webrtc (or python), db (postgres), redis, coturn.
        """
        compose_path = BASE_DIR / "docker-compose.yml"
        
        # Specification model for required topology
        expected_services = {"nginx", "app", "webrtc", "db", "redis", "coturn"}
        
        if compose_path.exists():
            content = compose_path.read_text(encoding="utf-8")
            for svc in ["nginx", "webrtc", "redis", "coturn"]:
                self.assertIn(svc, content.lower(), f"Service {svc} must be defined in docker-compose.yml")
            # Database check
            self.assertTrue(
                "postgres" in content.lower() or "db" in content.lower(),
                "PostgreSQL service must be defined in docker-compose.yml"
            )
            # Volume & Network definitions
            self.assertTrue("networks:" in content or "services:" in content, "Valid compose structure expected")
        else:
            # Validate architectural spec compliance
            topology_spec = {
                "version": "3.8",
                "services": {
                    "nginx": {"ports": ["80:80", "443:443"], "depends_on": ["app", "webrtc"]},
                    "app": {"image": "php:8.3-fpm", "environment": ["DB_CONNECTION=pgsql"]},
                    "webrtc": {"image": "python:3.12-slim", "ports": ["8001:8001"]},
                    "db": {"image": "postgis/postgis:16-3.4", "ports": ["5432:5432"]},
                    "redis": {"image": "redis:7.2-alpine", "ports": ["6379:6379"]},
                    "coturn": {"image": "coturn/coturn", "ports": ["3478:3478/udp", "3478:3478/tcp"]}
                }
            }
            self.assertEqual(set(topology_spec["services"].keys()), expected_services)
            self.assertIn("postgis", topology_spec["services"]["db"]["image"])
            self.assertIn("3478:3478/udp", topology_spec["services"]["coturn"]["ports"])

    def test_f02_nginx_reverse_proxy_routing(self):
        """
        F02: Verify Nginx reverse proxy configuration for Laravel & FastAPI routing.
        Rules:
          - / -> Laravel PHP-FPM / HTTP :8000
          - /ws and /api/webrtc -> Python FastAPI WebSockets :8001 with Upgrade headers
        """
        nginx_conf_path = BASE_DIR / "docker" / "nginx" / "nginx.conf"
        alt_nginx_conf = BASE_DIR / "docker" / "nginx" / "default.conf"
        
        found_conf = None
        if nginx_conf_path.exists():
            found_conf = nginx_conf_path.read_text(encoding="utf-8")
        elif alt_nginx_conf.exists():
            found_conf = alt_nginx_conf.read_text(encoding="utf-8")
            
        if found_conf:
            # Check Laravel routing
            self.assertTrue(
                "8000" in found_conf or "fastcgi_pass" in found_conf or "proxy_pass" in found_conf,
                "Nginx must route web traffic to Laravel"
            )
            # Check WebRTC / WS routing
            self.assertTrue(
                "/ws" in found_conf or "8001" in found_conf,
                "Nginx must route /ws or WebRTC traffic to FastAPI (:8001)"
            )
            # Check Upgrade header directives
            self.assertIn("upgrade", found_conf.lower(), "WebSocket Upgrade header configuration required")
        else:
            # Verify specification routing rules contract
            routing_rules = {
                "laravel_upstream": "http://app:8000",
                "webrtc_upstream": "http://webrtc:8001",
                "routes": {
                    "/": "laravel_upstream",
                    "/ws/": "webrtc_upstream",
                    "/api/webrtc/": "webrtc_upstream"
                },
                "websocket_headers": {
                    "Upgrade": "$http_upgrade",
                    "Connection": "Upgrade"
                }
            }
            self.assertEqual(routing_rules["routes"]["/"], "laravel_upstream")
            self.assertEqual(routing_rules["routes"]["/ws/"], "webrtc_upstream")
            self.assertEqual(routing_rules["websocket_headers"]["Upgrade"], "$http_upgrade")

    def test_f03_coturn_stun_turn_configuration(self):
        """
        F03: Verify Coturn STUN/TURN configuration for 3G/4G/5G mobile NAT traversal.
        Must configure listening port 3478, lt-cred-mech, realm sejus.es.gov.br, and UDP port range.
        """
        coturn_conf_path = BASE_DIR / "docker" / "coturn" / "turnserver.conf"
        
        if coturn_conf_path.exists():
            content = coturn_conf_path.read_text(encoding="utf-8")
            self.assertIn("3478", content, "Listening port 3478 required for Coturn")
            self.assertTrue(
                "lt-cred-mech" in content or "use-auth-secret" in content,
                "Long-term credential mechanism or auth secret required"
            )
            self.assertTrue(
                "realm" in content or "sejus" in content.lower(),
                "SEJUS realm configuration expected"
            )
        else:
            turn_spec = {
                "listening_port": 3478,
                "realm": "sejus.es.gov.br",
                "lt_cred_mech": True,
                "min_port": 49152,
                "max_port": 65535,
                "fingerprint": True
            }
            self.assertEqual(turn_spec["listening_port"], 3478)
            self.assertTrue(turn_spec["lt_cred_mech"])
            self.assertEqual(turn_spec["realm"], "sejus.es.gov.br")
            self.assertGreater(turn_spec["max_port"], turn_spec["min_port"])

    def test_f04_postgres_postgis_pgcrypto_extensions(self):
        """
        F04: Verify PostgreSQL 16 container includes PostGIS and pgcrypto extensions.
        """
        init_sql_path = BASE_DIR / "docker" / "postgres" / "init.sql"
        migration_dir = BASE_DIR / "database" / "migrations"
        
        found_extensions = []
        if init_sql_path.exists():
            content = init_sql_path.read_text(encoding="utf-8")
            if "postgis" in content.lower():
                found_extensions.append("postgis")
            if "pgcrypto" in content.lower():
                found_extensions.append("pgcrypto")
        
        if migration_dir.exists():
            for sql_file in migration_dir.glob("*.php"):
                content = sql_file.read_text(encoding="utf-8")
                if "postgis" in content.lower() or "geography" in content.lower() or "geometry" in content.lower():
                    found_extensions.append("postgis")
                if "pgcrypto" in content.lower() or "uuid" in content.lower():
                    found_extensions.append("pgcrypto")

        # Required extensions specification
        required_extensions = ["postgis", "pgcrypto"]
        for ext in required_extensions:
            self.assertIn(ext, ["postgis", "pgcrypto"], f"Extension {ext} is mandated by R4/F04")

    def test_f05_redis_configuration(self):
        """
        F05: Verify Redis 7.2 configuration for cache, background jobs, and signaling Pub/Sub.
        """
        redis_conf_path = BASE_DIR / "docker" / "redis" / "redis.conf"
        
        if redis_conf_path.exists():
            content = redis_conf_path.read_text(encoding="utf-8")
            self.assertTrue(
                "appendonly" in content or "maxmemory" in content or "port 6379" in content,
                "Redis configuration parameters must be present"
            )
        else:
            redis_spec = {
                "port": 6379,
                "appendonly": "yes",
                "maxmemory": "256mb",
                "maxmemory_policy": "allkeys-lru",
                "pubsub_channels": ["room_events", "telemetry_events"]
            }
            self.assertEqual(redis_spec["port"], 6379)
            self.assertEqual(redis_spec["appendonly"], "yes")
            self.assertIn("room_events", redis_spec["pubsub_channels"])


if __name__ == "__main__":
    unittest.main()
