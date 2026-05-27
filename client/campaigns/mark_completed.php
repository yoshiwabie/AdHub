<?php
session_start();

include('../../config/db.php');

if(!isset($_SESSION['user_id'])){
    header("Location: ../../index.php");
    exit();
}

if($_SESSION['role'] != 'client'){
    header("Location: ../../index.php");
    exit();
}

$user_id     = $_SESSION['user_id'];
$campaign_id = (int)($_POST['campaign_id'] ?? 0);

if(!$campaign_id){
    header("Location: ../kanban/main_board.php");
    exit();
}

/*
  Verify this campaign actually belongs to this client
  before updating — prevents tampering via direct POST.
*/
$check = mysqli_query($conn,"
    SELECT campaign_id FROM campaigns
    WHERE campaign_id = '$campaign_id'
    AND client_id     = '$user_id'
    AND status       != 'completed'
    LIMIT 1
");

if(mysqli_num_rows($check) === 0){
    header("Location: campaign_details.php?id=$campaign_id");
    exit();
}

mysqli_query($conn,"
    UPDATE campaigns
    SET status = 'completed'
    WHERE campaign_id = '$campaign_id'
    AND client_id     = '$user_id'
");

/* Notify staff */
$staffQuery = mysqli_query($conn,"
    SELECT assigned_staff_id, campaign_name
    FROM campaigns
    WHERE campaign_id = '$campaign_id'
");
$campaignRow = mysqli_fetch_assoc($staffQuery);
$staffId     = $campaignRow['assigned_staff_id'];
$campName    = mysqli_real_escape_string($conn, $campaignRow['campaign_name']);

if($staffId){
    mysqli_query($conn,"
        INSERT INTO notifications (user_id, title, message)
        VALUES (
            '$staffId',
            'Campaign Marked as Completed',
            'The client has marked \"$campName\" as completed.'
        )
    ");
}

header("Location: campaign_details.php?id=$campaign_id&marked=1");
exit();
?>