MiLight-php
==============

```php
$m = new \arandel\milight\MiLight("192.168.1.2");
$m->connect();
$m->switchOn($m::ZONE_ALL);
$m->setBrightness(0xFF);
$m->setColor($m::COLOR_AQUA, $m::ZONE_ALL);
```