<?php

ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', false);

while (ob_get_level() > 0) {
    ob_end_flush();
}

set_time_limit(0);

for ($i = 0; $i < 10000000; $i++) {
    echo "$i<br>";
    sleep(1);
}
