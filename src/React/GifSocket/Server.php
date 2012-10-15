<?php

namespace React\GifSocket;

use React\Curry\Util as Curry;
use React\GifSocket\GifEncoder;
use React\GifSocket\GifStream;

class Server
{
    private $gifStreams;

    public function __construct()
    {
        $this->gifStreams = new \SplObjectStorage();
    }

    public function addFrame($frame)
    {
        foreach ($this->gifStreams as $gif) {
            $gif->addFrame($frame);
        }
    }

    public function __invoke($request, $response)
    {
        $response->writeHead(200, array('Content-Type' => 'image/gif'));

        $gif = $this->createGifStream();
        $gif->pipe($response);

        $this->gifStreams->attach($gif);
        $request->on('close',
            Curry::bind(array($this->gifStreams, 'detach'), $gif));
    }

    private function createGifStream()
    {
        $encoder = new GifEncoder();
        $gif = new GifStream($encoder);

        return $gif;
    }
}
