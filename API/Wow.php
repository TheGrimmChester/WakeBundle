<?php
/**
 * Copyright (c) 2010-2017, AWHSPanel by Nicolas Méloni
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 *  Redistributions of source code must retain the above copyright notice, this
 *   list of conditions and the following disclaimer.
 *
 *  Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 *
 *  Neither the name of AWHSPanel nor the names of its contributors
 *   may be used to endorse or promote products derived from this software without
 *   specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace AWHS\WakeBundle\API;

/**
 * Class Wow
 * @package AWHS\WakeBundle\API
 * Modified source from https://github.com/sandrodz/wake-on-wan/blob/master/wow.class.php
 */
class Wow
{
    /**
     * @var \AWHS\WakeBundle\Entity\Computer $computer
     */
    private $computer;
    private $msg = array(
        0 => "Target machine seems to be Online.",
        1 => "socket_create function failed for some reason",
        2 => "socket_set_option function failed for some reason",
        3 => "magic packet sent successfully!",
        4 => "magic packet failed!"
    );

    public function setComputer($computer)
    {
        $this->computer = $computer;
    }

    public function wake_on_wan()
    {
        if (
            $this->computer->getHostname() == "255.0.0.0" ||
            $this->computer->getHostname() == "255.255.0.0" ||
            $this->computer->getHostname() == "255.255.255.0" ||
            $this->computer->getHostname() == "255.255.255.255" ||
            strtolower($this->computer->getMac()) == "ff:ff:ff:ff:ff:ff"
        ) {
            return $this->msg[4]; // magic packet failed!
        }

        if ($this->is_awake()) {
            return $this->msg[0]; // is awake, nothing to do
        } else {
            $addr_byte = explode(':', $this->computer->getMac());
            $hw_addr = '';
            for ($a = 0; $a < 6; $a++) $hw_addr .= chr(hexdec($addr_byte[$a]));
            $msg = chr(255) . chr(255) . chr(255) . chr(255) . chr(255) . chr(255);
            for ($a = 1; $a <= 16; $a++) $msg .= $hw_addr;
            // send it to the broadcast address using UDP
            $s = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

            if ($s == false) {
                return $this->msg[1]; // socket_create failed
            }
            $set_opt = @socket_set_option($s, 1, 6, TRUE);
            if ($set_opt < 0) {
                return $this->msg[2]; // socket_set_option failed
            }
            $sendto = @socket_sendto($s, $msg, strlen($msg), 0, gethostbyname($this->computer->getHostname()), $this->computer->getPort());

            if ($sendto) {
                socket_close($s);
                return $this->msg[3]; // magic packet sent successfully!
            }
            throw new \Exception($this->msg[4]); // magic packet failed!
        }
    }

    private function is_awake()
    {
        $awake = @fsockopen($this->ip, 80, $errno, $errstr, 2);
        if ($awake) {
            fclose($awake);
        }
        return $awake;
    }
}
