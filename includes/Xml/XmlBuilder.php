<?php
/*********************************************************************/
/*  PROGRAM          FlexRC                                          */
/*  PROPERTY         3-7170 Ash Cres                                 */
/*  OF               Vancouver BC   V6P 3K7                          */
/*  				 Voice 604 800-7879                              */
/*                                                                   */
/*  Any usage / copying / extension or modification without          */
/*  prior authorization is prohibited                                */
/*********************************************************************/

//declare(strict_types=1);

namespace OneTeamSoftware\WooCommerce\Xml;

defined('ABSPATH') || exit;

if (!class_exists(__NAMESPACE__ . '\\XmlBuilder')):

class XmlBuilder
{
	public function build(array $values)
	{
		$document = $this->createDocument();

		$root = $this->createRootElement($document, $values);
		$this->appendValues($document, $root, $values);
		
		return $document->saveXML();
	}

	protected function appendValues(\DomDocument &$document, \DomElement &$root, array $values)
	{
		$idx = 0;

		foreach ($values as $key => $value) {
			$nodeName = $key;
			$targetNode = &$root;
			if (is_numeric($key)) {
				$nodeName = $root->nodeName;
				$targetNode = &$root->parentNode;
			}

			if (is_array($value)) {
				$node = $this->createElement($document, $targetNode, $nodeName);

				$this->appendValues($document, $node, $value);

				if ($node->childNodes->length == 0) {
					$node->parentNode->removeChild($node);
				}
				
			} else if (is_numeric($key) && $idx == 0) {
				$root->nodeValue = $this->getValue($value);
			} else {
				$node = $this->createElement($document, $targetNode, $nodeName);

				$node->nodeValue = $this->getValue($value);
			}

			$idx++;
		}
	}

	protected function createDocument()
	{
		$document = new \DomDocument('1.0', 'UTF-8');
		$document->formatOutput = true;
		$document->preserveWhiteSpace = false;

		return $document;
	}

	protected function createRootElement(\DomDocument &$document, array &$values)
	{
		$nodeName = 'root';
		if (count($values) == 1) {
			$nodeName = current(array_keys($values));
			$values = current($values);
		}

		$root = $document->createElement($nodeName);
		$document->appendChild($root);

		return $root;
	}

	protected function createElement(\DomDocument &$document, \DomElement &$root, $nodeName)
	{
		$node = $document->createElement($nodeName);
		$root->appendChild($node);

		return $node;
	}

	protected function getValue($value)
	{
		if (is_bool($value)) {
			$value = $value ? "true" : "false";
		}

		return $value;
	}
}

endif;
