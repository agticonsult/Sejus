"""
CONECTA EGRESSO (SEJUS/ES) - End-to-End Testing Framework
Multi-Tier E2E Test Suite (Tier 1 Features, Tier 2 Boundaries, Tier 3 Combinations, Tier 4 Scenarios)
"""

from tests_e2e.e2e_utils import (
    ES_MUNICIPALITIES,
    MUNICIPALITY_BY_CODE,
    AssertionHelper,
    CryptoVerifier,
    DataGenerator,
    HttpClient,
    HttpResponse,
    MockApiClient,
    MockWebSocketClient,
)

__all__ = [
    "ES_MUNICIPALITIES",
    "MUNICIPALITY_BY_CODE",
    "AssertionHelper",
    "CryptoVerifier",
    "DataGenerator",
    "HttpClient",
    "HttpResponse",
    "MockApiClient",
    "MockWebSocketClient",
]

__version__ = "1.0.0"
