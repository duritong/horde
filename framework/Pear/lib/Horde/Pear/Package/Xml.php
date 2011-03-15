<?php
/**
 * Handles package.xml files.
 *
 * PHP version 5
 *
 * @category Horde
 * @package  Pear
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.fsf.org/copyleft/lgpl.html LGPL
 * @link     http://pear.horde.org/index.php?package=Pear
 */

/**
 * Handles package.xml files.
 *
 * Copyright 2011 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @category Horde
 * @package  Pear
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.fsf.org/copyleft/lgpl.html LGPL
 * @link     http://pear.horde.org/index.php?package=Pear
 */
class Horde_Pear_Package_Xml
{
    /** The package.xml namespace */
    const XMLNAMESPACE = 'http://pear.php.net/dtd/package-2.0';

    /**
     * The parsed XML.
     *
     * @var DOMDocument
     */
    private $_xml;

    /**
     * The XPath query handler.
     *
     * @var DOMXpath
     */
    private $_xpath;

    /**
     * Constructor.
     *
     * @param resource $xml The package.xml as stream.
     */
    public function __construct($xml)
    {
        rewind($xml);
        $this->_xml = new DOMDocument('1.0', 'UTF-8');
        $this->_xml->preserveWhiteSpace = true;
        $this->_xml->formatOutput = false;
        $this->_xml->loadXML(stream_get_contents($xml));
        $this->_xpath = new DOMXpath($this->_xml);
        $this->_xpath->registerNamespace('p', self::XMLNAMESPACE);
    }

    /**
     * Return the package name.
     *
     * @return string The name of the package.
     */
    public function getName()
    {
        return $this->getNodeText('/p:package/p:name');
    }

    /**
     * Mark the package as being release and set the timestamps to now.
     *
     * @return NULL
     */
    public function timestamp()
    {
        $this->replaceTextNode('/p:package/p:date', date('Y-m-d'));
        $this->replaceTextNode('/p:package/p:time', date('H:i:s'));
        $version = $this->getNodeText('/p:package/p:version/p:release');
        foreach($this->findNodes('/p:package/p:changelog/p:release') as $release) {
            if ($this->getNodeTextRelativeTo('./p:version/p:release', $release) == $version) {
                $this->replaceTextNodeRelativeTo('./p:date', $release, date('Y-m-d'));
            }
        }
    }

    /**
     * Return the complete package.xml as string.
     *
     * @return string The package.xml content.
     */
    public function __toString()
    {
        $result = $this->_xml->saveXML();
        $result = preg_replace(
            '#<package (.*) (packagerversion="[.0-9]*" version="2.0")#',
            '<package \2 \1',
            $result
        );
        return preg_replace('#"/>#', '" />', $result);
    }

    /**
     * Return a single named node matching the given XPath query.
     *
     * @param string $query The query.
     *
     * @return DOMNode|false The named DOMNode or empty if no node was found.
     */
    public function findNode($query)
    {
        return $this->_findSingleNode($this->_xpath->query($query));
    }

    /**
     * Return a single named node below the given context matching the given
     * XPath query.
     *
     * @param string  $query   The query.
     * @param DOMNode $context Search below this node.
     *
     * @return DOMNode|false The named DOMNode or empty if no node was found.
     */
    public function findNodeRelativeTo($query, DOMNode $context)
    {
        return $this->_findSingleNode($this->_xpath->query($query, $context));
    }

    /**
     * Return a single node for the result set.
     *
     * @param DOMNodeList $result The query result.
     *
     * @return DOMNode|false The DOMNode or empty if no node was found.
     */
    private function _findSingleNode($result)
    {
        if ($result->length) {
            return $result->item(0);
        }
        return false;
    }

    /**
     * Return all nodes matching the given XPath query.
     *
     * @param string $query The query.
     *
     * @return DOMNodeList The list of DOMNodes.
     */
    public function findNodes($query)
    {
        return $this->_xpath->query($query);
    }

    /**
     * Return the content of a single named node matching the given XPath query.
     *
     * @param string $path The node path.
     *
     * @return string|false The node content as string or empty if no node was
     *                      found.
     */
    public function getNodeText($path)
    {
        if ($node = $this->findNode($path)) {
            return $node->textContent;
        }
        throw new Horde_Pear_Exception(
            sprintf('"%s" element is missing!', $path)
        );
    }

    /**
     * Return the content of a single named node below the given context
     * and matching the given XPath query.
     *
     * @param string  $path    The node path.
     * @param DOMNode $context Search below this node.
     *
     * @return string|false The node content as string or empty if no node was
     *                      found.
     */
    public function getNodeTextRelativeTo($path, DOMNode $context)
    {
        if ($node = $this->findNodeRelativeTo($path, $context)) {
            return $node->textContent;
        }
        throw new Horde_Pear_Exception(
            sprintf('"%s" element is missing!', $path)
        );
    }

    /**
     * Replace a specific text node
     *
     * @param string $path  The XPath query pointing to the node.
     * @param string $value The new text value.
     *
     * @return DOMNodeList The list of DOMNodes.
     */
    public function replaceTextNode($path, $value)
    {
        if ($node = $this->findNode($path)) {
            $this->_xml->documentElement->replaceChild(
                $this->_replacementNode($node, $value), $node
            );
        }
    }

    /**
     * Replace a specific text node
     *
     * @param string  $path    The XPath query pointing to the node.
     * @param DOMNode $context Search below this node.
     * @param string  $value   The new text value.
     *
     * @return DOMNodeList The list of DOMNodes.
     */
    public function replaceTextNodeRelativeTo($path, DOMNode $context, $value)
    {
        if ($node = $this->findNodeRelativeTo($path, $context)) {
            $context->replaceChild(
                $this->_replacementNode($node, $value), $node
            );
        }
    }

    /**
     * Generate a replacement node.
     *
     * @param DOMNode $old_node The old DOMNode to be replaced.
     * @param string  $value    The new text value.
     *
     * @return DOMNode The new DOMNode.
     */
    private function _replacementNode($old_node, $value)
    {
        $new_node = $this->_xml->createElementNS(
            self::XMLNAMESPACE, $old_node->tagName
        );
        $text = $this->_xml->createTextNode($value);
        $new_node->appendChild($text);
        return $new_node;
    }
}