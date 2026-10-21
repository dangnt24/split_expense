<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

function answer(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function fail(string $message, int $code = 400): never
{
    answer(['ok' => false, 'message' => $message], $code);
}

function cleanString(mixed $value, string $label, int $maxLength = 100): string
{
    if (!is_string($value)) {
        fail("$label không hợp lệ.");
    }
    $value = trim($value);
    $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    if ($value === '' || $length > $maxLength || preg_match('/[\x00-\x1F\x7F]/u', $value)) {
        fail("$label phải có từ 1 đến $maxLength ký tự hợp lệ.");
    }
    return $value;
}

function requiredAmount(mixed $value): int
{
    // Ứng dụng dùng đồng Việt Nam nguyên, không chấp nhận số âm/số lẻ.
    if (!is_int($value) || $value < 1 || $value > 1000000000000) {
        fail('Số tiền phải là số nguyên từ 1 đến 1.000.000.000.000₫.');
    }
    return $value;
}

function requirePassword(array $body): void
{
    $password = $body['password'] ?? null;
    if (!is_string($password) || !hash_equals(APP_EDIT_PASSWORD, $password)) {
        fail('Sai mật khẩu chỉnh sửa/xóa.', 403);
    }
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    $body = $_GET;
    if ($action !== 'get') {
        fail('GET chỉ hỗ trợ lấy dữ liệu.', 405);
    }
} elseif ($method === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== 0) {
        fail('Dữ liệu gửi phải là JSON.', 415);
    }
    $rawBody = file_get_contents('php://input');
    if ($rawBody === false || strlen($rawBody) > 32768) {
        fail('Dữ liệu gửi quá lớn hoặc không đọc được.', 413);
    }
    $body = json_decode($rawBody, true);
    if (!is_array($body)) {
        fail('JSON gửi lên không hợp lệ.');
    }
    $action = $body['action'] ?? '';
    if (!in_array($action, [
        'init_trip', 'add_member', 'edit_member', 'delete_member',
        'add_expense', 'edit_expense', 'delete_expense'
    ], true)) {
        fail('Thao tác không hợp lệ.', 405);
    }
} else {
    fail('Phương thức không hỗ trợ.', 405);
}

$tripName = cleanString($body['trip'] ?? null, 'Tên trip', 100);
$path = __DIR__ . '/data.json';

// c+ không làm mất dữ liệu cũ, khóa file giúp tránh ghi đè khi nhiều người cùng sửa.
$fp = @fopen($path, 'c+');
if ($fp === false) {
    fail('Không mở được data.json. Hãy kiểm tra quyền đọc/ghi trên hosting.', 500);
}
if (!flock($fp, $method === 'GET' ? LOCK_SH : LOCK_EX)) {
    fclose($fp);
    fail('Không thể khóa file dữ liệu.', 500);
}

rewind($fp);
$rawData = stream_get_contents($fp);
if ($rawData === false) {
    flock($fp, LOCK_UN);
    fclose($fp);
    fail('Không đọc được dữ liệu.', 500);
}
$db = trim($rawData) === '' ? ['trips' => []] : json_decode($rawData, true);
if (!is_array($db) || !isset($db['trips']) || !is_array($db['trips'])) {
    flock($fp, LOCK_UN);
    fclose($fp);
    fail('File data.json không đúng định dạng.', 500);
}

$hasTrip = array_key_exists($tripName, $db['trips']);
if (!$hasTrip && $action !== 'init_trip') {
    flock($fp, LOCK_UN);
    fclose($fp);
    fail('Không tìm thấy trip này.', 404);
}

$changed = false;
if ($action === 'init_trip') {
    if (!$hasTrip) {
        $db['trips'][$tripName] = ['members' => [], 'expenses' => []];
        $changed = true;
    }
} elseif ($action === 'get') {
    $trip = $db['trips'][$tripName];
    flock($fp, LOCK_UN);
    fclose($fp);
    answer([
        'ok' => true,
        'trip' => $tripName,
        'members' => array_values($trip['members']),
        'expenses' => array_values($trip['expenses']),
    ]);
} else {
    // Thao tác trên chính phần tử thuộc trip, không sửa một bản sao.
    $trip =& $db['trips'][$tripName];

    switch ($action) {
        case 'add_member':
            $name = cleanString($body['name'] ?? null, 'Tên thành viên', 80);
            foreach ($trip['members'] as $member) {
                if ($member['name'] === $name) {
                    fail('Tên thành viên đã tồn tại.');
                }
            }
            $trip['members'][] = ['id' => bin2hex(random_bytes(8)), 'name' => $name];
            $changed = true;
            break;

        case 'edit_member':
            $id = cleanString($body['id'] ?? null, 'ID thành viên', 64);
            $name = cleanString($body['name'] ?? null, 'Tên thành viên', 80);
            foreach ($trip['members'] as $member) {
                if ($member['name'] === $name && $member['id'] !== $id) {
                    fail('Tên thành viên đã tồn tại.');
                }
            }
            $found = false;
            foreach ($trip['members'] as &$member) {
                if ($member['id'] === $id) {
                    $member['name'] = $name;
                    $found = true;
                    break;
                }
            }
            unset($member);
            if (!$found) fail('Không tìm thấy thành viên.', 404);
            $changed = true;
            break;

        case 'delete_member':
            $id = cleanString($body['id'] ?? null, 'ID thành viên', 64);
            $before = count($trip['members']);
            $trip['members'] = array_values(array_filter(
                $trip['members'], static fn(array $m): bool => $m['id'] !== $id
            ));
            if ($before === count($trip['members'])) fail('Không tìm thấy thành viên.', 404);
            // Theo app gốc: xóa thành viên thì xóa cả chi tiêu của người đó.
            $trip['expenses'] = array_values(array_filter(
                $trip['expenses'], static fn(array $e): bool => $e['memberId'] !== $id
            ));
            $changed = true;
            break;

        case 'add_expense':
            $memberId = cleanString($body['memberId'] ?? null, 'ID người trả', 64);
            $category = isset($body['category']) && trim((string)$body['category']) !== ''
                ? cleanString($body['category'], 'Hạng mục', 100)
                : '';
            $amount = requiredAmount($body['amount'] ?? null);
            $memberExists = false;
            foreach ($trip['members'] as $member) {
                if ($member['id'] === $memberId) {
                    $memberExists = true;
                    break;
                }
            }
            if (!$memberExists) fail('Người trả không tồn tại.', 404);
            $trip['expenses'][] = [
                'id' => bin2hex(random_bytes(8)),
                'memberId' => $memberId,
                'category' => $category,
                'amount' => $amount,
            ];
            $changed = true;
            break;

        case 'edit_expense':
            requirePassword($body);
            $id = cleanString($body['id'] ?? null, 'ID khoản chi', 64);
            $amount = requiredAmount($body['amount'] ?? null);
            $found = false;
            foreach ($trip['expenses'] as &$expense) {
                if ($expense['id'] === $id) {
                    $expense['amount'] = $amount;
                    $found = true;
                    break;
                }
            }
            unset($expense);
            if (!$found) fail('Không tìm thấy khoản chi.', 404);
            $changed = true;
            break;

        case 'delete_expense':
            requirePassword($body);
            $id = cleanString($body['id'] ?? null, 'ID khoản chi', 64);
            $before = count($trip['expenses']);
            $trip['expenses'] = array_values(array_filter(
                $trip['expenses'], static fn(array $e): bool => $e['id'] !== $id
            ));
            if ($before === count($trip['expenses'])) fail('Không tìm thấy khoản chi.', 404);
            $changed = true;
            break;
    }
    unset($trip);
}

if ($changed) {
    $encoded = json_encode($db, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($encoded === false) {
        flock($fp, LOCK_UN);
        fclose($fp);
        fail('Không thể mã hóa dữ liệu.', 500);
    }
    rewind($fp);
    if (!ftruncate($fp, 0)) {
        flock($fp, LOCK_UN);
        fclose($fp);
        fail('Không thể ghi đè file JSON.', 500);
    }
    $written = fwrite($fp, $encoded . "\n");
    if ($written !== strlen($encoded) + 1 || !fflush($fp)) {
        flock($fp, LOCK_UN);
        fclose($fp);
        fail('Lỗi lưu file JSON.', 500);
    }
}
flock($fp, LOCK_UN);
fclose($fp);
answer(['ok' => true, 'message' => 'Đã lưu dữ liệu.']);
