<?php
session_start();

include('../../config/db.php');
include('../../config/auto_assign.php');

if(!isset($_SESSION['user_id'])){
    header("Location: ../../index.php");
    exit();
}

$client_id = $_SESSION['user_id'];

$campaign_name = mysqli_real_escape_string(
    $conn,
    $_POST['campaign_name']
);

$description = mysqli_real_escape_string(
    $conn,
    $_POST['description']
);

$budget = mysqli_real_escape_string(
    $conn,
    $_POST['budget']
);

$start_date = $_POST['start_date'];
$deadline = $_POST['deadline'];

/*
========================================
DEFAULT STATUS
========================================
*/

$status = 'planning';

/*
========================================
INSERT CAMPAIGN
========================================
*/

$staff_id = getLeastBusyStaff($conn);

mysqli_query($conn, "
    INSERT INTO campaigns (
        campaign_name,
        description,
        budget,
        client_id,
        assigned_staff_id,
        status,
        start_date,
        deadline
    )
    VALUES (
        '$campaign_name',
        '$description',
        '$budget',
        '$client_id',
        '$staff_id',
        'planning',
        '$start_date',
        '$deadline'
    )
");

/*
========================================
UPDATE RETAINER
========================================
*/

$retainerCheck = mysqli_query($conn, "
    SELECT *
    FROM retainers
    WHERE client_id = '$client_id'
");

if(mysqli_num_rows($retainerCheck) > 0){

    $retainer = mysqli_fetch_assoc($retainerCheck);

    $new_total = $retainer['total_amount'] + $budget;
    $new_remaining = $new_total - $retainer['used_amount'];

    mysqli_query($conn, "
        UPDATE retainers
        SET
            total_amount = '$new_total',
            remaining_amount = '$new_remaining'
        WHERE client_id = '$client_id'
    ");

}else{

    mysqli_query($conn, "
        INSERT INTO retainers (
            client_id,
            total_amount,
            used_amount,
            remaining_amount
        )
        VALUES (
            '$client_id',
            '$budget',
            0,
            '$budget'
        )
    ");
}

/*
========================================
NOTIFICATION
========================================
*/

mysqli_query($conn,"
    INSERT INTO notifications
    (
        title,
        message
    )

    VALUES
    (
        'New Campaign Request',
        'A client submitted a new campaign request.'
    )
");

header("Location: ../kanban/main_board.php");
exit();
?>