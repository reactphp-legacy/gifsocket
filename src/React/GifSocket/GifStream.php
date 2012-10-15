<?php

namespace React\GifSocket;

use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;
use React\Stream\Util;

class GifStream extends EventEmitter implements ReadableStreamInterface
{
    private $encoder;
    private $closed = false;

    public function __construct(GifEncoder $encoder)
    {
        $this->encoder = $encoder;
    }

    public function addFrame($frame, $delay = 0)
    {
        $data = $this->encoder->addFrame($frame, $delay);
        $this->emit('data', [$data]);
    }

    public function isReadable()
    {
        return !$this->closed;
    }

    public function pause()
    {
    }

    public function resume()
    {
    }

    public function close()
    {
        $data = $this->finish();
        $this->emit('data', [$data]);

        $this->closed = true;
        $this->emit('close');
        $this->removeAllListeners();
    }

    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        Util::pipe($this, $dest, $options);

        return $dest;
    }
}
