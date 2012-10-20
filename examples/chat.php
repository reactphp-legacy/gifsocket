<?php

require __DIR__.'/../vendor/autoload.php';

function createGifFrame(array $messages)
{
    $im = imagecreatetruecolor(500, 400);

    imagefilledrectangle($im, 0, 0, 500, 400, 0x000000);
    foreach ($messages as $i => $message) {
        imagestring($im, 3, 40, 20 + $i*20, $message, 0xFFFFFF);
    }

    ob_start();

    imagegif($im);
    imagedestroy($im);

    return ob_get_clean();
}

function sendEmptyFrameAfter($gifServer)
{
    return function ($request, $response) use ($gifServer) {
        $gifServer($request, $response);
        $gifServer->addFrame(createGifFrame(['']));
    };
}

$loop = React\EventLoop\Factory::create();
$socket = new React\Socket\Server($loop);
$http = new React\Http\Server($socket);

$gifServer = new React\GifSocket\Server($loop);

$messages = array();
$addMessage = function ($message) use ($gifServer, &$messages) {
    $messages[] = $message;
    if (count($messages) > 18) {
        $messages = array_slice($messages, count($messages) - 18);
    }

    $frame = createGifFrame($messages);
    $gifServer->addFrame($frame);
};

$router = new React\GifSocket\Router([
    '/socket.gif' => sendEmptyFrameAfter($gifServer),
    '/' => function ($request, $response) use ($loop) {
        $response->writeHead(200, ['Content-Type' => 'text/html']);

        $fd = fopen(__DIR__.'/views/index.html', 'r');
        $template = new React\Stream\Stream($fd, $loop);
        $template->pipe($response);
    },
    '/message' => function ($request, $response) use ($addMessage) {
        $message = $request->getQuery()['message'];
        $addMessage($message);

        $response->writeHead(200);
        $response->end();
    },
]);

$http->on('request', $router);

$socket->listen(8080);
$loop->run();
