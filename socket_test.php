<?php
$e = 0;
$m = '';
$s = @stream_socket_server('tcp://127.0.0.1:9100', $e, $m);
var_dump((bool)$s, $e, $m);
