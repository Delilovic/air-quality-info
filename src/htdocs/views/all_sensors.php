<?php if ($homeWidget['level'] !== null && isset($this->user['sensor_widget']) && $this->user['all_widget']): ?>
<?php include('views/widget/homewidget.php') ?>
<?php endif ?>

<?php include('partials/sensors/avg-switch.php');
foreach($data as $r):
extract($r);
?>
<div class="row device-header">
    <div class="col-md-3 offset-md-2">
        <?php include('partials/device_description.php'); ?>
    </div>
    <div class="col-md-auto text-center">
        <?php include('partials/sensors/badge.php') ?>
    </div>
</div>

<div class="row">
    <div class="col-md-8 offset-md-2 text-center">
        <?php
        include('partials/sensors/table.php');
        ?>
    </div>
</div>
<?php endforeach ?>