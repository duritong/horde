<?php
/**
 * Components_Runner_Change:: adds a new change log entry.
 *
 * PHP version 5
 *
 * @category Horde
 * @package  Components
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.fsf.org/copyleft/lgpl.html LGPL
 * @link     http://pear.horde.org/index.php?package=Components
 */

/**
 * Components_Runner_Change:: adds a new change log entry.
 *
 * Copyright 2011 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @category Horde
 * @package  Components
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.fsf.org/copyleft/lgpl.html LGPL
 * @link     http://pear.horde.org/index.php?package=Components
 */
class Components_Runner_Change
{
    /**
     * The configuration for the current job.
     *
     * @var Components_Config
     */
    private $_config;

    /**
     * Change log helper.
     *
     * @var Components_Helper_ChangeLog
     */
    private $_helper;

    /**
     * Constructor.
     *
     * @param Components_Config           $config  The configuration for the current
     *                                             job.
     * @param Components_Helper_ChangeLog $helper  Change log helper
     */
    public function __construct(
        Components_Config $config,
        Components_Helper_ChangeLog $helper
    ) {
        $this->_config = $config;
        $this->_helper = $helper;
    }

    public function run()
    {
        $options = $this->_config->getOptions();
        $arguments = $this->_config->getArguments();

        if (count($arguments) > 1 && $arguments[0] == 'changed') {
            $log = $arguments[1];
        } else {
            throw new Components_Exception('Please provide a change log entry as additional argument!');
        }

        $this->_config->getComponent()->changed(
            $log, $this->_helper, $options
        );
    }
}
