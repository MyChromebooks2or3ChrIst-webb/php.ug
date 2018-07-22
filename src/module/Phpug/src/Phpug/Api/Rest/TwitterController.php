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
 * @since     02.07.14
 * @link      https://github.com/heiglandreas/
 */

namespace Phpug\Api\Rest;

use Doctrine\ORM\EntityManager;
use Phpug\Entity\Usergroup;
use Phpug\Service\TwitterInfoService;
use Zend\Mvc\Controller\AbstractRestfulController;

class TwitterController extends AbstractRestfulController
{
    protected $em = null;

    protected $twitter;

    public function __construct(EntityManager $em, TwitterInfoService $twitter)
    {
        $this->em = $em;
        $this->twitter = $twitter;
    }

    /**
     * Get a list of twitter-nicks ordered by groups
     *
     * @return mixed|void
     */
    public function getList()
    {
        $twitter = $this->em->getRepository('Phpug\Entity\Service')->findBy(array('name' => 'Twitter'));
        $twitters = $this->em->getRepository('Phpug\Entity\Groupcontact')->findBy(array('service' => $twitter[0]->id));

        $result = array();
        foreach ($twitters as $twitter) {
            try {
                $group = $twitter->getGroup();
                if (! $group) {
                    continue;
                }
                if (! $group instanceof Usergroup) {
                    continue;
                }
                if ($group->getState() != Usergroup::ACTIVE) {
                    continue;
                }
                if (! isset($result[$twitter->getName()])) {
                    $result[$twitter->getName()] = array(
                        'screen_name' => $twitter->getName(),
                        'name'        => $this->twitter->getInfoForUser(
                            'name',
                            $twitter->getName()
                        ),
                        'url'         => $twitter->getUrl(),
                        'icon_url'    => $this->twitter->getInfoForUser(
                            'profile_image_url_https',
                            $twitter->getName()
                        ),
                        'groups'      => array(),
                    );
                }
                $groupMapUrl = $this->url()->fromRoute(
                    'home',
                    array(),
                    array('force_canonical' => true)
                ) . '?center=' . $group->getShortName();

                $groupApiUrl = $this->url()->fromRoute(
                    'api/rest',
                    array(
                        'controller' => 'Usergroup',
                        'id'         => $group->getId(),
                    ),
                    array('force_canonical' => true)
                );
                $result[$twitter->getName()]['groups'][] = array(
                    'usergroup'           => $group->getName(),
                    'usergroup_url'       => $group->getUrl(),
                    'phpug_group_map_url' => $groupMapUrl,
                    'phpug_group_api_url' => $groupApiUrl,
                );
            } catch (\Exception $e) {
            }
        }

        usort($result, function ($a, $b) {
            return strnatcasecmp($a['name'], $b['name']);
        });

        $adapter = $this->getAdapter();
        $response = $this->getResponse();
        $response->setContent($adapter->serialize(array_values($result)));
        return $response;
    }

    protected function getAdapter()
    {
        $format = $this->getEvent()->getRouteMatch()->getParam('format');
        switch ($format) {
            case 'sphp':
                $contentType = 'text/plain';
                $adapter = '\Zend\Serializer\Adapter\PhpSerialize';
                break;
            case 'json':
            default:
                $contentType = 'application/json';
                $adapter = '\Zend\Serializer\Adapter\Json';
                break;
        }
        $this->getResponse()->getHeaders()->addHeaderLine('Content-Type', $contentType);

        return new $adapter;
    }
}
