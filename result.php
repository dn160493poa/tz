<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Result code</title>
</head>
<body>
<div>
    <?php if (isset($_SESSION['data']) && $_SESSION['data']):?>
        <?php $data = $_SESSION['data']; ?>
        <?php foreach ($data as $item):?>
            <div style="font-weight: 600;">As a result report for Customer ID <?= $item->customerId; ?> we have:</div>
            <div style="font-style: italic;">➔ Number of customer's calls within same continent: <strong style="color: #3b89ff"><?= $item->toSameContinentCalls; ?></strong></div>
            <div style="font-style: italic;">➔ Total duration of customer's calls within same continent: <strong style="color: #3b89ff"><?= $item->toSameContinentDuration; ?></strong></div>
            <div style="font-style: italic;">➔ Number of all customer's calls: <strong style="color: #3b89ff"><?= $item->totalCalls; ?></strong></div>
            <div style="font-style: italic;">➔ Total duration of all customer's calls: <strong style="color: #3b89ff"><?= $item->totalDuration; ?></strong></div>
        <hr>
        <?php endforeach; ?>
        <?php unset($_SESSION['data']); ?>
    <?php else :?>
        <?php $_SESSION['message'] = ''; ?>
        <?php header("Location: index.php"); ?>
    <?php endif;?>
</div>

</body>
</html>