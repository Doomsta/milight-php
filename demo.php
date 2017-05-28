<?php
require_once __DIR__ . '/vendor/autoload.php';

use arandel\milight\MiLight;

demo("192.168.20.15", MiLight::ZONE_ALL);

function demo($ip, $zone) {
    $m = new MiLight($ip);
    $m->connect();
    $m->switchOn($zone);
    foreach ([
                 $m::COLOR_RED,
                 $m::COLOR_YELLOW,
                 $m::COLOR_GREEN,
                 $m::COLOR_AQUA,
                 $m::COLOR_ORANGE,
             ] as $color) {
        $m->setColor($color, $zone);
        sleep(1);
    }
    $m->switchOff($zone);
}
