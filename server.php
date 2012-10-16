<?php

require 'vendor/autoload.php';

function createGifFrame($string)
{
    $im = imagecreatetruecolor(200, 80);

    imagefilledrectangle($im, 0, 0, 99, 99, 0x000000);
    imagestring($im, 3, 40, 20, $string, 0xFFFFFF);

    ob_start();

    imagegif($im);
    imagedestroy($im);

    return ob_get_clean();
}

$loop = React\EventLoop\Factory::create();
$socket = new React\Socket\Server($loop);
$http = new React\Http\Server($socket);

$gifServer = new React\GifSocket\Server($loop);
$stdin = new React\Stream\Stream(STDIN, $loop);

$stdin->on('data', function ($data) use ($gifServer, $loop) {
    $frame = createGifFrame(trim($data));
    $gifServer->addFrame($frame);
});

$router = new React\GifSocket\Router([
    '/' => $gifServer,
]);

$http->on('request', $router);

$socket->listen(8080);
$loop->run();
