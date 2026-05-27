<?php
function updateCampaignStatus($conn, $campaign_id) {

    // Count milestones and assets
    $milestoneRow = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT COUNT(*) as cnt FROM milestones
        WHERE campaign_id = '$campaign_id'
    "));

    $assetRow = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT COUNT(*) as cnt FROM assets a
        LEFT JOIN milestones m ON a.milestone_id = m.milestone_id
        WHERE m.campaign_id = '$campaign_id'
    "));

    $approvedRow = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT COUNT(*) as cnt FROM milestones
        WHERE campaign_id = '$campaign_id'
        AND status = 'approved'
    "));

    $totalMilestones = $milestoneRow['cnt'] ?? 0;
    $totalAssets     = $assetRow['cnt'] ?? 0;
    $approvedCount   = $approvedRow['cnt'] ?? 0;

    // Check current status first — never downgrade a completed campaign
    $currentRow = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT status FROM campaigns WHERE campaign_id = '$campaign_id'
    "));
    $currentStatus = $currentRow['status'] ?? 'planning';

    if($currentStatus === 'completed') return; // never touch completed

    // Derive new status
    if($totalMilestones > 0 && $approvedCount == $totalMilestones){
        $newStatus = 'review'; // all milestones approved → waiting for client to mark done
    } elseif($totalAssets > 0 || $totalMilestones > 0){
        $newStatus = 'active'; // has milestones or assets → work has started
    } else {
        $newStatus = 'planning'; // nothing yet
    }

    mysqli_query($conn,"
        UPDATE campaigns SET status = '$newStatus'
        WHERE campaign_id = '$campaign_id'
    ");
}
?>