<?php

// based on GIFEncoder by László Zsidi, http://gifs.hu

namespace React\Gifsocket;

class GifEncoder
{
    private $initialFrame;

    private $loopCount;
    private $disposal;
    private $transparentColor = null;

    public function __construct($loopCount = -1, $disposal = 2, $red = null, $green = null, $blue = null)
    {
        $this->loopCount = $loopCount;
        $this->disposal = $disposal;
        $this->transparentColor = (is_int($red) && is_int($green) && is_int($blue)) ? $red | ($green << 8) | ($blue << 16) : null;
    }

    public function addFrame($frame, $delay = 0)
    {
        $data = '';

        if (null === $this->initialFrame) {
            $this->initialFrame = $frame;
            $data .= $this->writeHeader($frame);
        }

        $sourceHeader = substr($frame, 0, 6);
        if (!in_array($sourceHeader, array('GIF87a', 'GIF89a'))) {
            throw new \InvalidArgumentException('Input frame must be a GIF');
        }

        $i = 13 + 3 * (2 << (ord($frame[10]) & 0x07));
        while (true) {
            $sectionType = $frame[$i];

            if ('!' === $sectionType && 'NETSCAPE' === substr($frame, $i + 3, 8)) {
                throw new \InvalidArgumentException('Input frame must not be animated');
            }

            if (';' === $sectionType) {
                break;
            }

            $i++;
        }

        $data .= $this->encodeFrame($frame, $delay);

        return $data;
    }

    public function finish()
    {
        return $this->writeFooter();
    }

    private function writeHeader($initialFrame)
    {
        $data = 'GIF89a';

        if (ord($initialFrame[10]) & 0x80) {
            $cmap = 3 * (2 << (ord($initialFrame[10]) & 0x07));

            $data .= substr($initialFrame, 6, 7);
            $data .= substr($initialFrame, 13, $cmap);
            if ($this->loopCount >= 0) {
                $data .= "!\377\13NETSCAPE2.0\3\1".$this->gifWord($this->loopCount)."\0";
            }
        }

        return $data;
    }

    // black magic
    private function encodeFrame($frame, $delay)
    {
        $data = '';

        $localsStr = 13 + 3 * (2 << (ord($frame[10]) & 0x07));

        $localsEnd = strlen($frame) - $localsStr - 1;
        $localsTmp = substr($frame, $localsStr, $localsEnd);

        $globalLen = 2 << (ord($this->initialFrame[10]) & 0x07);
        $localsLen = 2 << (ord($frame[10]) & 0x07);

        $globalRgb = substr($this->initialFrame, 13,
                            3 * (2 << (ord($this->initialFrame[10]) & 0x07)));
        $localsRgb = substr($frame, 13,
                            3 * (2 << (ord($frame[10]) & 0x07)));

        $localsExt = "!\xF9\x04".chr(($this->disposal << 2) + 0).
                        chr(($delay >> 0) & 0xFF ).chr(($delay >> 8) & 0xFF)."\x0\x0";

        if ($this->transparentColor !== null && ord($frame[10]) & 0x80) {
            for ($j = 0; $j < (2 << (ord($frame[10]) & 0x07)); $j++) {
                if (
                    ord($localsRgb[3 * $j + 0]) == (($this->transparentColor >> 16) & 0xFF) &&
                    ord($localsRgb[3 * $j + 1]) == (($this->transparentColor >>  8) & 0xFF) &&
                    ord($localsRgb[3 * $j + 2]) == (($this->transparentColor >>  0) & 0xFF)
                ) {
                    $localsExt = "!\xF9\x04".chr(($this->disposal << 2 ) + 1).
                                    chr(($delay >> 0) & 0xFF).chr(($delay >> 8) & 0xFF).chr($j)."\x0";
                    break;
                }
            }
        }

        switch ($localsTmp[0]) {
            case "!":
                $localsImg = substr($localsTmp, 8, 10);
                $localsTmp = substr($localsTmp, 18, strlen($localsTmp) - 18);
                break;
            case ",":
                $localsImg = substr($localsTmp, 0, 10);
                $localsTmp = substr($localsTmp, 10, strlen($localsTmp) - 10);
                break;
        }

        if (ord($frame[10]) & 0x80 && null !== $this->initialFrame) {
            if ($globalLen == $localsLen) {
                if ($this->gifBlockCompare($globalRgb, $localsRgb, $globalLen)) {
                    $data .= $localsExt.$localsImg.$localsTmp;
                } else {
                    $byte  = ord($localsImg[9]);
                    $byte |= 0x80;
                    $byte &= 0xF8;
                    $byte |= ord($this->initialFrame[10]) & 0x07;
                    $localsImg[9] = chr($byte);
                    $data .= $localsExt.$localsImg.$localsRgb.$localsTmp;
                }
            } else {
                $byte  = ord($localsImg[9]);
                $byte |= 0x80;
                $byte &= 0xF8;
                $byte |= ord($frame[10]) & 0x07;
                $localsImg[9] = chr($byte);
                $data .= $localsExt.$localsImg.$localsRgb.$localsTmp;
            }
        } else {
            $data .= $localsExt.$localsImg.$localsTmp;
        }

        return $data;
    }

    private function writeFooter()
    {
        return ";";
    }

    private function gifBlockCompare($globalBlock, $localBlock, $length)
    {
        for ($i = 0; $i < $length; $i++) {
            if (
                $globalBlock[3 * $i + 0] != $localBlock[3 * $i + 0] ||
                $globalBlock[3 * $i + 1] != $localBlock[3 * $i + 1] ||
                $globalBlock[3 * $i + 2] != $localBlock[3 * $i + 2]
            ) {
                return false;
            }
        }

        return true;
    }

    private function gifWord($int)
    {
        return chr($int & 0xFF).chr(($int >> 8) & 0xFF);
    }
}
