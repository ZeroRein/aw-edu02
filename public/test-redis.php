<?php
$redis = new Redis();
if (!$redis->connect('redis', 6379)) {
    die("connect NG\n");
}
echo $redis->ping(), PHP_EOL; // -> +PONG
$redis->set('hoge', 'fuga');
echo $redis->get('hoge'), PHP_EOL; // -> fuga
$redis->del('hoge');
$redis->close();

