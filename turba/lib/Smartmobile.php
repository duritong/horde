<?php
/**
 * Base class for smartmobile view pages.
 *
 * Copyright 2012 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/apache.
 *
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/apache ASL
 * @package  Turba
 */
class Turba_Smartmobile
{
    /**
     * @var Horde_Variables
     */
    public $vars;

    /**
     * @var Horde_View
     */
    public $view;

    /**
     */
    public function __construct(Horde_Variables $vars)
    {
        global $notification, $page_output;

        $this->vars = $vars;

        $this->view = new Horde_View(array(
            'templatePath' => TURBA_TEMPLATES . '/smartmobile'
        ));
        $this->view->addHelper('Text');

        $this->_initPages();

        $page_output->addScriptFile('smartmobile.js');

        $notification->notify(array('listeners' => 'status'));
    }

    /**
     */
    public function render()
    {
        echo $this->view->render('browse');
        echo $this->view->render('entry');
    }

    /**
     */
    protected function _initPages()
    {
        global $injector, $registry;

        $this->view->portal = $registry->getServiceLink('portal', 'horde')->setRaw(false);
        $this->view->logout = $registry->getServiceLink('logout')->setRaw(false);

        $this->view->list = array();
        if ($GLOBALS['browse_source_count']) {
            foreach (Turba::getAddressBooks() as $key => $val) {
                if (!empty($val['browse'])) {
                    try {
                        $driver = $injector->getInstance('Turba_Factory_Driver')->create($key);
                    } catch (Turba_Exception $e) {
                        continue;
                    }

                    $contacts = $driver->search(array(), null, 'AND', array('__key', 'name'));
                    $contacts->reset();

                    $url = new Horde_Url();
                    $url->add('source', $key);
                    $tmp = array();

                    while ($contact = $contacts->next()) {
                        $tmp[] = array(
                            'name' => Turba::formatName($contact),
                            'url' => strval($url->add('key', $contact->getValue('__key')))
                        );
                    }

                    $this->view->list[$val['title']] = $tmp;
                }
            }
        }
    }

}
