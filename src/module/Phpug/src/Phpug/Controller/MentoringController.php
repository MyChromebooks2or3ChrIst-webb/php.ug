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
 * @since     13.05.14
 * @link      https://github.com/heiglandreas/
 */

namespace Phpug\Controller;

use Phpug\Parser\Mentoring;
use Zend\Json\Json;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;

class MentoringController extends AbstractActionController
{

    /**
     * Parse the phpmentoring.org-page for apprentices and mentors
     *
     * This method is the endpoint for the console-action.
     *
     * @return void
     */
    public function getmentoringAction()
    {
        echo sprintf('Generating mentoring.json-File' . "\n");
        $config = $this->getServiceLocator()->get('config');

        $mentoring = new Mentoring($config['php.ug.mentoring']);

        $infos = $mentoring->parse(
            'https://github.com/phpmentoring/phpmentoring.github.com/wiki/Mentors-and-Apprentices'
        );

        $file = $config['php.ug.mentoring']['file'];

        $fh = fopen($file, 'w+');
        fwrite($fh, json_encode($infos));
        fclose($fh);

        echo sprintf('File "%s" has been stored' . "\n", $file);
    }

    /**
     * Get a list of apprentices and mentors
     *
     * @return JsonModel
     */
    public function getlistAction()
    {
        $config = $this->getServiceLocator()->get('config');
        $file = $config['php.ug.mentoring']['file'];
        $content = Json::decode(file_get_contents($file), Json::TYPE_ARRAY);

        return new JsonModel($content);
    }
}
