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

namespace AWHS\WakeBundle\Command;


use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class WakeCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('computers:wake')
            ->setDescription('Cron to wake computers');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();

        /**
         * @var \DateTime $datetime
         */
        $datetime = new \Datetime();

        $computers = $em->getRepository('AWHSWakeBundle:Computer')->findAll();

        /**
         * @var \AWHS\WakeBundle\Entity\Computer $computer
         */
        foreach ($computers as $computer) {
            $canBeWake = false;
            if (
                $computer->getHostname() == "255.0.0.0" ||
                $computer->getHostname() == "255.255.0.0" ||
                $computer->getHostname() == "255.255.255.0" ||
                $computer->getHostname() == "255.255.255.255" ||
                strtolower($computer->getMac()) == "ff:ff:ff:ff:ff:ff"
            ) {
                continue;
            }

            /**
             * @var \AWHS\WakeBundle\Entity\Schedule $schedule
             */
            foreach ($computer->getSchedules() as $schedule) {

                if ($schedule->getMonth() == '*' &&
                    $schedule->getDay() == '*' &&
                    $schedule->getHour() == '*' &&
                    $schedule->getMinute() == '*'
                ) {
                    $canBeWake = true;
                    break;
                }

                if (
                    ($schedule->getMonth() == '*' || $schedule->getMonth() == (int)$datetime->format('m')) &&
                    ($schedule->getDay() == '*' || $schedule->getDay() == (int)$datetime->format('w')) &&
                    ($schedule->getHour() == '*' || $schedule->getHour() == (int)$datetime->format('H')) &&
                    ($schedule->getMinute() == '*' || $schedule->getMinute() == (int)$datetime->format('i'))
                ) {
                    $canBeWake = true;
                    break;
                }

                echo "{$schedule->getMonth()} - " . (int)$datetime->format('m') . "\n";
                echo "{$schedule->getDay()} - {$datetime->format('w')}\n";
                echo "{$schedule->getHour()} - " . (int)$datetime->format('H') . "\n";
                echo "{$schedule->getMinute()} - {$datetime->format('i')}\n";
            }

            if ($canBeWake) {
                try {
                    /**
                     * @var \AWHS\WakeBundle\API\Wow $api
                     */
                    $api = $this->getContainer()->get("awhs_wake_api.wow");
                    $api->setComputer($computer);
                    $api->wake_on_wan();
                } catch (\Exception $ex) {

                }
            }
        }
    }
}
