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
    '/' => function ($request, $response) {
        $response->writeHead(200, ['Content-Type' => 'text/html']);
        $response->end(file_get_contents(__DIR__.'/views/index.html'));
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
