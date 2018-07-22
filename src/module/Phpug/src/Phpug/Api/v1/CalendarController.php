<?php
/**
 * Copyright (c)2014-2014 heiglandreas
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIBILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @category
 * @author    Andreas Heigl<andreas@heigl.org>
 * @copyright ©2014-2014 Andreas Heigl
 * @license   http://www.opesource.org/licenses/mit-license.php MIT-License
 * @version   0.0
 * @since     04.02.14
 * @link      https://github.com/heiglandreas/
 */

namespace Phpug\Api\v1;

use Doctrine\ORM\EntityManager;
use Phpug\ORM\Query\AST\Functions\DistanceFrom;
use Zend\Mvc\Controller\AbstractActionController;
use Sabre\VObject;
use Zend\View\Model\ViewModel;

class CalendarController extends AbstractActionController
{
    private $acceptCriteria = array(
        'Phpug\View\Model\IcalendarJsonModel' => array(
            'application/json',
        ),
        'Zend\View\Model\FeedModel' => array(
            'application/rss+xml',
        ),
        'Phpug\View\Model\IcalendarModel' => array(
            'text/calendar',
        ),
        'Zend\View\Model\ViewModel' => array(
            'text/html',
        )
    );

    private $acceptParameters = array(
        'html' => 'text/html',
        'rss'  => 'application/rss+xml',
        'ical' => 'text/calendar',
        'json' => 'application/json',
    );

    /** @var \Doctrine\ORM\EntityManager $em */
    protected $em;

    public function __construct(EntityManager $entityManager)
    {
        $this->em = $entityManager;
    }

    public function listAction()
    {
        $result = $this->em->getRepository('Phpug\Entity\Cache')->findBy(array('type' => 'event'));
        $calendar = new VObject\Component\VCalendar();
        $affectedUGs = $this->findGroupsWithinRangeAndDistance();
        $now = new \DateTimeImmutable();
        $then = $now->add(new \DateInterval('P1Y'));
        foreach ($result as $cal) {
            if (! $cal->getGroup()) {
                continue;
            }

            if ($affectedUGs && ! in_array($cal->getGroup()->getShortname(), $affectedUGs)) {
                continue;
            }

            try {
                $ical = VObject\Reader::read($cal->getCache());
                $ical = $ical->expand($now, $then);
                foreach ($ical->children() as $event) {
                    if (!$event instanceof VObject\Component\VEvent) {
                        continue;
                    }

                    if (! $event->isInTimeRange($now, $then)) {
                        continue;
                    }

                    $result = $event->validate(VObject\Node::REPAIR);
                    if ($result) {
                        error_log(print_r($result, true));
                        continue;
                    }
                    $event->SUMMARY = '[' . $cal->getGroup()->getName() . '] ' . $event->SUMMARY;

                    $calendar->add($event);
                }
            } catch (\Exception $e) {
            }
        }
        
        $viewModel = $this->getViewModel();
        if ($viewModel instanceof ViewModel) {
            $viewModel->setVariable('location', $this->params()->fromQuery('latitude', 0)
                                                . ' '
                                                . $this->params()->fromQuery('longitude', 0));
            $viewModel->setVariable('latitude', $this->params()->fromQuery('latitude', 0));
            $viewModel->setVariable('longitude', $this->params()->fromQuery('longitude', 0));
            $viewModel->setVariable('distance', $this->params()->fromQuery('distance', 100));
        }

        return $viewModel->setVariable('calendar', new \Phpug\Wrapper\SabreVCalendarWrapper($calendar));
    }

    protected function getViewModel()
    {
        $this->setAcceptHeaderAccordingToParameters();
        return $this->acceptableViewModelSelector($this->acceptCriteria);
    }

    protected function setAcceptHeaderAccordingToParameters()
    {
        $accept = $this->params()->fromQuery('format');
        if (! $accept || $accept == 'null') {
            return $this;
        }

        if (! isset($this->acceptParameters[$accept])) {
            return $this;
        }

        /** @var \Zend\Http\Headers $headers */
        $headers = $this->request->getHeaders();
        if ($headers->has('Accept')) {
            $headers->removeHeader($headers->get('Accept'));
        }
        $headers->addHeaderLine('Accept', $this->acceptParameters[$accept]);

        return $this;
    }

    protected function findGroupsWithinRangeAndDistance()
    {
        $lat      = $this->params()->fromQuery('latitude', null);
        $lon      = $this->params()->fromQuery('longitude', null);
        $distance = $this->params()->fromQuery('distance', null);
        $number   = $this->params()->fromQuery('count', null);

        if (! $lat || ! $lon) {
            return array();
        }
        DistanceFrom::setLatitudeField('latitude');
        DistanceFrom::setLongitudeField('longitude');
        DistanceFrom::setRadius(6367);
        $this->em->getConfiguration()->addCustomNumericFunction(
            'DISTANCEFROM',
            'Phpug\ORM\Query\AST\Functions\DistanceFrom'
        );

        $qs = 'SELECT p, DISTANCEFROM('
            . (float) $lat
            . ','
            . (float) $lon
            . ') AS distance FROM \Phpug\Entity\Usergroup p WHERE p.state = 1 ';


        if ($distance) {
            $qs .= ' AND DISTANCEFROM(' . (float) $lat . ',' . (float) $lon . ') <= ' . (float) $distance;
        }

        $qs .= ' ORDER BY distance';

        $query = $this->em->createQuery($qs);
        if ($number) {
            $query->setMaxResults($number);
        }

        $res = array();
        foreach ($query->getResult() as $result) {
            $res[] = $result[0]->getShortname();
        }

        return $res;
    }
}
