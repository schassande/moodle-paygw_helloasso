<?php
require_once(__DIR__ . '/../../../../config.php');
require_login();
echo $OUTPUT->header();
echo $OUTPUT->notification(get_string('payment_cancelled', 'payment_gateway_helloasso'), 'notifyproblem');
echo $OUTPUT->footer();
