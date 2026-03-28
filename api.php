<?php
// ============================================================
//  api.php - connects HTML to MySQL
//  Change password below if needed
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// -- CONNECT TO DATABASE --
$conn = new mysqli("localhost", "root", "", "CARPARK");
if ($conn->connect_error) {
    echo json_encode(["error" => "Connection failed"]);
    exit;
}

$action = $_GET['action'] ?? '';
$data   = json_decode(file_get_contents('php://input'), true) ?? [];

// -- RESIDENTS --

if ($action == 'get_residents') {
    $result = $conn->query("SELECT * FROM RESIDENTS");
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    echo json_encode($rows);

} elseif ($action == 'add_resident') {
    $regno = $conn->real_escape_string($data['regno']);
    $name  = $conn->real_escape_string($data['name']);
    $flat  = $conn->real_escape_string($data['flat']);
    $slot  = intval($data['slot']);
    $vtype = $conn->real_escape_string($data['vtype']);
    $conn->query("INSERT INTO RESIDENTS (REGNO,OWNNAME,FLATNO,SLOTNO,VTYPE) VALUES ('$regno','$name','$flat',$slot,'$vtype')");
    $conn->query("UPDATE PARKING_SLOTS SET STATUS='OCCUPIED' WHERE SLOTNO=$slot");
    echo json_encode(["msg" => "Resident added!"]);

} elseif ($action == 'delete_resident') {
    $regno = $conn->real_escape_string($data['regno']);
    $r = $conn->query("SELECT SLOTNO FROM RESIDENTS WHERE REGNO='$regno'");
    if ($row = $r->fetch_assoc()) {
        $conn->query("UPDATE PARKING_SLOTS SET STATUS='FREE' WHERE SLOTNO=" . $row['SLOTNO']);
    }
    $conn->query("DELETE FROM RESIDENTS WHERE REGNO='$regno'");
    echo json_encode(["msg" => "Resident deleted!"]);

// -- GUESTS --

} elseif ($action == 'get_guests') {
    $result = $conn->query("SELECT * FROM GUESTS ORDER BY ENTIME DESC");
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    echo json_encode($rows);

} elseif ($action == 'add_guest') {
    $regno = $conn->real_escape_string($data['regno']);
    $name  = $conn->real_escape_string($data['name']);
    $flat  = $conn->real_escape_string($data['flat']);
    $vtype = $conn->real_escape_string($data['vtype']);
    $time  = date('Y-m-d H:i:s');
    $conn->query("INSERT INTO GUESTS (REGNO,GNAME,FLATNO,VTYPE,ENTIME) VALUES ('$regno','$name','$flat','$vtype','$time')");
    echo json_encode(["msg" => "Guest entry recorded!"]);

} elseif ($action == 'guest_exit') {
    $guestid = intval($data['guestid']);
    $extime  = date('Y-m-d H:i:s');
    $r = $conn->query("SELECT REGNO, GNAME, ENTIME FROM GUESTS WHERE GUESTID=$guestid");
    if ($row = $r->fetch_assoc()) {
        // update exit time
        $conn->query("UPDATE GUESTS SET EXTIME='$extime' WHERE GUESTID=$guestid");
        // calculate fee
        $hours  = ceil((strtotime($extime) - strtotime($row['ENTIME'])) / 3600);
        $hours  = max($hours, 1); // minimum 1 hour
        $amount = $hours * 20;   // Rs. 20 per hour
        // save to payment
        $regno = $row['REGNO'];
        $gname = $row['GNAME'];
        $conn->query("INSERT INTO PAYMENT (REGNO,GNAME,DURATION_HR,AMOUNT,PAYTIME) VALUES ('$regno','$gname',$hours,$amount,'$extime')");
        echo json_encode(["msg" => "Exit recorded! Fee: Rs.$amount for $hours hour(s)"]);
    } else {
        echo json_encode(["msg" => "Guest not found"]);
    }

// -- PARKING SLOTS --

} elseif ($action == 'get_slots') {
    $result = $conn->query("SELECT * FROM PARKING_SLOTS");
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    echo json_encode($rows);

// -- PAYMENTS --

} elseif ($action == 'get_payments') {
    $result = $conn->query("SELECT * FROM PAYMENT ORDER BY PAYTIME DESC");
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    echo json_encode($rows);

// -- COMPLAINTS --

} elseif ($action == 'get_complaints') {
    $result = $conn->query("SELECT * FROM COMPLAINTS ORDER BY COMPDATE DESC");
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    echo json_encode($rows);

} elseif ($action == 'add_complaint') {
    $regno     = $conn->real_escape_string($data['regno']);
    $complaint = $conn->real_escape_string($data['complaint']);
    $time      = date('Y-m-d H:i:s');
    $conn->query("INSERT INTO COMPLAINTS (REGNO,COMPLAINT,COMPDATE) VALUES ('$regno','$complaint','$time')");
    echo json_encode(["msg" => "Complaint submitted!"]);

// -- CHECK VEHICLE --

} elseif ($action == 'check_vehicle') {
    $regno = $conn->real_escape_string($data['regno']);
    $r = $conn->query("SELECT OWNNAME FROM RESIDENTS WHERE REGNO='$regno'");
    if ($r->num_rows > 0) {
        $row = $r->fetch_assoc();
        echo json_encode(["type" => "resident", "msg" => "Resident vehicle - Owner: " . $row['OWNNAME']]);
    } else {
        $r2 = $conn->query("SELECT GNAME FROM GUESTS WHERE REGNO='$regno' ORDER BY GUESTID DESC LIMIT 1");
        if ($r2->num_rows > 0) {
            $row = $r2->fetch_assoc();
            echo json_encode(["type" => "guest", "msg" => "Guest vehicle - Name: " . $row['GNAME']]);
        } else {
            echo json_encode(["type" => "unknown", "msg" => "Vehicle not found in system"]);
        }
    }
}

$conn->close();
?>
