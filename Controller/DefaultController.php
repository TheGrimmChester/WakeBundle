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

namespace AWHS\WakeBundle\Controller;

use AWHS\WakeBundle\Entity\Computer;
use AWHS\WakeBundle\Entity\Schedule;
use AWHS\WakeBundle\Entity\Stat;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class DefaultController extends Controller
{
    public function computersAction(Request $request)
    {
        // On récupère l'entité correspondant à l'id $id
        if (!$this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->getUser();

        $em = $this->getDoctrine()->getManager();

        $computers = $em->getRepository('AWHSWakeBundle:Computer')->findByUser($user);

        $computer = new Computer();
        $formBuilder = $this->createFormBuilder($computer);
        $formBuilder
            ->add('name', TextType::class, array(
                'required' => true,
            ))
            ->add('hostname', TextType::class, array(
                'required' => true,
            ))
            ->add('mac', TextType::class, array(
                'required' => true,
            ))
            ->add('port', TextType::class, array(
                'required' => true,
            ));

        $form = $formBuilder->getForm();

        if ($request->getMethod() == 'POST') {
            $form->handleRequest($request);

            if ($form->isValid()) {
                $computer = $form->getData();
                $mac = $computer->getMac();
                $mac = str_replace("-", ":", $mac);
                $computer->setMac($mac);
                $computer->setUser($user);
                $em->persist($computer);
                $em->flush();

                return $this->redirect($this->generateUrl('awhs_wake_computers', array()));
            }
        }

        return $this->render('AWHSWakeBundle:' . $this->container->getParameter('awhs')['template'] . '/computers.html.twig', array(
            'user' => $user,
            'computers' => $computers,
            'form' => $form->createView(),
        ));
    }

    public function wakeAction($computer_id)
    {
        // On récupère l'entité correspondant à l'id $id
        if (!$this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->getUser();

        $em = $this->getDoctrine()->getManager();

        /**
         * @var \AWHS\WakeBundle\Entity\Computer $computer
         */
        $computer = $em->createQuery("
				SELECT c
				FROM AWHSWakeBundle:Computer c 
				WHERE c.id=:computer_id AND c.user=:user_id")
            ->setParameter('computer_id', $computer_id)
            ->setParameter('user_id', $user->getId())
            ->getOneOrNullResult();

        if ($computer != null) {
            /**
             * @var \AWHS\WakeBundle\API\Wow $api
             */
            $api = $this->container->get("awhs_wake_api.wow");
            try {
                $api->setComputer($computer);
                $api->wake_on_wan();

                return new Response("done");
            } catch (\Exception $e) {
                return new Response("error" . $e->getMessage());
            }

        } else {
            return new Response("error");
        }
    }

    public function deleteScheduleAction($computer_id, $schedule_id)
    {
        // On récupère l'entité correspondant à l'id $id
        if (!$this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->getUser();

        $em = $this->getDoctrine()->getManager();

        /**
         * @var \AWHS\WakeBundle\Entity\Computer $computer
         */
        $computer = $em->createQuery("SELECT c FROM AWHSWakeBundle:Computer c WHERE c.id=:computer_id AND c.user=:user_id")
            ->setParameter('computer_id', $computer_id)
            ->setParameter('user_id', $user->getId())
            ->getOneOrNullResult();

        /**
         * @var \AWHS\WakeBundle\Entity\Schedule $schedule
         */
        $schedule = $em->createQuery("SELECT c FROM AWHSWakeBundle:Schedule c WHERE c.id=:schedule_id AND c.computer=:computer_id")
            ->setParameter('computer_id', $computer_id)
            ->setParameter('schedule_id', $schedule_id)
            ->getOneOrNullResult();

        if ($computer != null && $schedule != null) {

            $em->remove($schedule);
            $em->flush();

            return new Response("done");
        } else {
            return new Response("error");
        }
    }

    public function schedulesAction(Request $request, $computer_id, $computer_name)
    {
        // On récupère l'entité correspondant à l'id $id
        if (!$this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->getUser();

        $em = $this->getDoctrine()->getManager();

        /**
         * @var \AWHS\WakeBundle\Entity\Computer $computer
         */
        $computer = $em->createQuery("SELECT c FROM AWHSWakeBundle:Computer c WHERE c.id=:computer_id AND c.user=:user_id")
            ->setParameter('computer_id', $computer_id)
            ->setParameter('user_id', $user->getId())
            ->getOneOrNullResult();

        if ($computer != null) {
            /**
             * @var \AWHS\WakeBundle\Entity\Schedule $schedule
             */
            $schedule = new Schedule();
            $formBuilder = $this->createFormBuilder($schedule);
            $formBuilder
                ->add('month', ChoiceType::class, array(
                    'required' => true,
                    'choices' => array(
                        'All' => '*',
                        'January' => 1,
                        'February' => 2,
                        'March' => 3,
                        'April' => 4,
                        'May' => 5,
                        'June' => 6,
                        'July' => 7,
                        'August' => 8,
                        'September' => 9,
                        'October' => 10,
                        'November' => 11,
                        'December' => 12,
                    )
                ))
                ->add('day', ChoiceType::class, array(
                    'required' => true,
                    'choices' => array(
                        'All' => '*',
                        'Monday' => 1,
                        'Tuesday' => 2,
                        'Wednesday' => 3,
                        'Thursday' => 4,
                        'Friday' => 5,
                        'Saturday' => 6,
                        'Sunday' => 0,
                    )
                ))
                ->add('hour', ChoiceType::class, array(
                    'required' => true,
                    'choices' => array(
                        'All' => '*',
                        '1' => 1,
                        '2' => 2,
                        '3' => 3,
                        '4' => 4,
                        '5' => 5,
                        '6' => 6,
                        '7' => 7,
                        '8' => 8,
                        '9' => 9,
                        '10' => 10,
                        '11' => 11,
                        '12' => 12,
                        '13' => 13,
                        '14' => 14,
                        '15' => 15,
                        '16' => 16,
                        '17' => 17,
                        '18' => 18,
                        '19' => 19,
                        '20' => 20,
                        '21' => 21,
                        '22' => 22,
                        '23' => 23,
                        '24' => 0,
                    )
                ))
                ->add('minute', ChoiceType::class, array(
                    'required' => true,
                    'choices' => array(
                        'All' => '*',
                        '0' => 0,
                        '1' => 1,
                        '2' => 2,
                        '3' => 3,
                        '4' => 4,
                        '5' => 5,
                        '6' => 6,
                        '7' => 7,
                        '8' => 8,
                        '9' => 9,
                        '10' => 10,
                        '11' => 11,
                        '12' => 12,
                        '13' => 13,
                        '14' => 14,
                        '15' => 15,
                        '16' => 16,
                        '17' => 17,
                        '18' => 18,
                        '19' => 19,
                        '20' => 20,
                        '21' => 21,
                        '22' => 22,
                        '23' => 23,
                        '24' => 24,
                        '25' => 25,
                        '26' => 26,
                        '27' => 27,
                        '28' => 28,
                        '29' => 29,
                        '30' => 30,
                        '31' => 31,
                        '32' => 32,
                        '33' => 33,
                        '34' => 34,
                        '35' => 35,
                        '36' => 36,
                        '37' => 37,
                        '38' => 38,
                        '39' => 39,
                        '40' => 40,
                        '41' => 41,
                        '42' => 42,
                        '43' => 43,
                        '44' => 44,
                        '45' => 45,
                        '46' => 46,
                        '47' => 47,
                        '48' => 48,
                        '49' => 49,
                        '50' => 50,
                        '51' => 51,
                        '52' => 52,
                        '53' => 53,
                        '54' => 54,
                        '55' => 55,
                        '56' => 56,
                        '57' => 57,
                        '58' => 58,
                        '59' => 59,
                    )
                ));

            $form = $formBuilder->getForm();

            if ($request->getMethod() == 'POST') {
                //$form->bindRequest($request);
                $form->handleRequest($request);

                if ($form->isValid()) {
                    $schedule = $form->getData();
                    $schedule->setComputer($computer);
                    $em->persist($schedule);
                    $em->flush();

                    return $this->redirect($this->generateUrl('awhs_wake_computer_schedules', array(
                        'computer_id' => $computer_id,
                        'computer_name' => $computer_name,
                    )));
                }
            }
            return $this->render('AWHSWakeBundle:' . $this->container->getParameter('awhs')['template'] . '/schedules.html.twig', array(
                'user' => $user,
                'computer' => $computer,
                'form' => $form->createView(),
            ));
        } else {
            return new Response("error");
        }
    }

    public function deleteComputerAction($computer_id)
    {
        // On récupère l'entité correspondant à l'id $id
        if (!$this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->getUser();

        $em = $this->getDoctrine()->getManager();

        /**
         * @var \AWHS\WakeBundle\Entity\Computer $computer
         */
        $computer = $em->createQuery("SELECT c FROM AWHSWakeBundle:Computer c WHERE c.id=:computer_id AND c.user=:user_id")
            ->setParameter('computer_id', $computer_id)
            ->setParameter('user_id', $user->getId())
            ->getOneOrNullResult();

        if ($computer != null) {

            $em->remove($computer);
            $em->flush();

            return new Response("done");
        } else {
            return new Response("error");
        }
    }

    public function statsAction($computer_id)
    {
        // On récupère l'entité correspondant à l'id $id
        if (!$this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->getUser();

        $em = $this->getDoctrine()->getManager();

        /**
         * @var \AWHS\WakeBundle\Entity\Computer $computer
         */
        $computer = $em->createQuery("
				SELECT c
				FROM AWHSWakeBundle:Computer c 
				WHERE c.id=:computer_id AND c.user=:user_id")
            ->setParameter('computer_id', $computer_id)
            ->setParameter('user_id', $user->getId())
            ->getOneOrNullResult();

        if ($computer != null) {
            return $this->render('AWHSWakeBundle:' . $this->container->getParameter('awhs')['template'] . '/stats.html.twig', array(
                'user' => $user,
                'computer' => $computer,
            ));
        }
        $response = new Response("no");

        return $response;
    }

    public function statsJsonAction($computer_id)
    {
        // On récupère l'entité correspondant à l'id $id
        if (!$this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->getUser();

        $em = $this->getDoctrine()->getManager();

        /**
         * @var \AWHS\WakeBundle\Entity\Computer $computer
         */
        $computer = $em->createQuery("
				SELECT c
				FROM AWHSWakeBundle:Computer c 
				WHERE c.id=:computer_id AND c.user=:user_id")
            ->setParameter('computer_id', $computer_id)
            ->setParameter('user_id', $user->getId())
            ->getOneOrNullResult();

        if ($computer != null) {
            $stats = $em->createQuery("
				SELECT s
				FROM AWHSWakeBundle:Stat s 
				WHERE s.computer=:computer_id
				ORDER BY s.timestamp DESC")
                ->setParameter('computer_id', $computer_id)
                ->setMaxResults(1)
                ->getResult();

            $response = new  Response($stats[0]->getStats());
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }
        $response = new Response("no");

        return $response;
    }

    public function statsUpdateAction($userKey, $computer_id)
    {
        $em = $this->getDoctrine()->getManager();

        $user = $em->createQuery("
			SELECT u
			FROM AWHSUserBundle:User u 
			WHERE u.userKey='$userKey'")->getOneOrNullResult();
        if ($user != null) {

            $computer = $em->createQuery("
				SELECT c
				FROM AWHSWakeBundle:Computer c 
				WHERE c.id=:computer_id AND c.user=:user_id")
                ->setParameter('computer_id', $computer_id)
                ->setParameter('user_id', $user->getId())
                ->getOneOrNullResult();

            if ($computer != null) {
                $stats = new Stat();
                $stats->setComputer($computer);
                $stats->setStats($_POST["datas"]);
                $stats->setTimestamp(new \DateTime());
                $em->persist($stats);
                $em->flush();

                return new Response("ok");
            }
        }
        return new Response("no");
    }
}
