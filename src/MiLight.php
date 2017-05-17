<?php
namespace arandel\milight;

class MiLight
{
    const COMMAND_MODE = 'mode';
    /** @var string */
    private $ip;
    /** @var int */
    private $port;
    /** @var resource */
    private $socket;
    /** @var string */
    private $session;

    const FILLER = 0x0;
    const PREAMPLE = [0x80,0x00,0x00,0x00,0x11];

    const BUFFER_LEN = 22;
    const SOCKET_TIMEOUT = 4;

    const COMMAND_LINK = 'link';
    const COMMAND_UNLINK = 'unlink';
    const COMMAND_ON = 'on';
    const COMMAND_OFF = 'off';
    const COMMAND_BRIGHTNESS = 'brightness';
    const COMMAND_CONNECT = 'connect';

    const ZONE_1 = 1;
    const ZONE_2 = 2;
    const ZONE_3 = 3;
    const ZONE_4 = 4;
    const ZONE_ALL = 0;

    const BRIGHTNESS_MIN = 0x00;
    const BRIGHTNESS_MAX = 0xFF;

    const COLOR_VIOLET = 0x00; #EE82EE
    const COLOR_ROYALBLUE = 0x10; #4169E1
    const COLOR_LIGHTSKYBLUE = 0x20; #87CEFA
    const COLOR_AQUA = 0x30; #00FFFF
    const COLOR_AQUAMARINE = 0x40; #7FFFD4
    const COLOR_SEAGREEN = 0x50; #2E8B57
    const COLOR_GREEN = 0x60; #008000
    const COLOR_LIMEGREEN = 0x70; #32CD32
    const COLOR_YELLOW = 0x80; #FFFF00
    const COLOR_GOLDENROD = 0x90; #DAA520
    const COLOR_ORANGE = 0xA0; #FFA500
    const COLOR_RED = 0xB0; #FF0000
    const COLOR_PINK = 0xC0; #FFC0CB
    const COLOR_FUCHSIA = 0xD0; #FF00FF
    const COLOR_ORCHID = 0xE0; #DA70D6
    const COLOR_LAVENDER = 0xF0; #E6E6FA
    const COLOR_WHITE = '#FFFFFF';

    private $colors = [
        self::COLOR_WHITE => [0x07, 0x03, 0x05, 0x00, 0x00, 0x00],
        self::COLOR_ROYALBLUE => [0x07, 0x01, 0xBA, 0xBA, 0xBA, 0xBA],
        self::COLOR_AQUA => [0x07, 0x01, 0x85, 0x85, 0x85, 0x85],
        self::COLOR_RED => [0x07, 0x01, 0xFF, 0xFF, 0xFF, 0xFF],
        self::COLOR_LAVENDER => [0x07, 0x01, 0xD9, 0xD9, 0xD9, 0xD9],
        self::COLOR_GREEN => [0x07, 0x01, 0x7A, 0x7A, 0x7A, 0x7A],
        self::COLOR_LIMEGREEN => [0x07, 0x01, 0x54, 0x54, 0x54, 0x54],
        self::COLOR_ORANGE => [0x07, 0x01, 0x1E, 0x1E, 0x1E, 0x1E],
        self::COLOR_YELLOW => [0x07, 0x01, 0x3B, 0x3B, 0x3B, 0x3B],
    ];


    private $commands = [
        self::COMMAND_LINK => [0x07, 0x00, 0x00, 0x00, 0x00, 0x00],
        self::COMMAND_UNLINK => [0x07, 0x00, 0x00, 0x00, 0x00, 0x00],
        self::COMMAND_ON => [0x07, 0x03, 0x01, 0x00, 0x00, 0x00],
        self::COMMAND_OFF => [0x07, 0x03, 0x02, 0x00, 0x00, 0x00],
        self::COMMAND_BRIGHTNESS => [0x07, 0x02, 0x64, 0x00, 0x00, 0x00],
        self::COMMAND_CONNECT => [
            0x20, 0x00, 0x00, 0x00, 0x16, 0x02,
            0x62, 0x3A, 0xD5, 0xED, 0xA3, 0x01,
            0xAE, 0x08, 0x2D, 0x46, 0x61, 0x41,
            0xA7, 0xF6, 0xDC, 0xAF, 0xD3, 0xE6,
            0x00, 0x00, 0x1E,
        ],
        self::COMMAND_MODE => [ 0x00,0x04,0xfF,0x0,0x0,0x0],
    ];

    /**
     * MiLight constructor.
     * @param $ip
     * @param $port
     */
    public function __construct($ip, $port = 5987)
    {
        $this->ip = $ip;
        $this->port = $port;
    }

    public function connect()
    {
        # return;
        $this->socket = \socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (!is_resource($this->socket)) {
            throw new \Exception('unable to connect');
        }
        # socket_set_timeout($this->socket, self::SOCKET_TIMEOUT);
        $this->session = $this->getWifiBridgeSession();
        if (!$this->session) {
            throw new \Exception('unable to connect');
        }
        return true;
    }

    public function isConnected()
    {
        return $this->socket !== false && !empty ($this->session);
    }

    public function disconnect()
    {
        socket_close($this->socket);
        $this->socket = null;
        $this->session = '';
    }

    public function switchOn($zone = self::ZONE_ALL)
    {
        $zone = $this->validateZone($zone);
        return $this->sendCommand(self::COMMAND_ON, $zone);
    }

    public function switchOff($zone = self::ZONE_ALL)
    {
        $zone = $this->validateZone($zone);
        $this->sendCommand(self::COMMAND_OFF, $zone);
    }

    public function setColor($color, $zone = self::ZONE_ALL)
    {
        $zone = $this->validateZone($zone);
        if (!isset($this->colors[$color])) {
            throw new \Exception('unknown color');
        }
        $this->send($this->buildBytes($this->colors[$color], $zone));
    }

    public function setMode($mode, $zone = self::ZONE_ALL)
    {
        $zone = $this->validateZone($zone);
        $bytes = $this->commands[self::COMMAND_MODE];
        $bytes[2] = $mode;
        $bytes = $this->buildBytes($bytes, $zone);
        $this->send($bytes);
    }

    public function setBrightness($intensity = self::BRIGHTNESS_MAX, $zone = self::ZONE_ALL)
    {
        $zone = $this->validateZone($zone);
        $intensity = $this->validateIntensity($intensity);
        $bytes = $this->commands[self::COMMAND_BRIGHTNESS];
        $bytes[2] = $intensity;
        $bytes = $this->buildBytes($bytes, $zone);
        $this->send($bytes);
    }

    public function link($zone = self::ZONE_1)
    {
        $zone = $this->validateZone($zone);
        $this->sendCommand(self::COMMAND_LINK, $zone, 0x3D);
    }

    public function unlink($zone)
    {
        $zone = $this->validateZone($zone);
        $this->sendCommand(self::COMMAND_UNLINK, $zone, 0x3E);
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    private function validateZone($zone)
    {
        if ($zone < 0 || $zone > 4) {
            throw new \InvalidArgumentException('invalid range for zone');
        }
        return $zone;
    }

    private function validateIntensity($intensity)
    {
        if ($intensity < self::BRIGHTNESS_MIN || $intensity > self::BRIGHTNESS_MAX) {
            throw new \InvalidArgumentException('invalid range for intensity');
        }
        return $intensity;
    }


    private function sendCommand($action, $zone, $scope = 0x31)
    {
        if (!isset($this->commands[$action])) {
            throw new \Exception('invalid command!');
        }
        $bytes = $this->buildBytes(
            $this->commands[$action],
            $zone,
            $scope
        );
        return $this->send($bytes);
    }

    /** TODO retries and delay */
    private function send($bytes)
    {
        $msg = vsprintf(str_repeat('%c', count($bytes)), $bytes);
        $buffer = null;

        if (socket_sendto($this->socket, $msg, strlen($msg), 0, $this->ip, $this->port) !== false) {
            $ret = socket_recvfrom($this->socket, $buffer, self::BUFFER_LEN, 0, $this->ip, $this->port);
            if ($ret === false) {
                throw new \Exception(socket_strerror(socket_last_error()));
            }
        }
        return $buffer;
    }

    private function buildBytes($bytes, $zone, $scope = 0x31)
    {
        $command = self::PREAMPLE;
        $command[] = unpack('c', $this->session[19])[1];
        $command[] = unpack('c', $this->session[20])[1];
        $command[] = self::FILLER;
        $command[] = self::FILLER;
        $command[] = 0x01;
        $command[] = $scope;
        $command[] = self::FILLER;
        $command[] = self::FILLER;
        if (!empty ($bytes)) {
            foreach ($bytes as $byte) {
                $command[] = $byte;
            }
        }
        $command[] = $zone;
        $command[] = self::FILLER;
        $command[] = $this->getChecksum($bytes, $zone, $scope);

        return $command;
    }


    private function getChecksum($command, $zone, $scope = 0x31)
    {
        $checksum = $scope + 0x00 + 0x00;

        if (!empty ($command)) {
            foreach ($command as $byte) {
                $checksum += $byte;
            }
        }
        $checksum += $zone + 0x00;

        return $checksum;
    }

    private function getWifiBridgeSession()
    {
        return $this->send($this->commands[self::COMMAND_CONNECT]);
    }
}
