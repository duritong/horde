<?php
/**
 * Components_Helper_ChangeLog:: helps with adding entries to the change log(s).
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
 * Components_Helper_ChangeLog:: helps with adding entries to the change log(s).
 *
 * Copyright 2010-2011 The Horde Project (http://www.horde.org/)
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
class Components_Helper_ChangeLog
{
    /** Path to the CHANGES file. */
    const CHANGES = '/docs/CHANGES';

    /**
     * The output handler.
     *
     * @param Component_Output
     */
    private $_output;

    /**
     * Constructor.
     *
     * @param Component_Output  $output  The output handler.
     */
    public function __construct(Components_Output $output)
    {
        $this->_output = $output;
    }

    /**
     * Update CHANGES file.
     *
     * @param string                 $log     The log entry.
     * @param Horde_Pear_Package_Xml $xml     The package xml handler.
     * @param string                 $file    Path to the package.xml.
     * @param array                  $options Additional options.
     *
     * @return NULL
     */
    public function packageXml($log, $xml, $file, $options)
    {
        if (file_exists($file)) {
            if (empty($options['pretend'])) {
                $xml->addNote($log);
                file_put_contents($file, (string) $xml);
                $this->_output->ok(
                    'Added new note to ' . $file . '.'
                );
                return $file;
            } else {
                $this->_output->info(
                    sprintf(
                        'Would add change log entry to %s now.',
                        $file
                    )
                );
            }
        }
    }

    /**
     * Update CHANGES file.
     *
     * @param string $log         The log entry.
     * @param string $directory   The path to the component directory.
     * @param array  $options     Additional options.
     *
     * @return NULL
     */
    public function changes($log, $directory, $options)
    {
        $changes = false;
        if ($changes = $this->changesFileExists($directory)) {
            if (empty($options['pretend'])) {
                $this->addChange($log, $changes);
                $this->_output->ok(
                    sprintf(
                        'Added new note to %s.',
                        $changes
                    )
                );
                return $changes;
            } else {
                $this->_output->info(
                    sprintf(
                        'Would add change log entry to %s now.',
                        $changes
                    )
                );
            }
        }
    }

    /**
     * Commit
     *
     * @param string $log         The log entry.
     * @param string $directory   The path to the component directory.
     * @param array  $options     Additional options.
     * @param string $package_xml Path to the modified package.xml
     * @param string $changes     Path to the modified CHANGES file.
     *
     * @return NULL
     */
    public function commit(
        $log, $directory, $options, $package_xml = null, $changes = null
    )
    {
        if (!empty($options['commit'])) {
            if (!empty($package_xml)) {
                $this->systemInDirectory(
                    'git add ' . $package_xml, $directory, $options
                );
            }
            if (!empty($changes)) {
                $this->systemInDirectory(
                    'git add ' . $changes, $directory, $options
                );
            }
            $this->systemInDirectory(
                'git commit -m "' . $log . '"',
                $directory,
                $options
            );
        }
    }

    /**
     * Run a system call.
     *
     * @param string $call       The system call to execute.
     * @param string $target_dir Run the command in the provided target path.
     * @param array  $options    Additional options.
     *
     * @return string The command output.
     */
    protected function systemInDirectory($call, $target_dir, $options)
    {
        $old_dir = getcwd();
        chdir($target_dir);
        $result = $this->system($call, $options);
        chdir($old_dir);
        return $result;
    }

    /**
     * Run a system call.
     *
     * @param string $call    The system call to execute.
     * @param array  $options Additional options.
     *
     * @return string The command output.
     */
    protected function system($call, $options)
    {
        if (empty($options['pretend'])) {
            //@todo Error handling
            return system($call);
        } else {
            $this->_output->info(sprintf('Would run "%s" now.', $call));
        }
    }

    /**
     * Indicates if there is a CHANGES file for this component.
     *
     * @param string $dir The basic component directory.
     *
     * @return string|boolean The path to the CHANGES file if it exists, false
     *                        otherwise.
     */
    public function changesFileExists($dir)
    {
        $changes = $dir . self::CHANGES;
        if (file_exists($changes)) {
            return $changes;
        }
        return false;
    }

    /**
     * Add a change log entry to CHANGES
     *
     * @param string $entry   Change log entry to add.
     * @param string $changes Path to the CHANGES file.
     *
     * @return NULL
     */
    public function addChange($entry, $changes)
    {
        $tmp = Horde_Util::getTempFile();
        $entry = Horde_String::wrap($entry, 79, "\n      ");

        $oldfp = fopen($changes, 'r');
        $newfp = fopen($tmp, 'w');
        $counter = 0;
        while ($line = fgets($oldfp)) {
            if ($counter == 4) {
                fwrite($newfp, $entry . "\n");
            }
            $counter++;
            fwrite($newfp, $line);
        }
        fclose($oldfp);
        fclose($newfp);
        system("mv -f $tmp $changes");
    }
}