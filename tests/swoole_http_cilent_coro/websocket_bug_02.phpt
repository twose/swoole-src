--TEST--
websocket_bug_02: websocket bug use client in server
--SKIPIF--
<?php require __DIR__ . '/../include/skipif.inc'; ?>
--FILE--
<?php
require_once __DIR__ . '/../include/bootstrap.php';
$ws = new swoole_websocket_server('127.0.0.1', 9501);
$ws->set(['worker_num' => 1]);
$ws->on('workerStart', function (swoole_websocket_server $serv) {
    $cli = new Swoole\Coroutine\Http\Client("127.0.0.1", 9501);
    $cli->set(['timeout' => -1]);
    $ret = $cli->upgrade('/');
    assert($ret);
    echo $cli->recv()->data;
    for ($i = 0; $i < 5; $i++) {
        $cli->push("hello server\n");
        echo ($cli->recv(1))->data;
        co::sleep(0.1);
    }
    $cli->close();
    $serv->shutdown();
});
$ws->on('open', function (swoole_websocket_server $ws, swoole_http_request $request) {
    $ws->push($request->fd, "server: hello, welcome\n");
});
$ws->on('message', function (swoole_websocket_server $ws, swoole_websocket_frame $frame) {
    echo "client: {$frame->data}";
    $frame->data = str_replace('server', 'client', $frame->data);
    $ws->push($frame->fd, "server-reply: {$frame->data}");
});
$ws->on('close', function (swoole_websocket_server $ws, int $fd) {
    echo "client-{$fd} is closed\n";
});
$ws->start();
?>
--EXPECT--
server: hello, welcome
client: hello server
server-reply: hello client
client: hello server
server-reply: hello client
client: hello server
server-reply: hello client
client: hello server
server-reply: hello client
client: hello server
server-reply: hello client
client-1 is closed