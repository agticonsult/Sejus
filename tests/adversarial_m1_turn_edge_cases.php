<?php

/**
 * Empirical Adversarial Challenger Test:
 * TURN credential edge cases for WebRtcJwtService::getIceServers()
 *
 * Specifically tests:
 * - empty array
 * - non-empty array
 * - booleans (true, false)
 * - non-standard objects (stdClass without id, id=null, id=array, id=object, id=bool, custom classes)
 * - non-string/non-numeric scalars and special types (null, float, resource)
 * Verifies ZERO fatal errors / TypeErrors and correct fallback to 'anonymous' or string coerced id.
 */

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\WebRtcJwtService;

$service = new WebRtcJwtService('test_jwt_secret_key', 3600);

$passed = 0;
$failed = 0;

function check_edge_case(string $label, mixed $input, string $expectedUserId) {
    global $service, $passed, $failed;
    try {
        $iceServers = $service->getIceServers($input, 300);
        if (!is_array($iceServers) || count($iceServers) !== 4) {
            echo "  [FAIL] {$label}: Expected 4 ice servers, got " . (is_array($iceServers) ? count($iceServers) : 'non-array') . "\n";
            $failed++;
            return;
        }

        $turnUdp = $iceServers[2];
        $parts = explode(':', $turnUdp['username']);
        $actualUserId = $parts[1] ?? null;

        if ($actualUserId === $expectedUserId) {
            echo "  [PASS] {$label} -> userId resolved to '{$actualUserId}'\n";
            $passed++;
        } else {
            echo "  [FAIL] {$label} -> Expected '{$expectedUserId}', got '{$actualUserId}'\n";
            $failed++;
        }
    } catch (\Throwable $e) {
        echo "  [FAIL] {$label} -> Threw exception: " . get_class($e) . ": " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "===============================================================================\n";
echo "EMPIRICAL ADVERSARIAL TEST: TURN CREDENTIAL EDGE CASES IN getIceServers()\n";
echo "===============================================================================\n\n";

// 1. Array inputs
check_edge_case("Empty array []", [], "anonymous");
check_edge_case("Indexed array [1, 2, 3]", [1, 2, 3], "anonymous");
check_edge_case("Associative array ['id' => 123]", ['id' => 123], "anonymous");
check_edge_case("Associative array ['username' => 'test']", ['username' => 'test'], "anonymous");

// 2. Boolean inputs
check_edge_case("Boolean true", true, "anonymous");
check_edge_case("Boolean false", false, "anonymous");

// 3. Non-standard objects
check_edge_case("Empty stdClass (no id)", new stdClass(), "anonymous");

$objWithNullId = new stdClass();
$objWithNullId->id = null;
check_edge_case("stdClass with id = null", $objWithNullId, "anonymous");

$objWithArrayId = new stdClass();
$objWithArrayId->id = ['nested' => 'array'];
check_edge_case("stdClass with id = array", $objWithArrayId, "anonymous");

$objWithObjId = new stdClass();
$objWithObjId->id = new stdClass();
check_edge_case("stdClass with id = object", $objWithObjId, "anonymous");

$objWithBoolId = new stdClass();
$objWithBoolId->id = true;
check_edge_case("stdClass with id = boolean true", $objWithBoolId, "anonymous");

$objWithBoolFalseId = new stdClass();
$objWithBoolFalseId->id = false;
check_edge_case("stdClass with id = boolean false", $objWithBoolFalseId, "anonymous");

$objWithOtherProps = new stdClass();
$objWithOtherProps->name = "Carlos";
$objWithOtherProps->uuid = "123e4567-e89b-12d3-a456-426614174000";
check_edge_case("stdClass without id but with name/uuid", $objWithOtherProps, "anonymous");

class CustomDummyUserWithoutId {
    public string $name = "Tester";
}
check_edge_case("Custom class instance without id", new CustomDummyUserWithoutId(), "anonymous");

class CustomDummyUserWithIntId {
    public int $id = 888;
}
check_edge_case("Custom class instance with int id=888", new CustomDummyUserWithIntId(), "888");

class CustomDummyUserWithStringId {
    public string $id = "usr_custom_uuid";
}
check_edge_case("Custom class instance with string id", new CustomDummyUserWithStringId(), "usr_custom_uuid");

class CustomDummyUserWithPrivateId {
    private int $id = 777;
}
check_edge_case("Custom class instance with private id (isset returns false)", new CustomDummyUserWithPrivateId(), "anonymous");

class CustomDummyUserWithMagicGet {
    public function __isset($name) {
        return $name === 'id';
    }
    public function __get($name) {
        return $name === 'id' ? 555 : null;
    }
}
check_edge_case("Custom class instance with magic __isset and __get id=555", new CustomDummyUserWithMagicGet(), "555");

// 4. Other types (Scalars, Floats, Resources, Null)
check_edge_case("Null value", null, "anonymous");
check_edge_case("Integer 0", 0, "0");
check_edge_case("Positive integer 42", 42, "42");
check_edge_case("Negative integer -99", -99, "-99");
check_edge_case("Float 123.45", 123.45, "123.45");
check_edge_case("Standard string 'egresso_1'", 'egresso_1', "egresso_1");

$resource = fopen('php://memory', 'r');
check_edge_case("PHP stream resource", $resource, "anonymous");
fclose($resource);

echo "\n===============================================================================\n";
echo "EDGE CASES SUITE SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "===============================================================================\n";

exit($failed > 0 ? 1 : 0);
