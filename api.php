<?php
session_start();
header('Content-Type: application/json');

require 'database.php';

$action = $_GET['action'] ?? '';

// --- API ROUTER ---
switch ($action) {
    case 'signup':
        handle_signup($conn);
        break;
    case 'login':
        handle_login($conn);
        break;
    case 'logout':
        handle_logout();
        break;
    case 'check_session':
        check_session();
        break;
    case 'get_user_data':
        get_user_data($conn);
        break;
    case 'save_state':
        save_state($conn);
        break;
    case 'add_activity':
        add_activity($conn);
        break;
    case 'request_payout':
        request_payout($conn);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

$conn->close();
exit();

// --- HANDLER FUNCTIONS ---

function handle_signup($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $name = $data['name'] ?? '';
    $email = $data['email'] ?? '';
    $phone = $data['phone'] ?? '';
    $password = $data['password'] ?? '';

    if (empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Invalid input.']);
        return;
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $joinedDate = date("F Y");
    $referralCode = 'WINZONE-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 7));

    $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, joinedDate, referralCode) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $name, $email, $phone, $hashed_password, $joinedDate, $referralCode);
    
    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_name'] = $name;
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Email already exists.']);
    }
    $stmt->close();
}

function handle_login($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, name, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid password.']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'User not found.']);
    }
    $stmt->close();
}

function handle_logout() {
    session_destroy();
    echo json_encode(['success' => true]);
}

function check_session() {
    if (isset($_SESSION['user_id'])) {
        echo json_encode(['success' => true, 'isLoggedIn' => true]);
    } else {
        echo json_encode(['success' => true, 'isLoggedIn' => false]);
    }
}

function get_user_data($conn) {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        return;
    }
    $user_id = $_SESSION['user_id'];
    $response = [];

    // Get user data
    $stmt_user = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    $response['userData'] = $result_user->fetch_assoc();
    unset($response['userData']['password']); // Don't send password hash to client
    $stmt_user->close();

    // Get activities
    $stmt_act = $conn->prepare("SELECT icon, color, text, timestamp FROM activities WHERE user_id = ? ORDER BY timestamp DESC LIMIT 10");
    $stmt_act->bind_param("i", $user_id);
    $stmt_act->execute();
    $result_act = $stmt_act->get_result();
    $response['activities'] = $result_act->fetch_all(MYSQLI_ASSOC);
    $stmt_act->close();

    // Get payouts
    $stmt_pay = $conn->prepare("SELECT amount, status, method, timestamp FROM payouts WHERE user_id = ? ORDER BY timestamp DESC");
    $stmt_pay->bind_param("i", $user_id);
    $stmt_pay->execute();
    $result_pay = $stmt_pay->get_result();
    $response['payouts'] = $result_pay->fetch_all(MYSQLI_ASSOC);
    $stmt_pay->close();

    $response['success'] = true;
    echo json_encode($response);
}

function save_state($conn) {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        return;
    }
    $user_id = $_SESSION['user_id'];
    $data = json_decode(file_get_contents('php://input'), true);

    $stmt = $conn->prepare("UPDATE users SET coins = ?, spinsLeft = ?, slotTokens = ?, scratchCards = ?, totalWins = ?, totalSpins = ?, totalPayouts = ? WHERE id = ?");
    $stmt->bind_param("iiiiiiii", $data['coins'], $data['spinsLeft'], $data['slotTokens'], $data['scratchCards'], $data['totalWins'], $data['totalSpins'], $data['totalPayouts'], $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save state.']);
    }
    $stmt->close();
}

function add_activity($conn) {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        return;
    }
    $user_id = $_SESSION['user_id'];
    $data = json_decode(file_get_contents('php://input'), true);

    $stmt = $conn->prepare("INSERT INTO activities (user_id, icon, color, text) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $data['icon'], $data['color'], $data['text']);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to add activity.']);
    }
    $stmt->close();
}

function request_payout($conn) {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        return;
    }
    $user_id = $_SESSION['user_id'];
    $data = json_decode(file_get_contents('php://input'), true);
    
    $requestedAmount = (int)($data['amount'] ?? 0);
    $method = $data['method'] ?? '';
    $details = json_encode($data['details'] ?? []);

    // --- SERVER-SIDE VALIDATION ---
    $stmt_check = $conn->prepare("SELECT coins FROM users WHERE id = ?");
    $stmt_check->bind_param("i", $user_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $user = $result_check->fetch_assoc();
    $stmt_check->close();

    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found.']);
        return;
    }

    if ($requestedAmount < 1000) {
        echo json_encode(['success' => false, 'error' => 'Minimum payout is 1,000 coins.']);
        return;
    }

    if ($requestedAmount > $user['coins']) {
        echo json_encode(['success' => false, 'error' => 'Insufficient balance.']);
        return;
    }
    // --- END SERVER-SIDE VALIDATION ---

    // Use a transaction to ensure data integrity
    $conn->begin_transaction();
    try {
        // Insert into payouts
        $stmt_pay = $conn->prepare("INSERT INTO payouts (user_id, amount, method, details) VALUES (?, ?, ?, ?)");
        $stmt_pay->bind_param("iiss", $user_id, $requestedAmount, $method, $details);
        $stmt_pay->execute();
        $stmt_pay->close();

        // Update user's coins and total payouts
        $stmt_user = $conn->prepare("UPDATE users SET coins = coins - ?, totalPayouts = totalPayouts + 1 WHERE id = ?");
        $stmt_user->bind_param("ii", $requestedAmount, $user_id);
        $stmt_user->execute();
        $stmt_user->close();

        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Transaction failed.']);
    }
}
?>